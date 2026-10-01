<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\HrisEmployee;
use App\Models\HrisDepartment;
use App\Models\HrisPosition;
use App\Models\TmWorkspace;
use App\Models\TmSpace;
use App\Models\TmFolder;
use App\Models\TmList;
use App\Models\TmTask;
use App\Models\TmMasterStatus;
use App\Models\TmMasterPriority;
use App\Models\TmMasterTaskType;
use App\Models\TmTaskTimeTracking;
use App\Models\TmTaskActivityLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TaskManagementDashboardSummaryTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected TmWorkspace $workspace;
    protected TmSpace $space;
    protected TmList $list;
    protected TmMasterStatus $statusTodo;
    protected TmMasterStatus $statusDone;
    protected TmMasterPriority $priorityUrgent;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'administrator'], ['is_active' => true]);
        $this->user = User::factory()->create(['role_id' => $role->id]);

        $dept = HrisDepartment::firstOrCreate(['dept_code' => 'IT'], ['dept_name' => 'Information Technology']);
        $pos = HrisPosition::firstOrCreate(['position_code' => 'DEV'], ['position_name' => 'Developer', 'level_grade' => 1]);
        $employee = HrisEmployee::firstOrCreate([
            'nik' => 'EMP-001',
        ], [
            'user_id'       => $this->user->id,
            'full_name'     => 'Test Developer',
            'email_office'  => 'dev@susantimegah.com',
            'department_id' => $dept->id,
            'position_id'   => $pos->id,
        ]);

        $this->workspace = TmWorkspace::firstOrCreate(['workspace_code' => 'SUSANTI-TEST'], [
            'name'          => 'PT Susanti Megah Perkasa',
            'owner_user_id' => $this->user->id,
        ]);

        $this->space = TmSpace::updateOrCreate(['id' => 'IT'], [
            'workspace_id'       => $this->workspace->id,
            'department_id'      => 'IT',
            'space_name'         => 'Information Technology',
            'space_slug'         => 'it-department',
            'created_by_user_id' => $this->user->id,
        ]);

        $folder = TmFolder::firstOrCreate([
            'space_id'    => $this->space->id,
            'folder_name' => 'Sprint 1',
        ], ['created_by_user_id' => $this->user->id]);

        $this->list = TmList::firstOrCreate([
            'space_id'  => $this->space->id,
            'folder_id' => $folder->id,
            'list_name' => 'Development',
        ], ['created_by_user_id' => $this->user->id]);

        $this->statusTodo = TmMasterStatus::firstOrCreate(['status_name' => 'To Do'], [
            'status_category' => 'TO_DO',
            'color_hex'       => '#94A3B8',
            'sort_order'      => 1,
        ]);

        $this->statusDone = TmMasterStatus::firstOrCreate(['status_name' => 'Done'], [
            'status_category' => 'DONE',
            'color_hex'       => '#10B981',
            'sort_order'      => 4,
        ]);

        $this->priorityUrgent = TmMasterPriority::firstOrCreate(['priority_code' => 'URGENT'], [
            'priority_name'    => 'Urgent',
            'color_hex'        => '#EF4444',
            'level_weight'     => 1,
            'target_sla_hours' => 4,
        ]);

        $type = TmMasterTaskType::firstOrCreate(['type_code' => 'TASK'], ['type_name' => 'Task']);

        // Create 2 test tasks
        $task1 = TmTask::create([
            'task_code'              => 'TSK-IT-2026-00001',
            'space_id'               => $this->space->id,
            'list_id'                => $this->list->id,
            'title'                  => 'Fix Login Bug',
            'status_id'              => $this->statusTodo->id,
            'priority_id'            => $this->priorityUrgent->id,
            'task_type_id'           => $type->id,
            'due_date'               => now()->subDays(1), // overdue
            'created_by_employee_id' => $employee->id,
        ]);
        $task1->assignedEmployees()->attach($employee->id, ['assigned_by_employee_id' => $employee->id]);

        $task2 = TmTask::create([
            'task_code'              => 'TSK-IT-2026-00002',
            'space_id'               => $this->space->id,
            'list_id'                => $this->list->id,
            'title'                  => 'Build Dashboard API',
            'status_id'              => $this->statusDone->id,
            'priority_id'            => $this->priorityUrgent->id,
            'task_type_id'           => $type->id,
            'due_date'               => now()->addDays(2),
            'completed_at'           => now(),
            'created_by_employee_id' => $employee->id,
        ]);

        // Add time tracking
        TmTaskTimeTracking::create([
            'task_id'          => $task1->id,
            'employee_id'      => $employee->id,
            'start_time'       => now()->subMinutes(90),
            'end_time'         => now(),
            'duration_minutes' => 90,
            'note'             => 'Investigating root cause',
        ]);

        // Add activity log
        TmTaskActivityLog::create([
            'task_id'                  => $task1->id,
            'performed_by_employee_id' => $employee->id,
            'action_type'              => 'TASK_CREATED',
            'field_name'               => 'title',
            'old_value'                => null,
            'new_value'                => 'Fix Login Bug',
        ]);
    }

    public function test_can_retrieve_task_management_dashboard_summary(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/distributor-channel/v1/task-management/dashboard/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'overview' => [
                    'total_tasks',
                    'to_do',
                    'in_progress',
                    'in_review',
                    'done',
                    'cancelled',
                    'overdue',
                    'due_today',
                    'completion_rate',
                    'total_estimated_hours',
                    'total_tracked_hours',
                ],
                'status_distribution',
                'priority_distribution',
                'space_distribution',
                'team_workload',
                'urgent_tasks',
                'recent_activities',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['overview']['total_tasks']);
        $this->assertEquals(1, $data['overview']['to_do']);
        $this->assertEquals(1, $data['overview']['done']);
        $this->assertEquals(1, $data['overview']['overdue']);
        $this->assertEquals(50.0, $data['overview']['completion_rate']);
        $this->assertEquals(1.5, $data['overview']['total_tracked_hours']);

        // Check urgent tasks contains overdue item
        $this->assertNotEmpty($data['urgent_tasks']);
        $this->assertEquals('TSK-IT-2026-00001', $data['urgent_tasks'][0]['task_code']);
        $this->assertTrue($data['urgent_tasks'][0]['is_overdue']);

        // Check recent activities
        $this->assertNotEmpty($data['recent_activities']);
        $this->assertEquals('TASK_CREATED', $data['recent_activities'][0]['action_type']);
    }

    public function test_can_filter_dashboard_summary_by_space_id(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/distributor-channel/v1/task-management/dashboard/summary?space_id=IT');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(2, $data['overview']['total_tasks']);

        // Query with non-existent space_id
        $emptyResponse = $this->actingAs($this->user)
            ->getJson('/api/distributor-channel/v1/task-management/dashboard/summary?space_id=NON_EXISTENT');
        $emptyResponse->assertStatus(200);
        $this->assertEquals(0, $emptyResponse->json('data.overview.total_tasks'));
    }

    public function test_can_access_dashboard_summary_via_non_v1_alias_route(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/distributor-channel/task-management/dashboard/summary');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.overview.total_tasks'));
    }
}
