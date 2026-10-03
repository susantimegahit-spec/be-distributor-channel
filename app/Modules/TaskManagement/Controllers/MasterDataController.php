<?php

namespace App\Modules\TaskManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TmMasterStatus;
use App\Models\TmMasterPriority;
use App\Models\TmMasterTaskType;
use App\Models\TmMasterTag;
use App\Models\HrisDepartment;
use App\Models\HrisEmployee;
use App\Models\HrisPosition;
use App\Models\HrisDivision;
use App\Modules\TaskManagement\Traits\ResolvesEmployeeTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MasterDataController extends Controller
{
    use ResolvesEmployeeTrait;

    public function getStatuses(Request $request): JsonResponse
    {
        $spaceId = $request->query('space_id');
        $query = TmMasterStatus::query();
        if ($spaceId) {
            $query->where(function ($q) use ($spaceId) {
                $q->whereNull('space_id')->orWhere('space_id', $spaceId);
            });
        } else {
            $query->whereNull('space_id');
        }

        $statuses = $query->orderBy('sort_order')->get();
        return response()->json(['status' => 'success', 'data' => $statuses]);
    }

    public function getPriorities(): JsonResponse
    {
        $priorities = TmMasterPriority::orderBy('level_weight')->get();
        return response()->json(['status' => 'success', 'data' => $priorities]);
    }

    public function getTaskTypes(): JsonResponse
    {
        $types = TmMasterTaskType::where('is_active', true)->get();
        return response()->json(['status' => 'success', 'data' => $types]);
    }

    public function getTags(Request $request): JsonResponse
    {
        $spaceId = $request->query('space_id');
        $query = TmMasterTag::query();
        if ($spaceId) {
            $query->where(function ($q) use ($spaceId) {
                $q->whereNull('space_id')->orWhere('space_id', $spaceId);
            });
        }
        $tags = $query->get();
        return response()->json(['status' => 'success', 'data' => $tags]);
    }

    public function getDepartments(): JsonResponse
    {
        $departments = HrisDepartment::where('is_active', true)
            ->with(['divisions' => fn($q) => $q->where('is_active', true)])
            ->get();
        return response()->json(['status' => 'success', 'data' => $departments]);
    }

    public function getEmployees(Request $request): JsonResponse
    {
        $departmentId = $request->query('department_id');
        $query = HrisEmployee::where('is_active', true)
            ->with(['department:id,dept_code,dept_name', 'position:id,position_code,position_name']);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        if ($request->query('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', $search)
                  ->orWhere('nik', 'like', $search)
                  ->orWhere('email_office', 'like', $search);
            });
        }

        $employees = $query->get();
        return response()->json(['status' => 'success', 'data' => $employees]);
    }

    public function getEmployee(int $id): JsonResponse
    {
        $employee = HrisEmployee::with([
            'department:id,dept_code,dept_name',
            'position:id,position_code,position_name',
            'division:id,division_code,division_name',
        ])->find($id);

        if (!$employee) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Employee not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $employee,
        ]);
    }

    public function createEmployee(Request $request): JsonResponse
    {
        // 1. Normalize inputs
        $fullName = trim((string)($request->input('full_name') ?? $request->input('name') ?? $request->input('employee_name') ?? ''));
        $emailOffice = trim((string)($request->input('email_office') ?? $request->input('email') ?? ''));
        $nik = trim((string)($request->input('nik') ?? ''));
        $deptInput = $request->input('department_id') ?? $request->input('dept_code');
        $posInput = $request->input('position_id') ?? $request->input('position_code');

        if (empty($fullName)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation error',
                'errors'  => ['full_name' => ['The full_name or name field is required.']],
            ], 422);
        }

        // Resolve Department
        $deptId = null;
        if (!empty($deptInput)) {
            if (is_numeric($deptInput)) {
                $dept = HrisDepartment::find($deptInput);
                if ($dept) {
                    $deptId = $dept->id;
                }
            } else {
                $dept = HrisDepartment::where('dept_code', strtoupper((string)$deptInput))->first();
                if ($dept) {
                    $deptId = $dept->id;
                }
            }
        }
        if (!$deptId) {
            $defaultDept = HrisDepartment::where('dept_code', 'IT')->first() ?? HrisDepartment::first();
            if (!$defaultDept) {
                $defaultDept = HrisDepartment::create([
                    'dept_code'     => 'IT',
                    'dept_name'     => 'Information Technology',
                    'sap_ocr_code3' => 'DEPT_IT',
                ]);
            }
            $deptId = $defaultDept->id;
        }

        // Resolve Position
        $posId = null;
        if (!empty($posInput)) {
            if (is_numeric($posInput)) {
                $pos = HrisPosition::find($posInput);
                if ($pos) {
                    $posId = $pos->id;
                }
            } else {
                $pos = HrisPosition::where('position_code', strtoupper((string)$posInput))->first();
                if ($pos) {
                    $posId = $pos->id;
                }
            }
        }
        if (!$posId) {
            $defaultPos = HrisPosition::where('position_code', 'STAFF')->first() ?? HrisPosition::first();
            if (!$defaultPos) {
                $defaultPos = HrisPosition::create([
                    'position_code' => 'STAFF',
                    'position_name' => 'Staff',
                    'level_grade'   => 1,
                ]);
            }
            $posId = $defaultPos->id;
        }

        // Resolve NIK
        if (empty($nik)) {
            $baseNik = 'EMP-' . date('Ymd');
            $uniqueNik = $baseNik . '-' . strtoupper(Str::random(4));
            while (HrisEmployee::where('nik', $uniqueNik)->exists()) {
                $uniqueNik = $baseNik . '-' . strtoupper(Str::random(4));
            }
            $nik = $uniqueNik;
        } else {
            if (HrisEmployee::where('nik', $nik)->exists()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Validation error',
                    'errors'  => ['nik' => ['The NIK has already been taken.']],
                ], 422);
            }
        }

        // Resolve Office Email
        if (empty($emailOffice)) {
            $slugName = Str::slug($fullName, '.');
            $uniqueEmail = $slugName . '@susantimegah.com';
            $counter = 1;
            while (HrisEmployee::where('email_office', $uniqueEmail)->exists()) {
                $uniqueEmail = $slugName . $counter . '@susantimegah.com';
                $counter++;
            }
            $emailOffice = $uniqueEmail;
        } else {
            if (HrisEmployee::where('email_office', $emailOffice)->exists()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Validation error',
                    'errors'  => ['email_office' => ['The office email has already been taken.']],
                ], 422);
            }
        }

        try {
            $employee = HrisEmployee::create([
                'nik'                  => $nik,
                'user_id'              => $request->input('user_id'),
                'full_name'            => $fullName,
                'nickname'             => $request->input('nickname') ?: explode(' ', $fullName)[0],
                'email_office'         => $emailOffice,
                'phone_number'         => $request->input('phone_number'),
                'telegram_chat_id'     => $request->input('telegram_chat_id'),
                'department_id'        => $deptId,
                'division_id'          => $request->input('division_id'),
                'position_id'          => $posId,
                'direct_supervisor_id' => $request->input('direct_supervisor_id'),
                'employment_status'    => $request->input('employment_status', 'PERMANENT'),
                'join_date'            => $request->input('join_date') ?: now()->toDateString(),
                'resign_date'          => $request->input('resign_date'),
                'is_active'            => $request->boolean('is_active', true),
                'avatar_url'           => $request->input('avatar_url'),
            ]);

            // Sync to hris_employees_2 if present in PostgreSQL database
            $this->syncToHrisEmployees2($employee->id);

            $employee->load([
                'department:id,dept_code,dept_name',
                'position:id,position_code,position_name',
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Employee created successfully',
                'data'    => $employee,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create employee: ' . $e->getMessage(),
            ], 500);
        }
    }
}
