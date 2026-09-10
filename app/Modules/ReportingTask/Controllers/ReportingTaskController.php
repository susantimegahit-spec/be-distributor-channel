<?php

namespace App\Modules\ReportingTask\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ReportingTask;
use App\Modules\ReportingTask\Services\ReportingTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportingTaskController extends Controller
{
    protected ReportingTaskService $reportingTaskService;

    public function __construct(ReportingTaskService $reportingTaskService)
    {
        $this->reportingTaskService = $reportingTaskService;
    }

    /**
     * Standard success JSON response.
     */
    protected function successResponse($data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'status_code' => $statusCode,
            'message'     => $message,
            'data'        => $data,
        ], $statusCode);
    }

    /**
     * Standard error JSON response.
     */
    protected function errorResponse(string $message = 'Error', $errors = [], int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success'     => false,
            'status_code' => $statusCode,
            'message'     => $message,
            'errors'      => (object) $errors,
        ], $statusCode);
    }

    /**
     * Sync tasks from n8n / ClickUp Webhook (single task or array of tasks).
     */
    public function sync(Request $request): JsonResponse
    {
        $input = $request->all();

        // Check if input is a list of tasks
        if (isset($input['tasks']) && is_array($input['tasks'])) {
            $items = $input['tasks'];
        } elseif (isset($input['data']) && is_array($input['data'])) {
            $items = $input['data'];
        } elseif (array_is_list($input) && !empty($input)) {
            $items = $input;
        } else {
            $items = [$input];
        }

        try {
            $result = $this->reportingTaskService->syncBatch($items);
            return $this->successResponse($result, 'Data task ClickUp berhasil disinkronkan ke database reporting.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menyinkronkan task ClickUp: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of reporting tasks (with filters & pagination / all export).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'quick_filter',
            'space_id',
            'space_name',
            'folder_name',
            'list_name',
            'status',
            'assignee',
            'priority',
            'task_type',
            'timeline',
            'start_date_from',
            'start_date_to',
            'due_date_from',
            'due_date_to',
            'sort_by',
            'sort_order',
            'all',
        ]);

        $perPage = (int) $request->input('per_page', 50);

        try {
            $tasks = $this->reportingTaskService->getTasks($filters, $perPage);
            return $this->successResponse($tasks, 'Daftar reporting tasks berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar tasks: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get full dashboard metrics (KPIs, Chart Distributions, Filter Options) for SPA/FE.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'space_id',
            'space_name',
            'folder_name',
            'list_name',
            'status',
            'assignee',
            'priority',
            'task_type',
            'timeline',
            'start_date_from',
            'start_date_to',
            'due_date_from',
            'due_date_to',
        ]);

        try {
            $data = $this->reportingTaskService->getDashboardMetrics($filters);
            return $this->successResponse($data, 'Data dashboard monitoring ClickUp berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data dashboard: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get distinct filter options for Frontend select dropdowns (all in one).
     */
    public function filterOptions(): JsonResponse
    {
        try {
            $options = $this->reportingTaskService->getFilterOptions();
            return $this->successResponse($options, 'Daftar opsi filter reporting berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil opsi filter: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of unique Spaces for dropdown.
     */
    public function getSpaces(): JsonResponse
    {
        try {
            $spaces = $this->reportingTaskService->getSpacesFilter();
            return $this->successResponse($spaces, 'Daftar space berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar space: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of unique Folders for dropdown (optional ?space_id=).
     */
    public function getFolders(Request $request): JsonResponse
    {
        $spaceId = $request->input('space_id') ?: $request->input('space');
        try {
            $folders = $this->reportingTaskService->getFoldersFilter($spaceId);
            return $this->successResponse($folders, 'Daftar folder berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar folder: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of unique Lists for dropdown (optional ?space_id= & ?folder_name=).
     */
    public function getLists(Request $request): JsonResponse
    {
        $spaceId = $request->input('space_id') ?: $request->input('space');
        $folderName = $request->input('folder_name') ?: $request->input('folder');
        try {
            $lists = $this->reportingTaskService->getListsFilter($spaceId, $folderName);
            return $this->successResponse($lists, 'Daftar list berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar list: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of unique Assignees for dropdown.
     */
    public function getAssignees(): JsonResponse
    {
        try {
            $assignees = $this->reportingTaskService->getAssigneesFilter();
            return $this->successResponse($assignees, 'Daftar assignee berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar assignee: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of unique Statuses for dropdown.
     */
    public function getStatuses(): JsonResponse
    {
        try {
            $statuses = $this->reportingTaskService->getStatusesFilter();
            return $this->successResponse($statuses, 'Daftar status berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar status: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of unique Priorities for dropdown.
     */
    public function getPriorities(): JsonResponse
    {
        try {
            $priorities = $this->reportingTaskService->getPrioritiesFilter();
            return $this->successResponse($priorities, 'Daftar priority berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar priority: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of unique Task Types for dropdown.
     */
    public function getTaskTypes(): JsonResponse
    {
        try {
            $types = $this->reportingTaskService->getTaskTypesFilter();
            return $this->successResponse($types, 'Daftar task type berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar task type: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of unique Timelines / Sprints for dropdown.
     */
    public function getTimelines(): JsonResponse
    {
        try {
            $timelines = $this->reportingTaskService->getTimelinesFilter();
            return $this->successResponse($timelines, 'Daftar timeline/sprint berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar timeline: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get ALL reporting tasks without pagination (Optimized for Apigee & Google Looker Studio).
     */
    public function getAll(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'quick_filter',
            'space_id',
            'space_name',
            'folder_name',
            'list_name',
            'status',
            'assignee',
            'priority',
            'task_type',
            'timeline',
            'start_date_from',
            'start_date_to',
            'due_date_from',
            'due_date_to',
            'sort_by',
            'sort_order',
        ]);

        $filters['all'] = true;

        try {
            $tasks = $this->reportingTaskService->getTasks($filters);
            return $this->successResponse($tasks, 'Seluruh data reporting tasks berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil seluruh data tasks: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get summary metrics for Data Studio / Dashboard.
     */
    public function summary(): JsonResponse
    {
        try {
            $summary = $this->reportingTaskService->getSummary();
            return $this->successResponse($summary, 'Summary reporting tasks berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil summary: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get single task detail.
     */
    public function show(string $id): JsonResponse
    {
        $task = ReportingTask::where('id', is_numeric($id) ? (int)$id : 0)
            ->orWhere('task_id', $id)
            ->first();

        if (!$task) {
            return $this->errorResponse('Task tidak ditemukan.', [], 404);
        }

        return $this->successResponse($task, 'Detail reporting task berhasil diambil.');
    }

    /**
     * Delete a reporting task.
     */
    public function destroy(string $id): JsonResponse
    {
        $task = ReportingTask::where('id', is_numeric($id) ? (int)$id : 0)
            ->orWhere('task_id', $id)
            ->first();

        if (!$task) {
            return $this->errorResponse('Task tidak ditemukan.', [], 404);
        }

        $task->delete();

        return $this->successResponse(null, 'Task berhasil dihapus dari database reporting.');
    }
}
