<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\HrisDepartment;
use App\Models\HrisPosition;
use App\Models\HrisEmployee;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class EmployeeCreateApiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected HrisDepartment $dept;
    protected HrisPosition $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'administrator'], ['is_active' => true]);
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'name'    => 'HR Admin',
            'email'   => 'hr.admin@susantimegah.com',
        ]);

        $this->dept = HrisDepartment::firstOrCreate(
            ['dept_code' => 'IT'],
            ['dept_name' => 'Information Technology', 'sap_ocr_code3' => 'DEPT_IT']
        );

        $this->pos = HrisPosition::firstOrCreate(
            ['position_code' => 'DEV'],
            ['position_name' => 'Software Developer', 'level_grade' => 2]
        );
    }

    public function test_can_create_employee_with_minimal_input(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/task-management/master/employees', [
                'name' => 'Ahmad Fauzi',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.full_name', 'Ahmad Fauzi');
        $this->assertNotEmpty($response->json('data.nik'));
        $this->assertNotEmpty($response->json('data.email_office'));

        $this->assertDatabaseHas('hris_employees', [
            'full_name' => 'Ahmad Fauzi',
        ]);
    }

    public function test_can_create_employee_with_full_details_and_dept_code(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/task-management/employees', [
                'full_name'         => 'Siti Rahmawati',
                'nik'               => 'EMP-2026-9901',
                'email_office'      => 'siti.rahmawati@susantimegah.com',
                'phone_number'      => '081234567890',
                'dept_code'         => 'IT',
                'position_id'       => $this->pos->id,
                'employment_status' => 'PERMANENT',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.nik', 'EMP-2026-9901');
        $response->assertJsonPath('data.email_office', 'siti.rahmawati@susantimegah.com');
        $response->assertJsonPath('data.department.dept_code', 'IT');
        $response->assertJsonPath('data.position.position_code', 'DEV');

        $empId = $response->json('data.id');

        // Test get single employee
        $detailRes = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/task-management/employees/{$empId}");

        $detailRes->assertStatus(200);
        $detailRes->assertJsonPath('data.full_name', 'Siti Rahmawati');
    }

    public function test_validation_fails_when_name_missing(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/task-management/master/employees', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['full_name']);
    }

    public function test_validation_fails_on_duplicate_nik(): void
    {
        HrisEmployee::firstOrCreate(
            ['nik' => 'EMP-DUP-01'],
            [
                'full_name'     => 'Existing Employee',
                'email_office'  => 'existing@susantimegah.com',
                'department_id' => $this->dept->id,
                'position_id'   => $this->pos->id,
            ]
        );

        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/task-management/master/employees', [
                'full_name' => 'New Duplicate Person',
                'nik'       => 'EMP-DUP-01',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nik']);
    }
}
