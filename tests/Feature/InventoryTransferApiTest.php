<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InventoryTransferApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create role
        Role::create([
            'id' => 1,
            'name' => 'administrator',
            'is_active' => true,
            'accessible_systems' => ['distributor'],
        ]);

        // Create admin user
        $this->user = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make($this->password),
            'role_id' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Test successful retrieval of IT list.
     */
    public function test_get_list_it_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        Http::fake([
            '*/api/getListIT' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'DocEntry' => '20',
                        'DocNum' => '260130001',
                        'DocDate' => '20260102',
                        'FromWhsCode' => 'FG03-BM',
                        'ToWhsCode' => 'FG03-KR',
                        'Comments' => 'MUTASI BM KE KR'
                    ]
                ]
            ], 200)
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/warehouses/inventory-transfer');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.DocEntry', '20')
            ->assertJsonPath('data.0.DocNum', '260130001');
    }

    /**
     * Test retrieval of IT list with filters.
     */
    public function test_get_list_it_with_filters(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        Http::fake([
            '*/api/getListIT' => function ($request) {
                $body = json_decode($request->body(), true);
                if (
                    isset($body['From']) && $body['From'] === '2026-1-1' &&
                    isset($body['To']) && $body['To'] === '2026-12-31' &&
                    isset($body['WhsCode']) && $body['WhsCode'] === 'VRTR01' &&
                    isset($body['ToWhsCode']) && $body['ToWhsCode'] === 'FG01'
                ) {
                    return Http::response([
                        'ErrorCode' => 0,
                        'Message' => '',
                        'Result' => [
                            [
                                'DocEntry' => '20',
                                'DocNum' => '260130001',
                                'DocDate' => '20260102',
                                'FromWhsCode' => 'VRTR01',
                                'ToWhsCode' => 'FG01',
                                'Comments' => 'MUTASI BM KE KR'
                            ]
                        ]
                    ], 200);
                }
                return Http::response(['ErrorCode' => 1, 'Message' => 'Invalid Filters'], 200);
            }
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/warehouses/inventory-transfer?From=2026-1-1&To=2026-12-31&WhsCode=VRTR01&ToWhsCode=FG01');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.DocEntry', '20')
            ->assertJsonPath('data.0.FromWhsCode', 'VRTR01')
            ->assertJsonPath('data.0.ToWhsCode', 'FG01');
    }

    /**
     * Test failed retrieval of IT list when SAP returns ErrorCode.
     */
    public function test_get_list_it_sap_error(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        Http::fake([
            '*/api/getListIT' => Http::response([
                'ErrorCode' => 1,
                'Message' => 'Gagal mengambil data dari SAP B1',
                'Result' => null
            ], 200)
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/warehouses/inventory-transfer');

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Gagal mengambil data dari SAP B1');
    }

    /**
     * Test successful retrieval of IT by ID.
     */
    public function test_get_it_by_id_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        Http::fake([
            '*/api/getITbyId' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    'Table1' => [
                        [
                            'Series' => '5340',
                            'DocEntry' => '20',
                            'DocNum' => '260130001',
                            'DocDate' => '20260102',
                            'FromWhsCode' => 'FG03-BM',
                            'ToWhsCode' => 'FG03-KR',
                            'Comments' => 'MUTASI BM KE KR'
                        ]
                    ],
                    'Table2' => [
                        [
                            'DocEntry' => '20',
                            'LineNum' => '0',
                            'ItemCode' => 'B26',
                            'ItemName' => 'KOP 250 M @ 10 KG / BAL',
                            'Quantity' => '10.000000',
                            'FromWhsCod' => 'FG03-BM',
                            'ToWhsCode' => 'FG03-KR',
                            'Uom' => 'Bal',
                            'UseBaseUn' => 'Y'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/warehouses/inventory-transfer/get-by-id', [
            'CustomQuery' => '20'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.Series', '5340')
            ->assertJsonPath('data.DocEntry', '20')
            ->assertJsonPath('data.lines.0.ItemCode', 'B26')
            ->assertJsonPath('data.lines.0.Quantity', '10.000000');
    }

    /**
     * Test validation error on get IT by ID.
     */
    public function test_get_it_by_id_validation_error(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/warehouses/inventory-transfer/get-by-id', [
            // 'CustomQuery' is missing
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status_code', 422);
    }

    /**
     * Test successful cancellation of Inventory Transfer.
     */
    public function test_cancel_it_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        Http::fake([
            '*/api/CancelIT' => function ($request) {
                $body = json_decode($request->body(), true);
                if (
                    isset($body['DocEntry']) && $body['DocEntry'] === '1231' &&
                    isset($body['AddonId']) && $body['AddonId'] === 2 &&
                    isset($body['UserId']) && $body['UserId'] === $this->user->id
                ) {
                    return Http::response([
                        'ErrorCode' => 0,
                        'Message' => 'Sukses Cancel Inventory Transfer',
                        'Result' => ['DocEntry' => '1231']
                    ], 200);
                }
                return Http::response(['ErrorCode' => 1, 'Message' => 'Invalid parameters'], 200);
            }
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/warehouses/inventory-transfer/cancel', [
            'DocEntry' => '1231'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Sukses Cancel Inventory Transfer');
    }

    /**
     * Test validation error when cancelling IT without DocEntry.
     */
    public function test_cancel_it_validation_error(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/warehouses/inventory-transfer/cancel', []);

        $response->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status_code', 422);
    }
}
