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

    /**
     * Test sending discount successfully to SAP.
     */
    public function test_send_discount_to_sap_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Mock SAP endpoints
        Http::fake([
            '103.18.133.187:3100/api/GetNumDis' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'Col1' => '20260314001'
                    ]
                ]
            ], 200),
            '103.18.133.187:3100/api/addudodiskon' => Http::response([
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
                    'Remarks' => 'DISC SEMARAK AWAL THN'
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
                    'code' => '20260314001',
                    'sap_response' => [
                        'ErrorCode' => 0,
                        'Message' => 'UDO Diskon added successfully'
                    ]
                ]
            ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'POST_UDO_DISCOUNT_SAP',
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
     * Test SAP API returned error scenario.
     */
    public function test_send_discount_sap_error(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Mock SAP endpoints returning error
        Http::fake([
            '103.18.133.187:3100/api/GetNumDis' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'Col1' => '20260314001'
                    ]
                ]
            ], 200),
            '103.18.133.187:3100/api/addudodiskon' => Http::response([
                'ErrorCode' => 500,
                'Message' => 'Internal SAP Error',
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
                    'Remarks' => 'DISC SEMARAK AWAL THN'
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/discounts/sap', $payload);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'API SAP addudodiskon mengembalikan error: Internal SAP Error',
            ]);
    }
}
