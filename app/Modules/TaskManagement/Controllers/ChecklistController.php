<?php

namespace App\Modules\TaskManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Services\ChecklistService;
use App\Models\HrisEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class ChecklistController extends Controller
{
    protected ChecklistService $checklistService;

    public function __construct(ChecklistService $checklistService)
    {
        $this->checklistService = $checklistService;
    }

    protected function getEmployeeId(Request $request): int
    {
        $user = $request->user();
        if ($user) {
            $employee = HrisEmployee::where('user_id', $user->id)->first();
            if ($employee) {
                return $employee->id;
            }
        }
        $first = HrisEmployee::first();
        return $first ? $first->id : 1;
    }

    public function storeChecklist(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_id'         => 'required|integer',
            'checklist_title' => 'required|string|max:150',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $checklist = $this->checklistService->createChecklist(
            $request->input('task_id'),
            $request->input('checklist_title')
        );

        return response()->json(['status' => 'success', 'data' => $checklist], 201);
    }

    public function storeItem(Request $request, int $checklistId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'item_text'            => 'required|string|max:255',
            'assignee_employee_id' => 'nullable|integer',
            'due_date'             => 'nullable|date',
            'sort_order'           => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $item = $this->checklistService->addItem($checklistId, $validator->validated());
        return response()->json(['status' => 'success', 'data' => $item], 201);
    }

    public function toggleItem(Request $request, int $itemId): JsonResponse
    {
        $employeeId = $this->getEmployeeId($request);
        $item = $this->checklistService->toggleItem($itemId, $employeeId);
        return response()->json(['status' => 'success', 'data' => $item]);
    }

    public function destroyItem(int $itemId): JsonResponse
    {
        $this->checklistService->deleteItem($itemId);
        return response()->json(['status' => 'success', 'message' => 'Checklist item deleted']);
    }

    public function destroyChecklist(int $checklistId): JsonResponse
    {
        $this->checklistService->deleteChecklist($checklistId);
        return response()->json(['status' => 'success', 'message' => 'Checklist deleted']);
    }
}
