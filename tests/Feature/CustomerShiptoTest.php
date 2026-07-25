<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Distributor;
use App\Models\CustomerShipto;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerShiptoTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Distributor $distributor;
    protected string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create([
            'id' => 1,
            'name' => 'administrator',
            'is_active' => true,
            'accessible_systems' => ['distributor'],
        ]);

        // Create distributor
        $this->distributor = Distributor::create([
            'code_customer' => 'C110000001',
            'name' => 'PT XYZ',
            'address' => 'Jl. Dummy No. 123',
            'phone' => '021-12345678',
            'email' => 'info@xyz.com',
            'status' => 1,
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

    public function test_get_customer_shiptos_list(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create mock shipto records
        CustomerShipto::create([
            'card_code' => 'C110000001',
            'name' => '124, TK',
            'address' => 'Alamat Kirim',
            'city' => 'SIDOARJO',
            'street' => 'PS BLURU KIDUL NO 124',
        ]);

        CustomerShipto::create([
            'card_code' => 'C110000001',
            'name' => 'IBU YEM, TK',
            'address' => 'Alamat Alternatif',
            'city' => 'SURABAYA',
            'street' => 'ALUN ALUN BANGUNSARI SELATAN N',
        ]);

        // Request list
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/distributors/shiptos');

        // Assertions
        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Daftar Ship To master berhasil diambil.');
        $response->assertJsonCount(2, 'data.data');

        // Request with filter and search
        $searchResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/distributors/shiptos?search=YEM');

        $searchResponse->assertStatus(200);
        $searchResponse->assertJsonCount(1, 'data.data');
        $searchResponse->assertJsonPath('data.data.0.name', 'IBU YEM, TK');
    }

    public function test_sync_customer_shiptos(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Fake SAP API response
        Http::fake([
            'http://103.18.133.187:3100/api/ListKiriman' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'CardCode' => 'C110000001',
                        'NAME' => '124, TK',
                        'Address' => 'Alamat Kirim',
                        'City' => 'SIDOARJO',
                        'Street' => 'PS BLURU KIDUL NO 124'
                    ],
                    [
                        'CardCode' => 'C110000002',
                        'NAME' => 'IBU YEM, TK',
                        'Address' => 'Alamat Kirim',
                        'City' => 'SURABAYA',
                        'Street' => 'ALUN ALUN BANGUNSARI SELATAN N'
                    ]
                ]
            ], 200)
        ]);

        // Sync request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/distributors/shiptos/sync');

        // Assertions
        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Sinkronisasi Ship To master dari SAP berhasil.');
        $response->assertJsonPath('data.count', 2);

        // Check database
        $this->assertDatabaseHas('customer_shiptos', [
            'card_code' => 'C110000001',
            'name' => '124, TK',
            'address' => 'Alamat Kirim',
            'city' => 'SIDOARJO',
            'street' => 'PS BLURU KIDUL NO 124',
        ]);

        $this->assertDatabaseHas('customer_shiptos', [
            'card_code' => 'C110000002',
            'name' => 'IBU YEM, TK',
            'address' => 'Alamat Kirim',
            'city' => 'SURABAYA',
            'street' => 'ALUN ALUN BANGUNSARI SELATAN N',
        ]);

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Sync Ship To Master',
            'user_id' => $this->user->id,
        ]);
    }
}
