<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserOrganizationAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserOrganizationAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrator',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin_test',
            'email' => 'admin_test@test.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test creating a user with organization_assignment saves to relational table.
     */
    public function test_create_user_with_organization_assignment_success(): void
    {
        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/users', [
            'name' => 'Test Operator',
            'username' => 'operator1',
            'email' => 'operator1@test.com',
            'password' => 'secret123',
            'role_id' => $this->adminRole->id,
            'organization_assignment' => [
                'warehouses' => ['PRD01-01', 'PRD01-05'],
                'branches' => ['SBY', 'JKT'],
                'business_units' => ['UNIT5'],
                'departments' => ['PROD'],
                'expeditions' => ['EXP-001'],
                'distributors' => ['CUST-001', 'CUST-002'],
            ],
        ]);

        $response->assertStatus(200);

        $createdUserId = $response->json('data.id');
        $this->assertNotNull($createdUserId);

        // Verify relational table entries
        $this->assertDatabaseHas('user_organization_assignments', [
            'user_id' => $createdUserId,
            'type' => 'warehouse',
            'value' => 'PRD01-01',
        ]);
        $this->assertDatabaseHas('user_organization_assignments', [
            'user_id' => $createdUserId,
            'type' => 'warehouse',
            'value' => 'PRD01-05',
        ]);
        $this->assertDatabaseHas('user_organization_assignments', [
            'user_id' => $createdUserId,
            'type' => 'branch',
            'value' => 'SBY',
        ]);
        $this->assertDatabaseHas('user_organization_assignments', [
            'user_id' => $createdUserId,
            'type' => 'distributor',
            'value' => 'CUST-001',
        ]);

        $orgAssignment = $response->json('data.organization_assignment');
        $this->assertContains('PRD01-01', $orgAssignment['warehouses']);
        $this->assertContains('PRD01-05', $orgAssignment['warehouses']);
        $this->assertContains('SBY', $orgAssignment['branches']);
        $this->assertContains('CUST-001', $orgAssignment['distributors']);
    }

    /**
     * Test updating a user's organization_assignment.
     */
    public function test_update_user_organization_assignment(): void
    {
        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        $user = User::create([
            'name' => 'User To Update',
            'username' => 'update_user',
            'email' => 'update_user@test.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        UserOrganizationAssignment::create([
            'user_id' => $user->id,
            'type' => 'warehouse',
            'value' => 'OLD_WHS',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/distributor-channel/v1/users/{$user->id}", [
            'name' => 'Updated User Name',
            'organization_assignment' => [
                'warehouses' => ['NEW_WHS_1', 'NEW_WHS_2'],
                'branches' => ['BDG'],
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('user_organization_assignments', [
            'user_id' => $user->id,
            'value' => 'OLD_WHS',
        ]);

        $this->assertDatabaseHas('user_organization_assignments', [
            'user_id' => $user->id,
            'type' => 'warehouse',
            'value' => 'NEW_WHS_1',
        ]);
        $this->assertDatabaseHas('user_organization_assignments', [
            'user_id' => $user->id,
            'type' => 'branch',
            'value' => 'BDG',
        ]);
    }

    /**
     * Test login response contains organization_assignment.
     */
    public function test_login_returns_organization_assignment(): void
    {
        $user = User::create([
            'name' => 'Login Tester',
            'username' => 'logintester',
            'email' => 'logintester@test.com',
            'password' => Hash::make('secret123'),
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        UserOrganizationAssignment::create([
            'user_id' => $user->id,
            'type' => 'branch',
            'value' => 'SBY',
        ]);
        UserOrganizationAssignment::create([
            'user_id' => $user->id,
            'type' => 'department',
            'value' => 'PROD',
        ]);

        $response = $this->postJson('/api/distributor-channel/v1/auth/login', [
            'username' => 'logintester',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertArrayHasKey('organization_assignment', $response->json('data'));
        $this->assertArrayHasKey('organization_assignment', $response->json('data.user'));

        $this->assertContains('SBY', $response->json('data.organization_assignment.branches'));
        $this->assertContains('PROD', $response->json('data.organization_assignment.departments'));
    }

    /**
     * Test deleting user cascades and removes organization assignments.
     */
    public function test_delete_user_cascades_organization_assignments(): void
    {
        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        $user = User::create([
            'name' => 'Delete Tester',
            'username' => 'deletetester',
            'email' => 'deletetester@test.com',
            'password' => Hash::make('secret123'),
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        UserOrganizationAssignment::create([
            'user_id' => $user->id,
            'type' => 'warehouse',
            'value' => 'PRD-DELETE',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/distributor-channel/v1/users/{$user->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('user_organization_assignments', ['user_id' => $user->id]);
    }
}
