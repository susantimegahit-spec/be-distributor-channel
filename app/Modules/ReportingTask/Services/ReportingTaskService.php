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
                  ->orWhere('space_name', 'like', "%{$search}%")
                  ->orWhere('folder_name', 'like', "%{$search}%")
                  ->orWhere('list_name', 'like', "%{$search}%")
                  ->orWhere('assignee', 'like', "%{$search}%")
                  ->orWhere('comment', 'like', "%{$search}%");
            });
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

        return $query;
    }

    /**
     * Get paginated or all tasks (for Data Studio connector).
     */
    public function getTasks(array $filters = [], int $perPage = 50): LengthAwarePaginator|Collection
    {
        $query = $this->getFilteredQuery($filters);

        $sortBy = $filters['sort_by'] ?? 'updated_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
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
}
