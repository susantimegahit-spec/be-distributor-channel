<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Distributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Role $role;
    protected Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\MasterApprovalSeeder::class);

        // Create standard role
        $this->role = Role::create([
            'name' => 'distributor',
            'is_active' => true,
        ]);

        // Create distributor
        $this->distributor = Distributor::create([
            'code_customer' => 'C110000411',
            'name' => 'PT XYZ',
            'status' => 1,
        ]);

        // Create user
        $this->user = User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->role->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test getting master approvals without authentication.
     */
    public function test_get_master_approvals_unauthenticated(): void
    {
        $response = $this->getJson('/api/distributor-channel/v1/master-approvals');

        $response->assertStatus(401);
    }

    /**
     * Test getting master approvals list with authentication.
     */
    public function test_get_master_approvals_authenticated(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/master-approvals');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Daftar master approval berhasil diambil.',
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'action',
                        'notification_type',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ]);

        // Verify ordering is ID ascending
        $data = $response->json('data');
        $this->assertCount(6, $data);
        $this->assertEquals(1, $data[0]['id']);
        $this->assertEquals('DRAFT', $data[0]['name']);
        $this->assertEquals(6, $data[5]['id']);
        $this->assertEquals('ORDER_APPROVED', $data[5]['name']);
    }
}
