<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\HrisPosition;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PositionApiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'administrator'], ['is_active' => true]);
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'name'    => 'Admin User',
            'email'   => 'admin.position@susantimegah.com',
        ]);
    }

    public function test_get_positions_returns_list_of_positions(): void
    {
        HrisPosition::firstOrCreate(
            ['position_code' => 'TEST_DEV'],
            ['position_name' => 'Test Developer', 'level_grade' => 2]
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/distributor-channel/v1/task-management/master/positions');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_can_create_position_and_get_detail(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/task-management/master/positions', [
                'position_code' => 'TECH_LEAD_' . uniqid(),
                'position_name' => 'Technical Lead Architect',
                'level_grade'   => 4,
                'description'   => 'Leads system architecture and code reviews',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.position_name', 'Technical Lead Architect');

        $posId = $response->json('data.id');

        $detailRes = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/task-management/positions/{$posId}");

        $detailRes->assertStatus(200);
        $detailRes->assertJsonPath('data.position_name', 'Technical Lead Architect');
    }

    public function test_create_position_validation_requires_name(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/task-management/master/positions', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['position_name']);
    }
}
