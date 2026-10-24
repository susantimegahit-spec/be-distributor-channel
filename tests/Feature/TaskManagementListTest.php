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
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TaskManagementListTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected TmWorkspace $workspace;
    protected TmSpace $space;
    protected TmFolder $folder;
    protected TmMasterStatus $statusTodo;
    protected HrisEmployee $employee;
    protected TmMasterPriority $priorityNormal;
    protected TmMasterTaskType $taskTypeTask;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'administrator'], ['is_active' => true]);
        $this->user = User::factory()->create(['role_id' => $role->id]);

        $dept = HrisDepartment::firstOrCreate(['dept_code' => 'IT'], ['dept_name' => 'Information Technology']);
        $pos = HrisPosition::firstOrCreate(['position_code' => 'DEV'], ['position_name' => 'Developer', 'level_grade' => 1]);
        $this->employee = HrisEmployee::firstOrCreate([
            'nik' => 'EMP-TEST-LIST',
        ], [
            'user_id'       => $this->user->id,
            'full_name'     => 'Test Developer List',
            'email_office'  => 'dev_list@susantimegah.com',
            'department_id' => $dept->id,
            'position_id'   => $pos->id,
        ]);

        $this->workspace = TmWorkspace::firstOrCreate(['workspace_code' => 'SUSANTI-TEST-LIST'], [
            'name'          => 'PT Susanti Megah Perkasa List',
            'owner_user_id' => $this->user->id,
        ]);

        $this->space = TmSpace::firstOrCreate(['id' => 'IT-TEST'], [
            'workspace_id'       => $this->workspace->id,
            'department_id'      => $dept->id,
            'space_name'         => 'IT Department Test',
            'space_slug'         => 'it-department-test',
            'created_by_user_id' => $this->user->id,
        ]);

        $this->folder = TmFolder::create([
            'space_id'           => $this->space->id,
            'folder_name'        => 'Sprint 1 Folder',
            'created_by_user_id' => $this->user->id,
        ]);

        $this->statusTodo = TmMasterStatus::firstOrCreate(['status_name' => 'To Do'], [
            'status_category' => 'TO_DO',
            'color_hex'       => '#d3d3d3',
            'sort_order'      => 1,
        ]);

        $this->priorityNormal = TmMasterPriority::firstOrCreate(['priority_code' => 'NORMAL'], [
            'priority_name'    => 'Normal',
            'color_hex'        => '#00ff00',
            'level_weight'     => 3,
            'target_sla_hours' => 24,
        ]);

        $this->taskTypeTask = TmMasterTaskType::firstOrCreate(['type_code' => 'TASK'], [
            'type_name' => 'Task',
            'icon_name' => 'task',
        ]);
    }

    public function test_delete_list_successfully_deletes_list_and_its_tasks(): void
    {
        $list = TmList::create([
            'space_id'           => $this->space->id,
            'folder_id'          => $this->folder->id,
            'list_name'          => 'Backend Development List',
            'created_by_user_id' => $this->user->id,
        ]);

        $task = TmTask::create([
            'task_code'              => 'TSK-DEL-001',
            'space_id'               => $this->space->id,
            'folder_id'              => $this->folder->id,
            'list_id'                => $list->id,
            'title'                  => 'Task in Deleted List',
            'status_id'              => $this->statusTodo->id,
            'priority_id'            => $this->priorityNormal->id,
            'task_type_id'           => $this->taskTypeTask->id,
            'created_by_employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/distributor-channel/v1/task-management/lists/{$list->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'List and its contents deleted successfully');

        $this->assertNull(TmList::find($list->id));
        $this->assertNull(TmTask::withTrashed()->find($task->id));
    }

    public function test_delete_list_returns_404_when_list_not_found(): void
    {
        $response = $this->actingAs($this->user)->deleteJson('/api/distributor-channel/v1/task-management/lists/999999');

        $response->assertStatus(404)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'List not found');
    }

    public function test_delete_list_works_via_task_management_prefix_alias(): void
    {
        $list = TmList::create([
            'space_id'           => $this->space->id,
            'folder_id'          => $this->folder->id,
            'list_name'          => 'Frontend Development List',
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/distributor-channel/task-management/lists/{$list->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'List and its contents deleted successfully');

        $this->assertNull(TmList::find($list->id));
    }
}
