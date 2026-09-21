<?php

namespace App\Modules\TaskManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Services\TaskService;
use App\Models\HrisEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class TaskController extends Controller
{
    protected TaskService $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
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

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'space_id', 'folder_id', 'list_id', 'parent_task_id',
            'include_subtasks', 'status_id', 'status_category',
            'priority_id', 'task_type_id', 'assignee_id',
            'due_date_from', 'due_date_to', 'search',
            'sort_by', 'sort_order'
        ]);

        $perPage = (int)$request->input('per_page', 15);
        $tasks = $this->taskService->listTasks($filters, $perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tasks retrieved successfully',
            'data'    => $tasks,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'space_id'        => 'required|integer',
            'list_id'         => 'required|integer',
            'title'           => 'required|string|max:255',
            'folder_id'       => 'nullable|integer',
            'parent_task_id'  => 'nullable|integer',
            'description'     => 'nullable|string',
            'status_id'       => 'nullable|integer',
            'priority_id'     => 'nullable|integer',
            'task_type_id'    => 'nullable|integer',
            'start_date'      => 'nullable|date',
            'due_date'        => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'assignee_ids'    => 'nullable|array',
            'assignee_ids.*'  => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $employeeId = $this->getEmployeeId($request);
            $task = $this->taskService->createTask($validator->validated(), $employeeId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Task created successfully',
                'data'    => $task,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create task: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $task = $this->taskService->getTaskDetail($id);
        if (!$task) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Task not found',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Task detail retrieved successfully',
            'data'    => $task,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'               => 'sometimes|required|string|max:255',
            'description'         => 'nullable|string',
            'folder_id'           => 'nullable|integer',
            'list_id'             => 'nullable|integer',
            'status_id'           => 'nullable|integer',
            'priority_id'         => 'nullable|integer',
            'task_type_id'        => 'nullable|integer',
            'start_date'          => 'nullable|date',
            'due_date'            => 'nullable|date',
            'estimated_hours'     => 'nullable|numeric|min:0',
            'progress_percentage' => 'nullable|integer|min:0|max:100',
            'is_milestone'        => 'nullable|boolean',
            'assignee_ids'        => 'nullable|array',
            'assignee_ids.*'      => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $employeeId = $this->getEmployeeId($request);
            $task = $this->taskService->updateTask($id, $validator->validated(), $employeeId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Task updated successfully',
                'data'    => $task,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update task: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $employeeId = $this->getEmployeeId($request);
            $task = $this->taskService->updateStatus($id, $request->input('status_id'), $employeeId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Task status updated successfully',
                'data'    => $task,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update task status: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $employeeId = $this->getEmployeeId($request);
            $this->taskService->deleteTask($id, $employeeId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Task deleted successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete task: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function metrics(Request $request): JsonResponse
    {
        $spaceId = $request->query('space_id') ? (int)$request->query('space_id') : null;
        $metrics = $this->taskService->getSummaryMetrics($spaceId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Task metrics retrieved successfully',
            'data'    => $metrics,
        ]);
    }
}
