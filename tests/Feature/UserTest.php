<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Distributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Role $role;
    protected Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        // Create standard role
        $this->role = Role::create([
            'name' => 'distributor',
            'is_active' => true,
            'accessible_systems' => ['distributor', 'ekspedisi'],
        ]);

        // Create distributor
        $this->distributor = Distributor::create([
            'code_customer' => 'C110000411',
            'name' => 'PT XYZ',
            'status' => 1,
        ]);

        // Create admin user
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->role->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test getting users list loads distributor.
     */
    public function test_get_users_list(): void
    {
        // Associate adminUser with distributor
        $this->adminUser->update(['code_customer' => 'C110000411']);

        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/users');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Daftar user berhasil diambil.',
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'username',
                        'email',
                        'code_customer',
                        'role',
                        'distributor' => [
                            'id',
                            'code_customer',
                            'name',
                        ],
                    ]
                ]
            ]);
    }

    /**
     * Test user creation with valid code_customer.
     */
    public function test_create_user_with_valid_distributor(): void
    {
        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/users', [
            'name' => 'New User',
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role_id' => $this->role->id,
            'code_customer' => 'C110000411',
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User berhasil dibuat.',
                'data' => [
                    'username' => 'newuser',
                    'code_customer' => 'C110000411',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'username' => 'newuser',
            'code_customer' => 'C110000411',
        ]);
    }

    /**
     * Test user creation fails with invalid code_customer.
     */
    public function test_create_user_fails_with_invalid_distributor(): void
    {
        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/users', [
            'name' => 'New User',
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role_id' => $this->role->id,
            'code_customer' => 'INVALID_CODE',
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status_code' => 422,
            ]);
    }

    /**
     * Test user update with valid code_customer.
     */
    public function test_update_user_with_valid_distributor(): void
    {
        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/distributor-channel/v1/users/' . $this->adminUser->id, [
            'name' => 'Admin User Updated',
            'code_customer' => 'C110000411',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User berhasil diperbarui.',
                'data' => [
                    'name' => 'Admin User Updated',
                    'code_customer' => 'C110000411',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->adminUser->id,
            'name' => 'Admin User Updated',
            'code_customer' => 'C110000411',
        ]);
    }

    /**
     * Test user creation with accessible_systems inherited from role.
     */
    public function test_create_user_with_accessible_systems(): void
    {
        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/users', [
            'name' => 'Systems User',
            'username' => 'sysuser',
            'email' => 'sysuser@example.com',
            'password' => 'password123',
            'role_id' => $this->role->id,
            'is_active' => true,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(['distributor', 'ekspedisi'], $response->json('data.accessible_systems'));

        $this->assertDatabaseHas('users', [
            'username' => 'sysuser',
        ]);
    }

    /**
     * Test updating user with accessible_systems.
     */
    public function test_update_user_with_accessible_systems(): void
    {
        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/distributor-channel/v1/users/' . $this->adminUser->id, [
            'accessible_systems' => ['distributor'],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(['distributor'], $response->json('data.accessible_systems'));
    }

    /**
     * Test updating custom permissions with dynamic actions (e.g. sync, custom_action).
     */
    public function test_update_custom_permissions_dynamic_actions(): void
    {
        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        $payload = [
            'actions' => [
                [
                    'menu_key' => '14',
                    'actions' => [
                        'create' => true,
                        'read' => true,
                        'update' => true,
                        'delete' => true,
                        'approve' => false,
                        'export' => true,
                        'sync' => true,
                    ],
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/distributor-channel/v1/users/' . $this->adminUser->id . '/custom-permissions', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $user = User::find($this->adminUser->id);
        $this->assertTrue($user->hasPermission('14', 'sync'));
        $this->assertTrue($user->hasPermission('14', 'create'));
        $this->assertFalse($user->hasPermission('14', 'approve'));
    }
}
