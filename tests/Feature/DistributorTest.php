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
            'mail_address' => 'Jl. Dummy No. 123, Kantor Pos',
            'contact_person' => 'John Doe',
            'sub_group' => 'Distributor',
            'depo' => 'TULUNGAGUNG',
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
                        'mail_address',
                        'contact_person',
                        'sub_group',
                        'depo',
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
                        'MailAddres' => 'Jl DSN GEBRUKAN DESA SRIKATON',
                        'Phone1' => '021-12345678',
                        'E_Mail' => 'info@xyz.com',
                        'CntctPrsn' => 'John Doe',
                        'SubGroup' => 'Distributor',
                        'Depo' => 'TULUNGAGUNG',
                    ],
                    [
                        'CardCode' => 'C110000412',
                        'CardName' => 'PT Berkah Abadi',
                        'Address' => 'Jl. Pahlawan No. 45, Surabaya',
                        'MailAddres' => 'Jl. Pahlawan Kantor Cabang',
                        'Phone1' => '031-87654321',
                        'E_Mail' => 'contact@berkahabadi.com',
                        'CntctPrsn' => 'Jane Smith',
                        'SubGroup' => 'Distributor',
                        'Depo' => 'SURABAYA',
                    ],
                    [
                        'CardCode' => 'C110000413',
                        'CardName' => 'Bukan Distributor',
                        'Address' => 'Alamat Lain',
                        'MailAddres' => 'Alamat Lain',
                        'Phone1' => '021-999',
                        'E_Mail' => 'bukan@dist.com',
                        'CntctPrsn' => 'Bukan',
                        'SubGroup' => 'Retail', // Harusnya difilter keluar
                        'Depo' => 'JAKARTA',
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
            'mail_address' => 'Jl DSN GEBRUKAN DESA SRIKATON',
            'contact_person' => 'John Doe',
            'sub_group' => 'Distributor',
            'depo' => 'TULUNGAGUNG',
        ]);
        $this->assertDatabaseHas('distributors', [
            'code_customer' => 'C110000412',
            'name' => 'PT Berkah Abadi',
            'mail_address' => 'Jl. Pahlawan Kantor Cabang',
            'contact_person' => 'Jane Smith',
            'sub_group' => 'Distributor',
            'depo' => 'SURABAYA',
        ]);

        // Assert retail subgroup was NOT synced
        $this->assertDatabaseMissing('distributors', [
            'code_customer' => 'C110000413',
        ]);
    }

    /**
     * Test search distributors.
     */
    public function test_search_distributors(): void
    {
        // Create another distributor to test search filtering
        Distributor::create([
            'code_customer' => 'C110000412',
            'name' => 'PT Berkah Abadi',
            'address' => 'Jl. Pahlawan No. 45, Surabaya',
            'phone' => '031-87654321',
            'email' => 'contact@berkahabadi.com',
            'mail_address' => 'Jl. Pahlawan Kantor Cabang',
            'contact_person' => 'Jane Smith',
            'sub_group' => 'Distributor',
            'depo' => 'SURABAYA',
            'status' => 1,
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        // Search by name "xyz" (lowercase to test case insensitivity)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/distributors?search=xyz');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code_customer', 'C110000411');

        // Search by depo "surabaya" (lowercase to test case insensitivity)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/distributors?search=surabaya');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code_customer', 'C110000412');

        // Search with non-matching query
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/distributors?search=NonExistentKeyword');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
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
