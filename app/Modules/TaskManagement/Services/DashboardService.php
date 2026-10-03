<?php

namespace App\Modules\TaskManagement\Services;

use App\Models\TmTask;
use App\Models\TmSpace;
use App\Models\TmMasterStatus;
use App\Models\TmMasterPriority;
use App\Models\TmTaskTimeTracking;
use App\Models\TmTaskActivityLog;
use App\Models\HrisEmployee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get comprehensive summary dashboard data for Task Management.
     *
     * @param array $filters
     * @return array
     */
    public function getSummary(array $filters = []): array
    {
        $spaceId = !empty($filters['space_id']) ? (string)$filters['space_id'] : null;
        $departmentId = !empty($filters['department_id']) ? (string)$filters['department_id'] : null;
        $folderId = !empty($filters['folder_id']) ? (int)$filters['folder_id'] : null;
        $listId = !empty($filters['list_id']) ? (int)$filters['list_id'] : null;
        $employeeId = !empty($filters['employee_id']) ? (int)$filters['employee_id'] : (!empty($filters['assignee_id']) ? (int)$filters['assignee_id'] : null);
        $dateFrom = !empty($filters['start_date_from']) ? $filters['start_date_from'] : (!empty($filters['date_from']) ? $filters['date_from'] : null);
        $dateTo = !empty($filters['due_date_to']) ? $filters['due_date_to'] : (!empty($filters['date_to']) ? $filters['date_to'] : null);

        // Base task query builder
        $baseQuery = TmTask::query();

        if ($spaceId) {
            $baseQuery->where('space_id', $spaceId);
        }

        if ($departmentId) {
            $baseQuery->whereHas('space', fn($q) => $q->where('department_id', $departmentId));
        }

        if ($folderId) {
            $baseQuery->where('folder_id', $folderId);
        }

        if ($listId) {
            $baseQuery->where('list_id', $listId);
        }

        if ($employeeId) {
            $baseQuery->whereHas('assignedEmployees', fn($q) => $q->where('hris_employees.id', $employeeId));
        }

        if ($dateFrom) {
            $baseQuery->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $baseQuery->whereDate('due_date', '<=', $dateTo);
        }

        $now = Carbon::now();
        $todayStr = $now->toDateString();

        // 1. Overall Metrics
        $total = (clone $baseQuery)->count();
        $todo = (clone $baseQuery)->whereHas('status', fn($q) => $q->where('status_category', 'TO_DO'))->count();
        $inProgress = (clone $baseQuery)->whereHas('status', fn($q) => $q->where('status_category', 'IN_PROGRESS'))->count();
        $inReview = (clone $baseQuery)->whereHas('status', fn($q) => $q->where('status_category', 'REVIEW'))->count();
        $done = (clone $baseQuery)->whereHas('status', fn($q) => $q->where('status_category', 'DONE'))->count();
        $cancelled = (clone $baseQuery)->whereHas('status', fn($q) => $q->where('status_category', 'CANCELLED'))->count();

        // Overdue: not completed, due_date past today, and not DONE/CANCELLED
        $overdue = (clone $baseQuery)
            ->whereNull('completed_at')
            ->where('due_date', '<', $now)
            ->whereHas('status', fn($q) => $q->whereNotIn('status_category', ['DONE', 'CANCELLED']))
            ->count();

        // Due today
        $dueToday = (clone $baseQuery)
            ->whereNull('completed_at')
            ->whereDate('due_date', '=', $todayStr)
            ->whereHas('status', fn($q) => $q->whereNotIn('status_category', ['DONE', 'CANCELLED']))
            ->count();

        $completionRate = $total > 0 ? round(($done / $total) * 100, 1) : 0.0;
        $totalEstimatedHours = (float) (clone $baseQuery)->sum('estimated_hours');

        // Tracked hours for filtered tasks
        $taskIdsQuery = (clone $baseQuery)->select('id');
        $totalTrackedMinutes = TmTaskTimeTracking::whereIn('task_id', $taskIdsQuery)->sum('duration_minutes');
        $totalTrackedHours = round($totalTrackedMinutes / 60, 2);

        $overview = [
            'total_tasks'           => $total,
            'to_do'                 => $todo,
            'in_progress'           => $inProgress,
            'in_review'             => $inReview,
            'done'                  => $done,
            'cancelled'             => $cancelled,
            'overdue'               => $overdue,
            'due_today'             => $dueToday,
            'completion_rate'       => $completionRate,
            'total_estimated_hours' => round($totalEstimatedHours, 2),
            'total_tracked_hours'   => $totalTrackedHours,
        ];

        // 2. Status Distribution Breakdown
        $statusDistribution = $this->getStatusDistribution($baseQuery, $total);

        // 3. Priority Distribution Breakdown
        $priorityDistribution = $this->getPriorityDistribution($baseQuery, $total);

        // 4. Space / Department Distribution Breakdown
        $spaceDistribution = $this->getSpaceDistribution($spaceId, $departmentId);

        // 5. Team Workload / Top Assignees Breakdown
        $teamWorkload = $this->getTeamWorkload($baseQuery);

        // 6. Urgent & Overdue Actionable Tasks (Top 5)
        $urgentTasks = $this->getUrgentTasks($baseQuery, $now);

        // 7. Recent Activities Feed (Top 5)
        $recentActivities = $this->getRecentActivities($taskIdsQuery);

        return [
            'overview'              => $overview,
            'status_distribution'   => $statusDistribution,
            'priority_distribution' => $priorityDistribution,
            'space_distribution'    => $spaceDistribution,
            'team_workload'         => $teamWorkload,
            'urgent_tasks'          => $urgentTasks,
            'recent_activities'     => $recentActivities,
        ];
    }

    protected function getStatusDistribution($baseQuery, int $total): array
    {
        $statuses = TmMasterStatus::orderBy('sort_order')->get();
        $counts = (clone $baseQuery)
            ->select('status_id', DB::raw('count(*) as aggregate'))
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id')
            ->toArray();

        $result = [];
        foreach ($statuses as $st) {
            $cnt = $counts[$st->id] ?? 0;
            $result[] = [
                'status_id'       => $st->id,
                'status_name'     => $st->status_name,
                'status_category' => $st->status_category,
                'color_hex'       => $st->color_hex,
                'total_tasks'     => $cnt,
                'percentage'      => $total > 0 ? round(($cnt / $total) * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    protected function getPriorityDistribution($baseQuery, int $total): array
    {
        $priorities = TmMasterPriority::orderBy('level_weight')->get();
        $counts = (clone $baseQuery)
            ->select('priority_id', DB::raw('count(*) as aggregate'))
            ->groupBy('priority_id')
            ->pluck('aggregate', 'priority_id')
            ->toArray();

        $result = [];
        foreach ($priorities as $pr) {
            $cnt = $counts[$pr->id] ?? 0;
            $result[] = [
                'priority_id'      => $pr->id,
                'priority_code'    => $pr->priority_code,
                'priority_name'    => $pr->priority_name,
                'color_hex'        => $pr->color_hex,
                'target_sla_hours' => $pr->target_sla_hours,
                'total_tasks'      => $cnt,
                'percentage'       => $total > 0 ? round(($cnt / $total) * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    protected function getSpaceDistribution(?string $spaceId, ?string $departmentId): array
    {
        $spacesQuery = TmSpace::query();
        if ($spaceId) {
            $spacesQuery->where('id', $spaceId);
        }
        if ($departmentId) {
            $spacesQuery->where('department_id', $departmentId);
        }

        $spaces = $spacesQuery->get();
        $result = [];

        foreach ($spaces as $sp) {
            $taskQ = TmTask::where('space_id', $sp->id);
            $spTotal = (clone $taskQ)->count();
            $spDone = (clone $taskQ)->whereHas('status', fn($q) => $q->where('status_category', 'DONE'))->count();
            $spInProgress = (clone $taskQ)->whereHas('status', fn($q) => $q->where('status_category', 'IN_PROGRESS'))->count();
            $spOverdue = (clone $taskQ)
                ->whereNull('completed_at')
                ->where('due_date', '<', Carbon::now())
                ->whereHas('status', fn($q) => $q->whereNotIn('status_category', ['DONE', 'CANCELLED']))
                ->count();

            $result[] = [
                'space_id'           => $sp->id,
                'space_name'         => $sp->space_name,
                'space_slug'         => $sp->space_slug,
                'department_id'      => $sp->department_id,
                'color_hex'          => $sp->color_hex,
                'icon_name'          => $sp->icon_name,
                'total_tasks'        => $spTotal,
                'completed_tasks'    => $spDone,
                'in_progress_tasks'  => $spInProgress,
                'overdue_tasks'      => $spOverdue,
                'completion_rate'    => $spTotal > 0 ? round(($spDone / $spTotal) * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    protected function getTeamWorkload($baseQuery): array
    {
        $taskIds = (clone $baseQuery)->pluck('id');
        if ($taskIds->isEmpty()) {
            return [];
        }

        $assigneeRows = \App\Models\TmTaskAssignee::whereIn('task_id', $taskIds)
            ->select('employee_id', DB::raw('count(task_id) as total_assigned'))
            ->groupBy('employee_id')
            ->orderByDesc('total_assigned')
            ->limit(10)
            ->get();

        $employeeIds = $assigneeRows->pluck('employee_id')->filter()->toArray();
        if (empty($employeeIds)) {
            return [];
        }

        $employees = HrisEmployee::with('department')
            ->whereIn('id', $employeeIds)
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($assigneeRows as $row) {
            $emp = $employees->get($row->employee_id);
            if (!$emp) {
                continue;
            }

            $empTaskQ = (clone $baseQuery)->whereHas('assignedEmployees', fn($q) => $q->where('hris_employees.id', $emp->id));
            $empTotal = (int)$row->total_assigned;
            $empDone = (clone $empTaskQ)->whereHas('status', fn($q) => $q->where('status_category', 'DONE'))->count();
            $empPending = $empTotal - $empDone;
            $empOverdue = (clone $empTaskQ)
                ->whereNull('completed_at')
                ->where('due_date', '<', Carbon::now())
                ->whereHas('status', fn($q) => $q->whereNotIn('status_category', ['DONE', 'CANCELLED']))
                ->count();

            $minutes = TmTaskTimeTracking::where('employee_id', $emp->id)->whereIn('task_id', $taskIds)->sum('duration_minutes');

            $result[] = [
                'employee_id'          => $emp->id,
                'full_name'            => $emp->full_name,
                'nik'                  => $emp->nik,
                'avatar_url'           => $emp->avatar_url,
                'department_name'      => $emp->department ? $emp->department->dept_name : null,
                'total_tasks'          => $empTotal,
                'completed_tasks'      => $empDone,
                'pending_tasks'        => max(0, $empPending),
                'overdue_tasks'        => $empOverdue,
                'total_tracked_hours'  => round($minutes / 60, 2),
            ];
        }

        return $result;
    }

    protected function getUrgentTasks($baseQuery, Carbon $now): array
    {
        $tasks = (clone $baseQuery)
            ->with(['space', 'status', 'priority', 'assignedEmployees'])
            ->whereNull('completed_at')
            ->whereHas('status', fn($q) => $q->whereNotIn('status_category', ['DONE', 'CANCELLED']))
            ->orderByRaw('CASE WHEN due_date < ? THEN 1 ELSE 2 END', [$now])
            ->orderBy('due_date', 'asc')
            ->limit(5)
            ->get();

        return $tasks->map(function ($t) use ($now) {
            $isOverdue = $t->due_date && Carbon::parse($t->due_date)->lt($now);

            return [
                'id'                  => $t->id,
                'task_code'           => $t->task_code,
                'title'               => $t->title,
                'space_id'            => $t->space_id,
                'space_name'          => $t->space ? $t->space->space_name : null,
                'priority_name'       => $t->priority ? $t->priority->priority_name : 'Normal',
                'priority_color'      => $t->priority ? $t->priority->color_hex : '#3B82F6',
                'status_name'         => $t->status ? $t->status->status_name : 'To Do',
                'status_color'        => $t->status ? $t->status->color_hex : '#94A3B8',
                'due_date'            => $t->due_date ? Carbon::parse($t->due_date)->format('Y-m-d H:i') : null,
                'is_overdue'          => $isOverdue,
                'progress_percentage' => $t->progress_percentage ?? 0,
                'assignees'           => $t->assignedEmployees->map(fn($e) => [
                    'employee_id' => $e->id,
                    'full_name'   => $e->full_name,
                    'avatar_url'  => $e->avatar_url,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    protected function getRecentActivities($taskIdsQuery): array
    {
        $activities = TmTaskActivityLog::with(['task', 'performer'])
            ->whereIn('task_id', $taskIdsQuery)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return $activities->map(function ($act) {
            return [
                'id'             => $act->id,
                'task_id'        => $act->task_id,
                'task_code'      => $act->task ? $act->task->task_code : null,
                'task_title'     => $act->task ? $act->task->title : null,
                'action_type'    => $act->action_type,
                'field_name'     => $act->field_name,
                'performer_name' => $act->performer ? $act->performer->full_name : 'System',
                'created_at'     => $act->created_at ? Carbon::parse($act->created_at)->format('Y-m-d H:i:s') : null,
            ];
        })->values()->all();
    }
}
