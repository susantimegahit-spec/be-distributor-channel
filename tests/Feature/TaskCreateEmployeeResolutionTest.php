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
use App\Models\TmList;
use App\Models\TmMasterStatus;
use App\Models\TmMasterPriority;
use App\Models\TmMasterTaskType;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TaskCreateEmployeeResolutionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected TmSpace $space;
    protected TmList $list;
    protected TmMasterStatus $status;
    protected TmMasterPriority $priority;
    protected TmMasterTaskType $taskType;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'administrator'], ['is_active' => true]);
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'name'    => 'New Corporate User',
            'email'   => 'new.corp.user@susantimegah.com',
        ]);

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

        $this->list = TmList::firstOrCreate(['space_id' => 'IT', 'list_name' => 'General Tasks'], [
            'workspace_id'       => $workspace->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $this->status = TmMasterStatus::firstOrCreate(['status_name' => 'To Do'], [
            'status_category' => 'TO_DO',
            'color_hex'       => '#94A3B8',
            'is_default'      => true,
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

    /**
     * Test creating a task when user has no pre-existing HrisEmployee record.
     * The system should auto-resolve / auto-create an employee record and succeed without FK violation.
     */
    public function test_create_task_auto_resolves_employee_for_user(): void
    {
        // Ensure user does not have an employee record yet
        HrisEmployee::where('user_id', $this->user->id)->delete();

        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/task-management/tasks', [
                'space_id'     => 'IT',
                'list_id'      => $this->list->id,
                'title'        => 'Provisioning VPS 8 Core / 24 GB / 300 GB / S3 100 GB',
                'status_id'    => $this->status->id,
                'priority_id'  => $this->priority->id,
                'task_type_id' => $this->taskType->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.title', 'Provisioning VPS 8 Core / 24 GB / 300 GB / S3 100 GB');

        // Check that an employee was auto-created for this user
        $createdEmployee = HrisEmployee::where('user_id', $this->user->id)->first();
        $this->assertNotNull($createdEmployee);
        $this->assertEquals($createdEmployee->id, $response->json('data.created_by_employee_id'));
    }

    /**
     * Test that if legacy hris_employees_2 table exists, employee record is automatically synced.
     */
    public function test_create_task_syncs_to_hris_employees_2_when_table_exists(): void
    {
        // Dynamically create temporary hris_employees_2 table in sqlite
        \Illuminate\Support\Facades\Schema::dropIfExists('hris_employees_2');
        \Illuminate\Support\Facades\DB::statement("CREATE TABLE hris_employees_2 AS SELECT * FROM hris_employees WHERE 1=0");

        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/task-management/tasks', [
                'space_id'     => 'IT',
                'list_id'      => $this->list->id,
                'title'        => 'Another task with hris_employees_2 existing',
                'status_id'    => $this->status->id,
                'priority_id'  => $this->priority->id,
                'task_type_id' => $this->taskType->id,
            ]);

        $response->assertStatus(201);
        $empId = $response->json('data.created_by_employee_id');

        // Verify that hris_employees_2 contains this employee ID
        $this->assertTrue(\Illuminate\Support\Facades\DB::table('hris_employees_2')->where('id', $empId)->exists());

        \Illuminate\Support\Facades\Schema::dropIfExists('hris_employees_2');
    }
}
