<?php

namespace App\Modules\TaskManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TmMasterStatus;
use App\Models\TmMasterPriority;
use App\Models\TmMasterTaskType;
use App\Models\TmMasterTag;
use App\Models\HrisDepartment;
use App\Models\HrisEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
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
}
