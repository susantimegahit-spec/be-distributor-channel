<?php

namespace Tests\Feature;

use App\Models\DashboardLayout;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use DatabaseTransactions;

    protected Role $adminRole;
    protected Role $userRole;
    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup roles
        $this->adminRole = Role::firstOrCreate(
            ['name' => 'Administrator'],
            ['is_active' => true]
        );

        $this->userRole = Role::firstOrCreate(
            ['name' => 'Customer Portal'],
            ['is_active' => true]
        );

        // Setup users
        $this->adminUser = User::create([
            'name'      => 'Admin Test',
            'username'  => 'admin_test_' . uniqid(),
            'email'     => 'admin_test_' . uniqid() . '@example.com',
            'password'  => bcrypt('password123'),
            'role_id'   => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->regularUser = User::create([
            'name'      => 'Regular User Test',
            'username'  => 'user_test_' . uniqid(),
            'email'     => 'user_test_' . uniqid() . '@example.com',
            'password'  => bcrypt('password123'),
            'role_id'   => $this->userRole->id,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_layouts(): void
    {
        $response = $this->getJson('/api/dashboard-layouts/me');
        $response->assertStatus(401);
    }

    public function test_get_my_layout_returns_empty_default_when_unconfigured(): void
    {
        Sanctum::actingAs($this->regularUser);

        // Test with direct path
        $response = $this->getJson('/api/dashboard-layouts/me');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dashboard layout has not been configured',
                'data'    => [
                    'role_id'    => $this->userRole->id,
                    'version'    => 0,
                    'rows'       => [['row' => 1, 'columns' => 3]],
                    'widgets'    => [],
                    'updated_at' => null,
                ],
            ]);

        // Test with module path
        $responseModule = $this->getJson('/api/distributor-channel/v1/dashboard-layouts/me');
        $responseModule->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dashboard layout has not been configured',
            ]);
    }

    public function test_non_admin_cannot_access_or_modify_other_role_layout(): void
    {
        Sanctum::actingAs($this->regularUser);

        // Non-admin GET /roles/{id}
        $getResponse = $this->getJson("/api/dashboard-layouts/roles/{$this->userRole->id}");
        $getResponse->assertStatus(403)
            ->assertJson(['success' => false]);

        // Non-admin PUT /roles/{id}
        $putResponse = $this->putJson("/api/dashboard-layouts/roles/{$this->userRole->id}", [
            'version' => 0,
            'rows'    => [['row' => 1, 'columns' => 3]],
            'widgets' => [],
        ]);
        $putResponse->assertStatus(403);

        // Non-admin DELETE /roles/{id}
        $delResponse = $this->deleteJson("/api/dashboard-layouts/roles/{$this->userRole->id}");
        $delResponse->assertStatus(403);
    }

    public function test_admin_can_save_and_retrieve_layout_for_a_role(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'version' => 0,
            'rows'    => [
                ['row' => 1, 'columns' => 3],
                ['row' => 2, 'columns' => 2],
            ],
            'widgets' => [
                [
                    'id'     => 'customer-orders',
                    'sort'   => 1,
                    'row'    => 1,
                    'column' => 1,
                    'span'   => 1,
                ],
                [
                    'id'     => 'order-ready',
                    'sort'   => 2,
                    'row'    => 2,
                    'column' => 1,
                    'span'   => 2,
                ],
            ],
        ];

        $saveResponse = $this->putJson("/api/dashboard-layouts/roles/{$this->userRole->id}", $payload);

        $saveResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dashboard layout saved successfully',
                'data'    => [
                    'role_id' => $this->userRole->id,
                    'version' => 1,
                    'rows'    => [
                        ['row' => 1, 'columns' => 3],
                        ['row' => 2, 'columns' => 2],
                    ],
                    'widgets' => [
                        [
                            'id'         => 'customer-orders',
                            'sort'       => 1,
                            'row'        => 1,
                            'column'     => 1,
                            'span'       => 1,
                            'properties' => null,
                        ],
                        [
                            'id'         => 'order-ready',
                            'sort'       => 2,
                            'row'        => 2,
                            'column'     => 1,
                            'span'       => 2,
                            'properties' => null,
                        ],
                    ],
                ],
            ]);

        // Verify user sees their updated layout
        Sanctum::actingAs($this->regularUser);
        $myLayoutResponse = $this->getJson('/api/dashboard-layouts/me');
        $myLayoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dashboard layout retrieved successfully',
                'data'    => [
                    'role_id' => $this->userRole->id,
                    'version' => 1,
                ],
            ]);
    }

    public function test_optimistic_locking_version_conflict_returns_409(): void
    {
        Sanctum::actingAs($this->adminUser);

        // First save creates version 1
        $this->putJson("/api/dashboard-layouts/roles/{$this->userRole->id}", [
            'version' => 0,
            'rows'    => [['row' => 1, 'columns' => 3]],
            'widgets' => [],
        ])->assertStatus(200);

        // Attempting to save with outdated version 0 should fail with 409 Conflict
        $conflictResponse = $this->putJson("/api/dashboard-layouts/roles/{$this->userRole->id}", [
            'version' => 0,
            'rows'    => [['row' => 1, 'columns' => 3]],
            'widgets' => [],
        ]);

        $conflictResponse->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Dashboard layout has been updated by another user',
                'data'    => [
                    'current_version' => 1,
                ],
            ]);
    }

    public function test_validation_rejects_non_sequential_rows(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/dashboard-layouts/roles/{$this->userRole->id}", [
            'version' => 0,
            'rows'    => [
                ['row' => 1, 'columns' => 3],
                ['row' => 3, 'columns' => 2], // Gap: missing row 2
            ],
            'widgets' => [],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Dashboard layout validation failed',
            ])
            ->assertJsonValidationErrors(['rows']);
    }

    public function test_validation_rejects_widget_span_exceeding_columns(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Row 2 has 2 columns, but widget column 2 with span 2 requires column 3 (2 + 2 - 1 = 3 > 2)
        $response = $this->putJson("/api/dashboard-layouts/roles/{$this->userRole->id}", [
            'version' => 0,
            'rows'    => [
                ['row' => 1, 'columns' => 3],
                ['row' => 2, 'columns' => 2],
            ],
            'widgets' => [
                [
                    'id'     => 'order-ready',
                    'sort'   => 1,
                    'row'    => 2,
                    'column' => 2,
                    'span'   => 2,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Dashboard layout validation failed',
                'errors'  => [
                    'widgets.0.span' => [
                        'Widget span exceeds the number of columns available in row 2.',
                    ],
                ],
            ]);
    }

    public function test_validation_rejects_duplicate_widget_ids(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/dashboard-layouts/roles/{$this->userRole->id}", [
            'version' => 0,
            'rows'    => [
                ['row' => 1, 'columns' => 3],
            ],
            'widgets' => [
                [
                    'id'     => 'customer-orders',
                    'sort'   => 1,
                    'row'    => 1,
                    'column' => 1,
                    'span'   => 1,
                ],
                [
                    'id'     => 'customer-orders', // Duplicated ID
                    'sort'   => 2,
                    'row'    => 1,
                    'column' => 2,
                    'span'   => 1,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['widgets.1.id']);
    }

    public function test_admin_can_reset_layout(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Save layout first
        $this->putJson("/api/dashboard-layouts/roles/{$this->userRole->id}", [
            'version' => 0,
            'rows'    => [['row' => 1, 'columns' => 3]],
            'widgets' => [],
        ])->assertStatus(200);

        // Reset
        $resetResponse = $this->deleteJson("/api/dashboard-layouts/roles/{$this->userRole->id}");
        $resetResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dashboard layout reset successfully',
                'data'    => null,
            ]);

        // Check it's reset to unconfigured
        $getResponse = $this->getJson("/api/dashboard-layouts/roles/{$this->userRole->id}");
        $getResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dashboard layout has not been configured',
                'data'    => [
                    'version' => 0,
                ],
            ]);
    }
}
