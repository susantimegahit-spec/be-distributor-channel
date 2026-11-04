<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\HrisDepartment;
use App\Models\HrisPosition;
use App\Models\HrisEmployee;
use App\Models\TmWorkspace;
use App\Models\TmSpace;
use App\Models\TmList;
use App\Models\TmTask;
use App\Models\TmMasterStatus;
use App\Models\TmMasterPriority;
use App\Models\TmMasterTaskType;
use App\Models\TmTaskAssignee;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TeamViewApiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected HrisEmployee $claudia;
    protected HrisEmployee $budi;
    protected TmSpace $space;
    protected TmList $list;
    protected TmMasterStatus $statusReady;
    protected TmMasterStatus $statusInProgress;
    protected TmMasterStatus $statusReview;
    protected TmMasterStatus $statusDone;
    protected TmMasterPriority $priority;
    protected TmMasterTaskType $taskType;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'administrator'], ['is_active' => true]);
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'name'    => 'Team Lead',
            'email'   => 'team.lead@susantimegah.com',
        ]);

        $dept = HrisDepartment::firstOrCreate(
            ['dept_code' => 'IT'],
            ['dept_name' => 'Information Technology', 'sap_ocr_code3' => 'DEPT_IT']
        );

        $pos = HrisPosition::firstOrCreate(
            ['position_code' => 'STAFF'],
            ['position_name' => 'Staff', 'level_grade' => 1]
        );

        $this->claudia = HrisEmployee::firstOrCreate(
            ['nik' => 'EMP-CLAUDIA'],
            [
                'full_name'     => 'Claudia Maria',
                'nickname'      => 'Claudia',
                'email_office'  => 'claudia.maria@susantimegah.com',
                'department_id' => $dept->id,
                'position_id'   => $pos->id,
                'is_active'     => true,
            ]
        );

        $this->budi = HrisEmployee::firstOrCreate(
            ['nik' => 'EMP-BUDI'],
            [
                'full_name'     => 'Budi Santoso',
                'nickname'      => 'Budi',
                'email_office'  => 'budi.santoso@susantimegah.com',
                'department_id' => $dept->id,
                'position_id'   => $pos->id,
                'is_active'     => true,
            ]
        );

        $workspace = TmWorkspace::firstOrCreate(['workspace_code' => 'TEST-WS'], [
            'name'          => 'Test Workspace',
            'owner_user_id' => $this->user->id,
        ]);

        $this->space = TmSpace::updateOrCreate(['id' => 'IT'], [
            'workspace_id'       => $workspace->id,
            'space_name'         => 'IT Space',
            'space_slug'         => 'it-space',
            'created_by_user_id' => $this->user->id,
            'is_private'         => false,
        ]);

        $this->list = TmList::firstOrCreate(['space_id' => 'IT', 'list_name' => 'Development Tasks'], [
            'workspace_id'       => $workspace->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $this->statusReady = TmMasterStatus::firstOrCreate(['status_name' => 'Ready'], [
            'status_category' => 'TO_DO',
            'color_hex'       => '#22C55E',
            'sort_order'      => 1,
            'is_default'      => true,
        ]);

        $this->statusInProgress = TmMasterStatus::firstOrCreate(['status_name' => 'In Progress'], [
            'status_category' => 'IN_PROGRESS',
            'color_hex'       => '#D946EF',
            'sort_order'      => 2,
        ]);

        $this->statusReview = TmMasterStatus::firstOrCreate(['status_name' => 'Review'], [
            'status_category' => 'REVIEW',
            'color_hex'       => '#3B82F6',
            'sort_order'      => 3,
        ]);

        $this->statusDone = TmMasterStatus::firstOrCreate(['status_name' => 'Done'], [
            'status_category' => 'DONE',
            'color_hex'       => '#10B981',
            'sort_order'      => 4,
            'is_closed_status' => true,
        ]);

        $this->priority = TmMasterPriority::firstOrCreate(['priority_code' => 'NORMAL'], [
            'priority_name' => 'Normal',
            'level'         => 3,
            'color_hex'     => '#3B82F6',
        ]);

        $this->taskType = TmMasterTaskType::firstOrCreate(['type_code' => 'TASK'], [
            'type_name' => 'Task',
            'icon'      => 'check-square',
            'color_hex' => '#3B82F6',
        ]);
    }

    protected function createTaskForEmployee(HrisEmployee $emp, TmMasterStatus $status, string $title): TmTask
    {
        $task = TmTask::create([
            'space_id'                => 'IT',
            'list_id'                 => $this->list->id,
            'task_code'               => 'TSK-TEST-' . uniqid(),
            'title'                   => $title,
            'status_id'               => $status->id,
            'priority_id'             => $this->priority->id,
            'task_type_id'            => $this->taskType->id,
            'created_by_employee_id'  => $emp->id,
            'estimated_hours'         => 4.0,
            'progress_percentage'     => $status->status_category === 'DONE' ? 100 : 25,
            'completed_at'            => $status->status_category === 'DONE' ? now() : null,
        ]);

        TmTaskAssignee::create([
            'task_id'                  => $task->id,
            'employee_id'              => $emp->id,
            'assigned_by_employee_id'  => $emp->id,
        ]);

        return $task;
    }

    public function test_get_team_view_returns_employee_monitoring_cards_and_summary(): void
    {
        // Claudia has 3 tasks: 1 Ready, 1 In Progress, 1 Done (2 Not Done, 1 Done -> 33.3% completion rate)
        $this->createTaskForEmployee($this->claudia, $this->statusReady, 'Setup Development Environment');
        $this->createTaskForEmployee($this->claudia, $this->statusInProgress, 'Build Authentication Module');
        $this->createTaskForEmployee($this->claudia, $this->statusDone, 'Requirement Analysis Document');

        // Budi has 1 task Done (100% completion rate)
        $this->createTaskForEmployee($this->budi, $this->statusDone, 'Setup CI/CD Pipeline');

        $response = $this->actingAs($this->user)
            ->getJson('/api/distributor-channel/v1/task-management/dashboard/team-view?space_id=IT');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        // Assert summary
        $response->assertJsonStructure([
            'data' => [
                'summary' => [
                    'total_members',
                    'total_tasks',
                    'total_not_done',
                    'total_done',
                    'overall_completion_rate',
                ],
                'team' => [
                    '*' => [
                        'employee_id',
                        'full_name',
                        'nickname',
                        'metrics' => [
                            'total_tasks',
                            'not_done_tasks',
                            'done_tasks',
                            'completion_rate',
                        ],
                        'status_distribution' => [
                            '*' => [
                                'status_id',
                                'status_name',
                                'status_category',
                                'color_hex',
                                'total_tasks',
                                'percentage',
                                'tasks',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $team = collect($response->json('data.team'));
        $claudiaCard = $team->firstWhere('employee_id', $this->claudia->id);
        $this->assertNotNull($claudiaCard);
        $this->assertEquals('Claudia Maria', $claudiaCard['full_name']);
        $this->assertEquals(3, $claudiaCard['metrics']['total_tasks']);
        $this->assertEquals(2, $claudiaCard['metrics']['not_done_tasks']);
        $this->assertEquals(1, $claudiaCard['metrics']['done_tasks']);
        $this->assertEquals(33.3, $claudiaCard['metrics']['completion_rate']);

        // Check status distribution contains Ready, In Progress, Review, Done
        $readyStatus = collect($claudiaCard['status_distribution'])->firstWhere('status_name', 'Ready');
        $this->assertNotNull($readyStatus);
        $this->assertEquals(1, $readyStatus['total_tasks']);
        $this->assertEquals('#22C55E', $readyStatus['color_hex']);
        $this->assertCount(1, $readyStatus['tasks']);
    }

    public function test_get_employee_team_view_returns_single_employee_card(): void
    {
        $this->createTaskForEmployee($this->claudia, $this->statusInProgress, 'Frontend Team View Component');

        $response = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/task-management/dashboard/team-view/{$this->claudia->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.employee_id', $this->claudia->id);
        $response->assertJsonPath('data.full_name', 'Claudia Maria');
        $this->assertGreaterThanOrEqual(1, $response->json('data.metrics.total_tasks'));
    }

    public function test_reassign_task_to_another_employee(): void
    {
        $task = $this->createTaskForEmployee($this->claudia, $this->statusReady, 'Task to be Reassigned');

        $response = $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/task-management/tasks/{$task->id}/reassign", [
                'from_employee_id' => $this->claudia->id,
                'to_employee_id'   => $this->budi->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('message', 'Task reassigned successfully');

        $assigneeIds = $response->json('data.assignee_ids');
        $this->assertContains($this->budi->id, $assigneeIds);
        $this->assertNotContains($this->claudia->id, $assigneeIds);
    }
}
