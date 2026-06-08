<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Distributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DistributorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create distributor
        Distributor::create([
            'code_customer' => 'C110000411',
            'name' => 'PT XYZ',
            'address' => 'Jl. Dummy No. 123',
            'phone' => '021-12345678',
            'email' => 'info@xyz.com',
            'status' => 1,
        ]);

        // Create active user associated with distributor
        $this->user = User::create([
            'name' => 'Distributor User',
            'username' => 'distuser',
            'email' => 'dist@example.com',
            'password' => Hash::make($this->password),
            'code_customer' => 'C110000411',
            'is_active' => true,
        ]);
    }

    /**
     * Test list of distributors.
     */
    public function test_get_distributors_list(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/distributors');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Daftar distributor berhasil diambil.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code_customer',
                        'name',
                        'address',
                        'phone',
                        'email',
                        'status',
                    ]
                ]
            ]);
    }

    /**
     * Test get distributor detail.
     */
    public function test_get_distributor_detail(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;
        $distributor = Distributor::where('code_customer', 'C110000411')->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/distributors/' . $distributor->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Detail distributor berhasil diambil.',
                'data' => [
                    'code_customer' => 'C110000411',
                    'name' => 'PT XYZ',
                ]
            ]);
    }

    /**
     * Test distributor sync from SAP.
     */
    public function test_sync_distributors(): void
    {
        Http::fake([
            '103.18.133.187:3100/api/ListCust' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'CardCode' => 'C110000411',
                        'CardName' => 'PT XYZ',
                        'Address' => 'Jl. Dummy No. 123, Jakarta',
                        'Phone1' => '021-12345678',
                        'E_Mail' => 'info@xyz.com',
                        'SubGroup' => 'Distributor',
                    ],
                    [
                        'CardCode' => 'C110000412',
                        'CardName' => 'PT Berkah Abadi',
                        'Address' => 'Jl. Pahlawan No. 45, Surabaya',
                        'Phone1' => '031-87654321',
                        'E_Mail' => 'contact@berkahabadi.com',
                        'SubGroup' => 'Distributor',
                    ],
                    [
                        'CardCode' => 'C110000413',
                        'CardName' => 'Bukan Distributor',
                        'Address' => 'Alamat Lain',
                        'Phone1' => '021-999',
                        'E_Mail' => 'bukan@dist.com',
                        'SubGroup' => 'Retail', // Harusnya difilter keluar
                    ]
                ]
            ], 200)
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/distributors/sync');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data distributor berhasil disinkronisasi dari SAP.',
            ]);

        // Assert audit log was recorded
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'SYNC_DISTRIBUTORS',
        ]);

        // Assert both PT XYZ and PT Berkah Abadi exist in the database
        $this->assertDatabaseHas('distributors', [
            'code_customer' => 'C110000411',
            'name' => 'PT XYZ',
        ]);
        $this->assertDatabaseHas('distributors', [
            'code_customer' => 'C110000412',
            'name' => 'PT Berkah Abadi',
        ]);

        // Assert retail subgroup was NOT synced
        $this->assertDatabaseMissing('distributors', [
            'code_customer' => 'C110000413',
        ]);
    }

    /**
     * Test unauthenticated access to distributor endpoints.
     */
    public function test_unauthenticated_access(): void
    {
        $response = $this->getJson('/api/distributor-channel/v1/distributors');
        $response->assertStatus(401);
    }
}
