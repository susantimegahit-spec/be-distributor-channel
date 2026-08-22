<?php

namespace App\Modules\ReportingTask\Services;

use App\Models\ReportingTask;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportingTaskService
{
    /**
     * Parse date from various formats (timestamps in ms/s, ISO strings, Y-m-d H:i:s).
     */
    protected function parseDate($dateValue): ?string
    {
        if (empty($dateValue)) {
            return null;
        }

        try {
            // Check if numeric unix timestamp in milliseconds
            if (is_numeric($dateValue)) {
                $num = (float) $dateValue;
                if ($num > 10000000000) { // milliseconds
                    return Carbon::createFromTimestampMs((int)$num)->format('Y-m-d H:i:s');
                }
                return Carbon::createFromTimestamp((int)$num)->format('Y-m-d H:i:s');
            }

            return Carbon::parse($dateValue)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Normalize string or array of assignee names.
     */
    protected function normalizeAssignee($assignee): ?string
    {
        if (empty($assignee)) {
            return null;
        }

        if (is_array($assignee)) {
            $names = [];
            foreach ($assignee as $item) {
                if (is_array($item)) {
                    $names[] = $item['username'] ?? $item['name'] ?? $item['email'] ?? json_encode($item);
                } elseif (is_string($item)) {
                    $names[] = $item;
                }
            }
            return implode(', ', array_filter($names));
        }

        return (string) $assignee;
    }

    /**
     * Upsert a single task from ClickUp / n8n payload.
     */
    public function upsertTask(array $data): ReportingTask
    {
        $taskId = (string) ($data['task_id'] ?? $data['id'] ?? $data['taskId'] ?? '');
        if (empty($taskId)) {
            throw new \Exception("Field 'task_id' wajib diisi.");
        }

        $taskName = (string) ($data['task_name'] ?? $data['name'] ?? $data['taskName'] ?? '');

        // ClickUp Hierarchy Locations
        $spaceId = is_array($data['space'] ?? null) ? ($data['space']['id'] ?? null) : ($data['space_id'] ?? $data['spaceId'] ?? null);
        $spaceName = is_array($data['space'] ?? null) ? ($data['space']['name'] ?? null) : ($data['space_name'] ?? $data['space'] ?? null);
        $folderName = is_array($data['folder'] ?? null) ? ($data['folder']['name'] ?? null) : ($data['folder_name'] ?? $data['folder'] ?? null);
        $listName = is_array($data['list'] ?? null) ? ($data['list']['name'] ?? null) : ($data['list_name'] ?? $data['list'] ?? null);

        $assignee = $this->normalizeAssignee($data['assignee'] ?? $data['assignees'] ?? null);
        $timeline = $data['timeline'] ?? $data['sprint'] ?? null;
        $startDate = $this->parseDate($data['start_date'] ?? $data['startDate'] ?? $data['start_date_time'] ?? null);
        $dueDate = $this->parseDate($data['due_date'] ?? $data['dueDate'] ?? $data['due_date_time'] ?? null);
        $priority = is_array($data['priority'] ?? null) ? ($data['priority']['priority'] ?? $data['priority']['name'] ?? null) : ($data['priority'] ?? null);
        $taskType = $data['task_type'] ?? $data['type'] ?? $data['taskType'] ?? null;
        $createdBy = is_array($data['created_by'] ?? null) ? ($data['created_by']['username'] ?? $data['created_by']['name'] ?? null) : ($data['created_by'] ?? $data['creator'] ?? null);
        $comment = $data['comment'] ?? $data['comments'] ?? $data['description'] ?? $data['text_content'] ?? null;
        if (is_array($comment)) {
            $comment = json_encode($comment);
        }
        $status = is_array($data['status'] ?? null) ? ($data['status']['status'] ?? $data['status']['name'] ?? null) : ($data['status'] ?? null);

        return ReportingTask::updateOrCreate(
            ['task_id' => $taskId],
            [
                'task_name'   => $taskName ?: null,
                'space_id'    => $spaceId,
                'space_name'  => $spaceName,
                'folder_name' => $folderName,
                'list_name'   => $listName,
                'assignee'    => $assignee,
                'timeline'    => $timeline,
                'start_date'  => $startDate,
                'due_date'    => $dueDate,
                'priority'    => $priority,
                'task_type'   => $taskType,
                'created_by'  => $createdBy,
                'comment'     => $comment,
                'status'      => $status,
                'synced_at'   => now(),
            ]
        );
    }

    /**
     * Batch upsert tasks from n8n.
     */
    public function syncBatch(array $items): array
    {
        $results = [
            'total'   => count($items),
            'success' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];

        foreach ($items as $idx => $item) {
            if (!is_array($item)) {
                continue;
            }
            try {
                $this->upsertTask($item);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'index'   => $idx,
                    'task_id' => $item['task_id'] ?? $item['id'] ?? null,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get query builder with filters.
     */
    public function getFilteredQuery(array $filters = []): Builder
    {
        $query = ReportingTask::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('task_name', 'like', "%{$search}%")
                  ->orWhere('task_id', 'like', "%{$search}%")
                  ->orWhere('space_id', 'like', "%{$search}%")
                  ->orWhere('space_name', 'like', "%{$search}%")
                  ->orWhere('folder_name', 'like', "%{$search}%")
                  ->orWhere('list_name', 'like', "%{$search}%")
                  ->orWhere('assignee', 'like', "%{$search}%")
                  ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['space_id'])) {
            $query->where('space_id', $filters['space_id']);
        }

        if (!empty($filters['space_name'])) {
            $query->where('space_name', $filters['space_name']);
        }

        if (!empty($filters['folder_name'])) {
            $query->where('folder_name', $filters['folder_name']);
        }

        if (!empty($filters['list_name'])) {
            $query->where('list_name', $filters['list_name']);
        }

        if (!empty($filters['status'])) {
            $status = $filters['status'];
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
        }

        if (!empty($filters['assignee'])) {
            $query->where('assignee', 'like', "%{$filters['assignee']}%");
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['task_type'])) {
            $query->where('task_type', $filters['task_type']);
        }

        if (!empty($filters['timeline'])) {
            $query->where('timeline', $filters['timeline']);
        }

        if (!empty($filters['start_date_from'])) {
            $query->where('start_date', '>=', Carbon::parse($filters['start_date_from'])->startOfDay());
        }

        if (!empty($filters['start_date_to'])) {
            $query->where('start_date', '<=', Carbon::parse($filters['start_date_to'])->endOfDay());
        }

        if (!empty($filters['due_date_from'])) {
            $query->where('due_date', '>=', Carbon::parse($filters['due_date_from'])->startOfDay());
        }

        if (!empty($filters['due_date_to'])) {
            $query->where('due_date', '<=', Carbon::parse($filters['due_date_to'])->endOfDay());
        }

        // Quick Filter Preset Handling (KPI Click Filters)
        if (!empty($filters['quick_filter'])) {
            $quickFilter = $filters['quick_filter'];
            if ($quickFilter === 'in_progress') {
                $query->where(function ($q) {
                    $q->whereRaw("LOWER(status) IN ('in progress', 'progress', 'doing', 'working', 'in review', 'review', 'active')");
                });
            } elseif ($quickFilter === 'completed') {
                $query->where(function ($q) {
                    $q->whereRaw("LOWER(status) IN ('complete', 'completed', 'done', 'closed', 'resolved')");
                });
            } elseif ($quickFilter === 'overdue') {
                $query->whereNotNull('due_date')
                    ->where('due_date', '<', now())
                    ->where(function ($q) {
                        $q->whereNull('status')
                          ->orWhereRaw("LOWER(status) NOT IN ('complete', 'completed', 'done', 'closed', 'resolved')");
                    });
            } elseif ($quickFilter === 'due_soon') {
                $query->whereNotNull('due_date')
                    ->where('due_date', '>=', now())
                    ->where('due_date', '<=', now()->addDays(7))
                    ->where(function ($q) {
                        $q->whereNull('status')
                          ->orWhereRaw("LOWER(status) NOT IN ('complete', 'completed', 'done', 'closed', 'resolved')");
                    });
            }
        }

        return $query;
    }

    /**
     * Get paginated or all tasks (for Data Studio connector / FE list).
     */
    public function getTasks(array $filters = [], int $perPage = 50): LengthAwarePaginator|Collection
    {
        $query = $this->getFilteredQuery($filters);

        $sortBy = $filters['sort_by'] ?? 'updated_at';
        $allowedSorts = ['task_name', 'status', 'priority', 'assignee', 'list_name', 'folder_name', 'space_name', 'start_date', 'due_date', 'updated_at', 'created_at', 'synced_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'updated_at';
        }
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // If pagination is explicitly disabled (e.g. for full BI export)
        if (isset($filters['all']) && filter_var($filters['all'], FILTER_VALIDATE_BOOLEAN)) {
            return $query->get();
        }

        return $query->paginate($perPage);
    }

    /**
     * Get summary metrics for Dashboard / Data Studio.
     */
    public function getSummary(): array
    {
        $total = ReportingTask::count();
        $byStatus = ReportingTask::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $byPriority = ReportingTask::selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        $byAssignee = ReportingTask::selectRaw('assignee, count(*) as total')
            ->groupBy('assignee')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->pluck('total', 'assignee')
            ->toArray();

        $overdueCount = ReportingTask::whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhereNotIn('status', ['complete', 'closed', 'done', 'resolved']);
            })
            ->count();

        return [
            'total_tasks'    => $total,
            'overdue_tasks'  => $overdueCount,
            'by_status'      => $byStatus,
            'by_priority'    => $byPriority,
            'top_assignees'  => $byAssignee,
            'last_synced_at' => ReportingTask::max('synced_at'),
        ];
    }

    /**
     * Get full dashboard data (KPIs, Charts, Options) for API / SPA Frontend.
     */
    public function getDashboardMetrics(array $filters = []): array
    {
        $baseQuery = $this->getFilteredQuery(array_diff_key($filters, ['quick_filter' => '']));

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

        // Chart Aggregations
        $statusData = (clone $baseQuery)->selectRaw("COALESCE(status, 'Unassigned') as label, count(*) as total")
            ->groupBy('label')
            ->orderBy('total', 'desc')
            ->get();

        $priorityData = (clone $baseQuery)->selectRaw("COALESCE(priority, 'None') as label, count(*) as total")
            ->groupBy('label')
            ->orderBy('total', 'desc')
            ->get();

        $assigneeData = (clone $baseQuery)->selectRaw("COALESCE(assignee, 'Unassigned') as label, count(*) as total")
            ->groupBy('label')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        $listData = (clone $baseQuery)->selectRaw("COALESCE(list_name, 'No List') as label, count(*) as total")
            ->groupBy('label')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        $timelineData = (clone $baseQuery)->whereNotNull('due_date')
            ->selectRaw("TO_CHAR(due_date, 'YYYY-MM-DD') as label, count(*) as total")
            ->groupBy('label')
            ->orderBy('label', 'asc')
            ->limit(14)
            ->get();

        return [
            'kpis' => [
                'total_tasks'     => $totalTasks,
                'in_progress'     => $inProgressCount,
                'completed'       => $completedCount,
                'overdue'         => $overdueCount,
                'due_soon'        => $dueSoonCount,
                'completion_rate' => $completionRate,
            ],
            'charts' => [
                'status_distribution'   => $statusData,
                'priority_distribution' => $priorityData,
                'top_assignees'         => $assigneeData,
                'list_distribution'     => $listData,
                'due_date_timeline'     => $timelineData,
            ],
            'filter_options' => $this->getFilterOptions(),
            'last_synced_at' => ReportingTask::max('synced_at'),
        ];
    }

    /**
     * Get distinct filter options for Frontend select dropdowns.
     */
    public function getFilterOptions(): array
    {
        return [
            'spaces'     => ReportingTask::whereNotNull('space_id')->select('space_id', 'space_name')->distinct()->orderBy('space_id')->get(),
            'folders'    => ReportingTask::whereNotNull('folder_name')->distinct()->orderBy('folder_name')->pluck('folder_name')->toArray(),
            'lists'      => ReportingTask::whereNotNull('list_name')->distinct()->orderBy('list_name')->pluck('list_name')->toArray(),
            'assignees'  => ReportingTask::whereNotNull('assignee')->distinct()->orderBy('assignee')->pluck('assignee')->toArray(),
            'statuses'   => ReportingTask::whereNotNull('status')->distinct()->orderBy('status')->pluck('status')->toArray(),
            'priorities' => ReportingTask::whereNotNull('priority')->distinct()->orderBy('priority')->pluck('priority')->toArray(),
        ];
    }
}
