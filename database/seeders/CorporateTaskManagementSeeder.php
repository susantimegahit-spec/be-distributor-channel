<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HrisDepartment;
use App\Models\HrisPosition;
use App\Models\HrisEmployee;
use App\Models\TmWorkspace;
use App\Models\TmSpace;
use App\Models\TmFolder;
use App\Models\TmList;
use App\Models\TmMasterStatus;
use App\Models\TmMasterPriority;
use App\Models\TmMasterTaskType;
use App\Models\User;

class CorporateTaskManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Master Positions
        $positions = [
            ['position_code' => 'DIR', 'position_name' => 'Direktur', 'level_grade' => 7],
            ['position_code' => 'GM', 'position_name' => 'General Manager', 'level_grade' => 6],
            ['position_code' => 'MGR', 'position_name' => 'Manager Departemen', 'level_grade' => 5],
            ['position_code' => 'AMGR', 'position_name' => 'Assistant Manager', 'level_grade' => 4],
            ['position_code' => 'SPV', 'position_name' => 'Supervisor', 'level_grade' => 3],
            ['position_code' => 'SR_STAFF', 'position_name' => 'Senior Staff', 'level_grade' => 2],
            ['position_code' => 'STAFF', 'position_name' => 'Staff / Pelaksana', 'level_grade' => 1],
        ];

        foreach ($positions as $pos) {
            HrisPosition::firstOrCreate(['position_code' => $pos['position_code']], $pos);
        }

        // 2. Master Departments
        $departments = [
            ['dept_code' => 'IT', 'dept_name' => 'Information Technology', 'sap_ocr_code3' => 'DEPT_IT'],
            ['dept_code' => 'HRD', 'dept_name' => 'Human Resources & General Affairs', 'sap_ocr_code3' => 'DEPT_HRD'],
            ['dept_code' => 'FIN', 'dept_name' => 'Finance & Accounting', 'sap_ocr_code3' => 'DEPT_FIN'],
            ['dept_code' => 'SCM', 'dept_name' => 'Supply Chain & Logistics', 'sap_ocr_code3' => 'DEPT_SCM'],
            ['dept_code' => 'PROD', 'dept_name' => 'Production & Plant Operations', 'sap_ocr_code3' => 'DEPT_PROD'],
            ['dept_code' => 'SLS', 'dept_name' => 'Sales & Commercial', 'sap_ocr_code3' => 'DEPT_SLS'],
        ];

        $deptModels = [];
        foreach ($departments as $dept) {
            $deptModels[$dept['dept_code']] = HrisDepartment::firstOrCreate(['dept_code' => $dept['dept_code']], $dept);
        }

        // 3. Fallback/Admin user mapping to Employee
        $adminUser = User::first();
        $adminUserId = $adminUser ? $adminUser->id : 1;
        $mgrPos = HrisPosition::where('position_code', 'MGR')->first();
        $staffPos = HrisPosition::where('position_code', 'STAFF')->first();

        $mgrEmp = HrisEmployee::firstOrCreate([
            'nik' => 'EMP-0001',
        ], [
            'user_id'           => $adminUserId,
            'full_name'         => 'Manager IT & Digital PT Susanti',
            'nickname'          => 'IT Head',
            'email_office'      => 'it.head@susantimegah.com',
            'phone_number'      => '081234567890',
            'department_id'     => $deptModels['IT']->id,
            'position_id'       => $mgrPos->id,
            'employment_status' => 'PERMANENT',
            'join_date'         => '2020-01-01',
            'is_active'         => true,
        ]);

        $staffEmp = HrisEmployee::firstOrCreate([
            'nik' => 'EMP-0002',
        ], [
            'full_name'            => 'Software Developer In-House',
            'nickname'             => 'Developer',
            'email_office'         => 'developer@susantimegah.com',
            'department_id'        => $deptModels['IT']->id,
            'position_id'          => $staffPos->id,
            'direct_supervisor_id' => $mgrEmp->id,
            'employment_status'    => 'PERMANENT',
            'join_date'            => '2022-06-01',
            'is_active'            => true,
        ]);

        // 4. Master Task Statuses (Global Default)
        $statuses = [
            ['status_name' => 'To Do', 'status_category' => 'TO_DO', 'color_hex' => '#94A3B8', 'sort_order' => 1, 'is_default' => true, 'is_closed_status' => false],
            ['status_name' => 'In Progress', 'status_category' => 'IN_PROGRESS', 'color_hex' => '#3B82F6', 'sort_order' => 2, 'is_default' => false, 'is_closed_status' => false],
            ['status_name' => 'In Review', 'status_category' => 'REVIEW', 'color_hex' => '#F59E0B', 'sort_order' => 3, 'is_default' => false, 'is_closed_status' => false],
            ['status_name' => 'Done', 'status_category' => 'DONE', 'color_hex' => '#10B981', 'sort_order' => 4, 'is_default' => false, 'is_closed_status' => true],
            ['status_name' => 'Cancelled', 'status_category' => 'CANCELLED', 'color_hex' => '#EF4444', 'sort_order' => 5, 'is_default' => false, 'is_closed_status' => true],
        ];

        foreach ($statuses as $st) {
            TmMasterStatus::firstOrCreate([
                'space_id'    => null,
                'status_name' => $st['status_name'],
            ], $st);
        }

        // 5. Master Priorities
        $priorities = [
            ['priority_code' => 'URGENT', 'priority_name' => 'Urgent', 'color_hex' => '#EF4444', 'level_weight' => 1, 'target_sla_hours' => 4],
            ['priority_code' => 'HIGH', 'priority_name' => 'High', 'color_hex' => '#F97316', 'level_weight' => 2, 'target_sla_hours' => 24],
            ['priority_code' => 'NORMAL', 'priority_name' => 'Normal', 'color_hex' => '#3B82F6', 'level_weight' => 3, 'target_sla_hours' => 72],
            ['priority_code' => 'LOW', 'priority_name' => 'Low', 'color_hex' => '#64748B', 'level_weight' => 4, 'target_sla_hours' => 168],
        ];

        foreach ($priorities as $pr) {
            TmMasterPriority::firstOrCreate(['priority_code' => $pr['priority_code']], $pr);
        }

        // 6. Master Task Types
        $taskTypes = [
            ['type_code' => 'TASK', 'type_name' => 'Task', 'icon_name' => 'check-square', 'color_hex' => '#3B82F6'],
            ['type_code' => 'BUG', 'type_name' => 'Bug', 'icon_name' => 'alert-circle', 'color_hex' => '#EF4444'],
            ['type_code' => 'FEATURE', 'type_name' => 'Feature', 'icon_name' => 'zap', 'color_hex' => '#8B5CF6'],
            ['type_code' => 'OPERATIONAL_ROUTINE', 'type_name' => 'Operational Routine', 'icon_name' => 'clock', 'color_hex' => '#10B981'],
            ['type_code' => 'HR_REQUEST', 'type_name' => 'HR Request', 'icon_name' => 'user', 'color_hex' => '#EC4899'],
            ['type_code' => 'MILESTONE', 'type_name' => 'Milestone', 'icon_name' => 'flag', 'color_hex' => '#F59E0B'],
            ['type_code' => 'APPROVAL', 'type_name' => 'Approval', 'icon_name' => 'check-circle', 'color_hex' => '#06B6D4'],
        ];

        foreach ($taskTypes as $tt) {
            TmMasterTaskType::firstOrCreate(['type_code' => $tt['type_code']], $tt);
        }

        // 7. Workspace & Spaces
        $workspace = TmWorkspace::firstOrCreate([
            'workspace_code' => 'SUSANTI-CORP',
        ], [
            'name'          => 'PT Susanti Megah Perkasa',
            'description'   => 'Ruang kerja korporat holding task management PT Susanti',
            'owner_user_id' => $adminUserId,
            'is_active'     => true,
        ]);

        $spaceConfigs = [
            ['name' => 'Information Technology', 'slug' => 'it-department', 'dept' => 'IT', 'color' => '#4F46E5', 'icon' => 'code'],
            ['name' => 'Human Resources & GA', 'slug' => 'hrd-department', 'dept' => 'HRD', 'color' => '#EC4899', 'icon' => 'users'],
            ['name' => 'Finance & Accounting', 'slug' => 'finance-department', 'dept' => 'FIN', 'color' => '#10B981', 'icon' => 'dollar-sign'],
            ['name' => 'Supply Chain & Logistics', 'slug' => 'supply-chain', 'dept' => 'SCM', 'color' => '#F59E0B', 'icon' => 'truck'],
        ];

        foreach ($spaceConfigs as $sc) {
            $space = TmSpace::firstOrCreate([
                'workspace_id' => $workspace->id,
                'space_slug'   => $sc['slug'],
            ], [
                'department_id'      => $deptModels[$sc['dept']]->id,
                'space_name'         => $sc['name'],
                'color_hex'          => $sc['color'],
                'icon_name'          => $sc['icon'],
                'description'        => 'Space kerja resmi ' . $sc['name'],
                'is_private'         => false,
                'created_by_user_id' => $adminUserId,
            ]);

            // Create initial folder & list for IT space as example
            if ($sc['dept'] === 'IT') {
                $folder = TmFolder::firstOrCreate([
                    'space_id'    => $space->id,
                    'folder_name' => 'Sistem In-House & Otomasi 2026',
                ], [
                    'description'        => 'Proyek digitalisasi sistem mandiri PT Susanti',
                    'color_hex'          => '#4F46E5',
                    'created_by_user_id' => $adminUserId,
                ]);

                TmList::firstOrCreate([
                    'space_id'  => $space->id,
                    'folder_id' => $folder->id,
                    'list_name' => 'Task Management & HRIS Replacement',
                ], [
                    'description'        => 'Backlog implementasi Task Management In-House',
                    'default_view'       => 'LIST',
                    'created_by_user_id' => $adminUserId,
                ]);
            }
        }
    }
}
