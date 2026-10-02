<?php

namespace App\Modules\TaskManagement\Traits;

use App\Models\HrisDepartment;
use App\Models\HrisEmployee;
use App\Models\HrisPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait ResolvesEmployeeTrait
{
    /**
     * Resolve the employee ID for the currently authenticated user.
     * Auto-creates or links an HrisEmployee record if one does not exist.
     */
    protected function getEmployeeId(Request $request): int
    {
        $resolvedId = $this->resolveEmployeeId($request);
        $this->syncToHrisEmployees2($resolvedId);
        return $resolvedId;
    }

    protected function resolveEmployeeId(Request $request): int
    {
        $user = $request->user();

        if ($user) {
            // 1. Try finding by user_id
            $employee = HrisEmployee::where('user_id', $user->id)->first();
            if ($employee) {
                return $employee->id;
            }

            // 2. Try finding by office/login email
            if (!empty($user->email)) {
                $employee = HrisEmployee::where('email_office', $user->email)->first();
                if ($employee) {
                    $employee->update(['user_id' => $user->id]);
                    return $employee->id;
                }
            }

            // 3. Auto-create an employee record for this user
            try {
                $dept = HrisDepartment::where('dept_code', 'IT')->first() ?? HrisDepartment::first();
                if (!$dept) {
                    $dept = HrisDepartment::create([
                        'dept_code'     => 'IT',
                        'dept_name'     => 'Information Technology',
                        'sap_ocr_code3' => 'DEPT_IT',
                    ]);
                }

                $pos = HrisPosition::where('position_code', 'STAFF')->first() ?? HrisPosition::first();
                if (!$pos) {
                    $pos = HrisPosition::create([
                        'position_code' => 'STAFF',
                        'position_name' => 'Staff',
                        'level_grade'   => 1,
                    ]);
                }

                $nik = 'EMP-' . str_pad((string)$user->id, 4, '0', STR_PAD_LEFT);
                if (HrisEmployee::where('nik', $nik)->exists()) {
                    $nik = 'EMP-' . $user->id . '-' . substr(uniqid(), -4);
                }

                $newEmp = HrisEmployee::create([
                    'nik'               => $nik,
                    'user_id'           => $user->id,
                    'full_name'         => $user->name ?: ($user->username ?: 'Employee ' . $user->id),
                    'nickname'          => $user->username ?: $user->name,
                    'email_office'      => $user->email ?: ('emp' . $user->id . '@susantimegah.com'),
                    'department_id'     => $dept->id,
                    'position_id'       => $pos->id,
                    'employment_status' => 'PERMANENT',
                    'is_active'         => true,
                ]);

                return $newEmp->id;
            } catch (\Throwable $e) {
                Log::warning("Failed to auto-create HrisEmployee for user #{$user->id}: " . $e->getMessage());
            }
        }

        // 4. Fallback: retrieve any existing employee
        $first = HrisEmployee::first();
        if ($first) {
            return $first->id;
        }

        // 5. If table is empty, create a system fallback employee
        try {
            $dept = HrisDepartment::firstOrCreate(
                ['dept_code' => 'IT'],
                ['dept_name' => 'Information Technology', 'sap_ocr_code3' => 'DEPT_IT']
            );
            $pos = HrisPosition::firstOrCreate(
                ['position_code' => 'STAFF'],
                ['position_name' => 'Staff', 'level_grade' => 1]
            );

            $fallbackEmp = HrisEmployee::create([
                'nik'               => 'EMP-0001',
                'user_id'           => $user ? $user->id : null,
                'full_name'         => $user ? ($user->name ?: 'Administrator') : 'System Admin',
                'email_office'      => $user && $user->email ? $user->email : 'admin@susantimegah.com',
                'department_id'     => $dept->id,
                'position_id'       => $pos->id,
                'employment_status' => 'PERMANENT',
                'is_active'         => true,
            ]);

            return $fallbackEmp->id;
        } catch (\Throwable $e) {
            Log::warning("Failed to create fallback HrisEmployee: " . $e->getMessage());
        }

        return 1;
    }

    /**
     * If production has a legacy or duplicated hris_employees_2 table referenced by old constraints,
     * sync the resolved employee record to prevent foreign key errors.
     */
    protected function syncToHrisEmployees2(int $employeeId): void
    {
        try {
            $conn = config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_corporate';

            $exists = false;
            try {
                $exists = \Illuminate\Support\Facades\Schema::connection($conn)->hasTable('hris_employees_2');
            } catch (\Throwable $e) {
                $exists = false;
            }

            if ($exists) {
                $hasEmp = \Illuminate\Support\Facades\DB::connection($conn)->table('hris_employees_2')->where('id', $employeeId)->exists();
                if (!$hasEmp) {
                    $source = \Illuminate\Support\Facades\DB::connection($conn)->table('hris_employees')->where('id', $employeeId)->first();
                    if ($source) {
                        \Illuminate\Support\Facades\DB::connection($conn)->table('hris_employees_2')->insertOrIgnore((array) $source);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to sync employee #{$employeeId} to hris_employees_2: " . $e->getMessage());
        }
    }
}
