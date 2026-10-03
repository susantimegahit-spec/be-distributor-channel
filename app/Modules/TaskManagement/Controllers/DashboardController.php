<?php

namespace App\Modules\TaskManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get summary metrics and distribution data for the task management dashboard.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getSummary(Request $request): JsonResponse
    {
        $filters = $request->only([
            'workspace_id',
            'space_id',
            'department_id',
            'folder_id',
            'list_id',
            'employee_id',
            'assignee_id',
            'start_date_from',
            'due_date_to',
            'date_from',
            'date_to',
            'period',
        ]);

        $summary = $this->dashboardService->getSummary($filters);

        return response()->json([
            'status'  => 'success',
            'message' => 'Dashboard summary retrieved successfully',
            'data'    => $summary,
        ]);
    }

    /**
     * Get Team View data for monitoring tasks, progress, and capacity per employee.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getTeamView(Request $request): JsonResponse
    {
        $filters = $request->only([
            'workspace_id',
            'space_id',
            'department_id',
            'folder_id',
            'list_id',
            'employee_id',
            'assignee_id',
            'search',
            'date_from',
            'date_to',
            'start_date_from',
            'due_date_to',
            'include_tasks',
        ]);

        $teamView = $this->dashboardService->getTeamView($filters);

        return response()->json([
            'status'  => 'success',
            'message' => 'Team view data retrieved successfully',
            'data'    => $teamView,
        ]);
    }

    /**
     * Get Team View card data for a single employee.
     *
     * @param  Request  $request
     * @param  int      $employeeId
     * @return JsonResponse
     */
    public function getEmployeeTeamView(Request $request, int $employeeId): JsonResponse
    {
        $filters = $request->only([
            'workspace_id',
            'space_id',
            'department_id',
            'folder_id',
            'list_id',
            'date_from',
            'date_to',
            'include_tasks',
        ]);

        $employeeCard = $this->dashboardService->getEmployeeTeamView($employeeId, $filters);

        if (!$employeeCard) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Employee team view not found',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Employee team view retrieved successfully',
            'data'    => $employeeCard,
        ]);
    }
}
