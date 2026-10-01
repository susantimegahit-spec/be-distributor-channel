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
}
