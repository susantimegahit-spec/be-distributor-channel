<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Distributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscountTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Role $role;
    protected Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Carbon::setTestNow('2026-03-14');

        $this->role = Role::create([
            'name' => 'distributor',
            'is_active' => true,
        ]);

        $this->distributor = Distributor::create([
            'code_customer' => 'C110003074',
            'name' => 'LESAFFRE SARI',
            'status' => 1,
        ]);

        $this->user = User::create([
            'name' => 'Distributor User',
            'username' => 'distuser',
            'email' => 'dist@example.com',
            'password' => Hash::make('password123'),
            'code_customer' => 'C110003074',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test sending discount successfully to SAP.
     */
    public function test_send_discount_to_sap_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Mock SAP endpoints
        Http::fake([
            '*/api/GetNumDis' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'Col1' => '20260314001'
                    ]
                ]
            ], 200),
            '*/api/addudodiskon' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'UDO Diskon added successfully',
                'Result' => []
            ], 200),
        ]);

        $payload = [
            'CardCode' => 'C110003074',
            'CardName' => 'LESAFFRE SARI',
            'Lines' => [
                [
                    'TypeDiscount' => 'Diskon Item',
                    'Persentase' => 0,
                    'TotalDiskon' => 3000000,
                    'Remarks' => 'DISC SEMARAK AWAL THN',
                    'BatchId' => 456,
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/discounts/sap', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Diskon UDO berhasil dikirim ke SAP.',
                'data' => [
                    'code' => '20260314501',
                    'sap_response' => [
                        'ErrorCode' => 0,
                        'Message' => 'Discount saved locally'
                    ]
                ]
            ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'CREATE_LOCAL_DISCOUNT',
        ]);

        // Verify discount saved in local database
        $this->assertDatabaseHas('sap_discount_headers', [
            'discount_code' => '20260314501',
            'card_code' => 'C110003074',
            'card_name' => 'LESAFFRE SARI',
            'total_so' => 0.00,
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('sap_discount_details', [
            'type_discount' => 'Diskon Item',
            'percentage' => 0.00,
            'total_discount' => 3000000.00,
            'remarks' => 'DISC SEMARAK AWAL THN',
        ]);

        $detail = \App\Models\SapDiscountDetail::where('type_discount', 'Diskon Item')->first();
        $this->assertNotNull($detail);
        $this->assertDatabaseHas('trade_promo_temp', [
            'id' => $detail->id,
            'batch_id' => 456,
        ]);
    }

    /**
     * Test validation failure.
     */
    public function test_send_discount_validation_failure(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Missing required field 'Lines'
        $payload = [
            'CardCode' => 'C110003074',
            'CardName' => 'LESAFFRE SARI',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/discounts/sap', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status_code' => 422,
            ]);
    }


    /**
     * Test sync discount types from SAP.
     */
    public function test_sync_discount_types_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        Http::fake([
            '*/api/ListType' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'FldValue' => 'Cash Diskon',
                        'Descr' => 'Cash Diskon'
                    ],
                    [
                        'FldValue' => 'Promo Diskon',
                        'Descr' => 'Promo Diskon'
                    ]
                ]
            ], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/discounts/types/sync');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data tipe diskon berhasil disinkronisasi dari SAP.',
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'fld_value',
                        'descr',
                        'status',
                    ]
                ]
            ]);

        $this->assertDatabaseHas('discount_types', [
            'fld_value' => 'Cash Diskon',
            'descr' => 'Cash Diskon',
        ]);
        $this->assertDatabaseHas('discount_types', [
            'fld_value' => 'Promo Diskon',
            'descr' => 'Promo Diskon',
        ]);
    }

    /**
     * Test get discount types from local database.
     */
    public function test_get_discount_types(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Seed data
        \App\Models\DiscountType::create([
            'fld_value' => 'Cash Diskon',
            'descr' => 'Cash Diskon Description',
            'status' => 1,
        ]);
        \App\Models\DiscountType::create([
            'fld_value' => 'Quantity Diskon',
            'descr' => 'Quantity Diskon Description',
            'status' => 1,
        ]);

        // Get all
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/discounts/types');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.fld_value', 'Cash Diskon');

        // Search
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/discounts/types?search=Quantity');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.fld_value', 'Quantity Diskon');
    }
}
