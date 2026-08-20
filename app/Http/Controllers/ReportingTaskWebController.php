<?php

namespace App\Http\Controllers;

use App\Models\ReportingTask;
use App\Modules\ReportingTask\Services\ReportingTaskService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportingTaskWebController extends Controller
{
    protected ReportingTaskService $reportingTaskService;

    public function __construct(ReportingTaskService $reportingTaskService)
    {
        $this->reportingTaskService = $reportingTaskService;
    }

    /**
     * Display the ClickUp Task Reporting Dashboard.
     */
    public function index(Request $request): View
    {
        $filters = $request->only([
            'search',
            'space_id',
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

        $perPage = (int) $request->input('per_page', 15);
        if ($perPage < 5 || $perPage > 100) {
            $perPage = 15;
        }

        // 1. Get filtered query for tasks list and KPIs
        $query = $this->reportingTaskService->getFilteredQuery($filters);

        // Sorting
        $sortBy = $request->input('sort_by', 'updated_at');
        $allowedSorts = ['task_name', 'status', 'priority', 'assignee', 'list_name', 'start_date', 'due_date', 'updated_at', 'created_at', 'synced_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'updated_at';
        }
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate tasks
        $tasks = $query->paginate($perPage)->withQueryString();

        // 2. Compute KPI Metrics (using base filtered query clone)
        $baseQuery = $this->reportingTaskService->getFilteredQuery($filters);

        $totalTasks = (clone $baseQuery)->count();

        $inProgressCount = (clone $baseQuery)->where(function ($q) {
            $q->whereRaw("LOWER(status) IN ('in progress', 'progress', 'doing', 'working', 'in review', 'review', 'active')");
        })->count();

        $completedCount = (clone $baseQuery)->where(function ($q) {
            $q->whereRaw("LOWER(status) IN ('complete', 'completed', 'done', 'closed', 'resolved')");
        })->count();

        $overdueCount = (clone $baseQuery)->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhereRaw("LOWER(status) NOT IN ('complete', 'completed', 'done', 'closed', 'resolved')");
            })->count();

        $dueSoonCount = (clone $baseQuery)->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhereRaw("LOWER(status) NOT IN ('complete', 'completed', 'done', 'closed', 'resolved')");
            })->count();

        $completionRate = $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100, 1) : 0;

        // 3. Aggregate Data for Charts
        // Status Distribution
        $statusData = (clone $baseQuery)->selectRaw("COALESCE(status, 'Unassigned') as status_label, count(*) as count")
            ->groupBy('status_label')
            ->orderBy('count', 'desc')
            ->pluck('count', 'status_label')
            ->toArray();

        // Top Assignees
        $assigneeData = (clone $baseQuery)->selectRaw("COALESCE(assignee, 'Unassigned') as assignee_label, count(*) as count")
            ->groupBy('assignee_label')
            ->orderBy('count', 'desc')
            ->limit(8)
            ->pluck('count', 'assignee_label')
            ->toArray();

        // List / Folder Distribution
        $listData = (clone $baseQuery)->selectRaw("COALESCE(list_name, 'No List') as list_label, count(*) as count")
            ->groupBy('list_label')
            ->orderBy('count', 'desc')
            ->limit(8)
            ->pluck('count', 'list_label')
            ->toArray();

        // Priority Distribution
        $priorityData = (clone $baseQuery)->selectRaw("COALESCE(priority, 'None') as priority_label, count(*) as count")
            ->groupBy('priority_label')
            ->orderBy('count', 'desc')
            ->pluck('count', 'priority_label')
            ->toArray();

        // Timeline Trend (Grouped by Due Date month/week or Created Date)
        $timelineData = (clone $baseQuery)->whereNotNull('due_date')
            ->selectRaw("TO_CHAR(due_date, 'YYYY-MM-DD') as date_label, count(*) as count")
            ->groupBy('date_label')
            ->orderBy('date_label', 'asc')
            ->limit(14)
            ->pluck('count', 'date_label')
            ->toArray();

        // 4. Distinct Filter Options for Dropdowns
        $filterOptions = [
            'spaces'    => ReportingTask::whereNotNull('space_id')->select('space_id', 'space_name')->distinct()->orderBy('space_id')->get(),
            'folders'   => ReportingTask::whereNotNull('folder_name')->distinct()->orderBy('folder_name')->pluck('folder_name')->toArray(),
            'lists'     => ReportingTask::whereNotNull('list_name')->distinct()->orderBy('list_name')->pluck('list_name')->toArray(),
            'assignees' => ReportingTask::whereNotNull('assignee')->distinct()->orderBy('assignee')->pluck('assignee')->toArray(),
            'statuses'  => ReportingTask::whereNotNull('status')->distinct()->orderBy('status')->pluck('status')->toArray(),
            'priorities'=> ReportingTask::whereNotNull('priority')->distinct()->orderBy('priority')->pluck('priority')->toArray(),
        ];

        // Last Synced At
        $lastSyncedAt = ReportingTask::max('synced_at');

        return view('reporting.tasks', [
            'tasks'           => $tasks,
            'filters'         => $filters,
            'filterOptions'   => $filterOptions,
            'kpis'            => [
                'total'           => $totalTasks,
                'in_progress'     => $inProgressCount,
                'completed'       => $completedCount,
                'overdue'         => $overdueCount,
                'due_soon'        => $dueSoonCount,
                'completion_rate' => $completionRate,
            ],
            'chartData'       => [
                'status'   => $statusData,
                'assignee' => $assigneeData,
                'list'     => $listData,
                'priority' => $priorityData,
                'timeline' => $timelineData,
            ],
            'lastSyncedAt'    => $lastSyncedAt ? Carbon::parse($lastSyncedAt) : null,
            'sortBy'          => $sortBy,
            'sortOrder'       => $sortOrder,
        ]);
    }
}
