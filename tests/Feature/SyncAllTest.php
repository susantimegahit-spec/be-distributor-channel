<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Distributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncAllTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create standard distributor
        Distributor::create([
            'code_customer' => 'C110003419',
            'name' => 'SAKTI SETIA SANTOSA, PT',
            'status' => 1,
        ]);

        // Create user
        $this->user = User::create([
            'name' => 'Active User',
            'username' => 'activeuser',
            'email' => 'active@example.com',
            'password' => Hash::make($this->password),
            'code_customer' => 'C110003419',
            'is_active' => true,
        ]);
    }

    /**
     * Test unauthenticated access.
     */
    public function test_sync_all_unauthenticated(): void
    {
        $response = $this->postJson('/api/distributor-channel/v1/sync/all');
        $response->assertStatus(401);
    }

    /**
     * Test sync all endpoints successfully.
     */
    public function test_sync_all_success(): void
    {
        // Mock all SAP endpoints called by the services
        Http::fake([
            '103.18.133.187:3100/api/ListCust' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'CardCode' => 'C110003419',
                        'CardName' => 'SAKTI SETIA SANTOSA, PT',
                        'SubGroup' => 'Distributor',
                        'Depo' => 'SURABAYA',
                    ]
                ]
            ], 200),

            '103.18.133.187:3100/api/ListOcrCode' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'OcrCode' => 'SBY',
                        'OcrName' => 'Surabaya Depo',
                    ]
                ]
            ], 200),

            '103.18.133.187:3100/api/ListItem' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'ItemCode' => 'E65',
                        'ItemName' => 'TOP 250 M @ 10 KG / BAL',
                        'UoMEntry' => 1,
                        'SalUnitMsr' => 'Kg',
                    ]
                ]
            ], 200),

            '103.18.133.187:3100/api/ListSalesEmp' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'SlpCode' => 1,
                        'SlpName' => 'Sales Person A',
                    ]
                ]
            ], 200),

            '103.18.133.187:3100/api/ListVat' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'Code' => 'S1',
                        'Name' => 'PPN 11%',
                        'Rate' => 11.0,
                    ]
                ]
            ], 200),

            '103.18.133.187:3100/api/SearchWH' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'WhsCode' => 'FG04',
                        'WhsName' => 'Finished Goods 04',
                    ]
                ]
            ], 200),

            '103.18.133.187:3100/api/ListType' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'FldValue' => 'Cash Diskon',
                        'Descr' => 'Cash Diskon',
                    ]
                ]
            ], 200),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/sync/all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sinkronisasi semua data master dari SAP selesai.',
                'data' => [
                    'distributors' => [
                        'success' => true,
                        'count' => 1,
                    ],
                    'ocr_codes' => [
                        'success' => true,
                    ],
                    'items' => [
                        'success' => true,
                        'count' => 1,
                    ],
                    'sales_employees' => [
                        'success' => true,
                        'count' => 1,
                    ],
                    'vats' => [
                        'success' => true,
                        'count' => 1,
                    ],
                    'warehouses' => [
                        'success' => true,
                        'count' => 1,
                    ],
                    'discount_types' => [
                        'success' => true,
                        'count' => 1,
                    ],
                ]
            ]);

        // Verify databases contain synced elements
        $this->assertDatabaseHas('distributors', [
            'code_customer' => 'C110003419',
            'name' => 'SAKTI SETIA SANTOSA, PT',
            'depo' => 'SURABAYA',
        ]);

        $this->assertDatabaseHas('ocr_codes', [
            'ocr_code' => 'SBY',
            'ocr_name' => 'Surabaya Depo',
        ]);

        $this->assertDatabaseHas('items', [
            'item_code' => 'E65',
            'item_name' => 'TOP 250 M @ 10 KG / BAL',
        ]);

        $this->assertDatabaseHas('sales_employees', [
            'slp_code' => 1,
            'slp_name' => 'Sales Person A',
        ]);

        $this->assertDatabaseHas('vats', [
            'code' => 'S1',
            'name' => 'PPN 11%',
            'rate' => 11.0,
        ]);

        $this->assertDatabaseHas('warehouses', [
            'whs_code' => 'FG04',
            'whs_name' => 'Finished Goods 04',
        ]);

        $this->assertDatabaseHas('discount_types', [
            'fld_value' => 'Cash Diskon',
            'descr' => 'Cash Diskon',
        ]);

        // Verify Audit Logs
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'SYNC_DISTRIBUTORS',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'SYNC_ITEMS',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'SYNC_SALES_EMPLOYEES',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'SYNC_VATS',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'SYNC_WAREHOUSES',
        ]);
    }
}
