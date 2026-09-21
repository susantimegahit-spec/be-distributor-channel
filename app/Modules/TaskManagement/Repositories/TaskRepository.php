<?php

namespace App\Modules\TaskManagement\Repositories;

use App\Models\TmTask;
use App\Models\TmSpace;
use App\Models\TmTaskActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskRepository implements TaskRepositoryInterface
{
    public function getFilteredTasks(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = TmTask::query()->with([
            'space:id,space_name,space_slug,color_hex',
            'folder:id,folder_name',
            'list:id,list_name',
            'status:id,status_name,status_category,color_hex',
            'priority:id,priority_name,color_hex,level_weight',
            'taskType:id,type_name,icon_name,color_hex',
            'assignedEmployees:id,nik,full_name,avatar_url',
            'createdBy:id,nik,full_name',
        ]);

        if (!empty($filters['space_id'])) {
            $query->where('space_id', $filters['space_id']);
        }

        if (!empty($filters['folder_id'])) {
            $query->where('folder_id', $filters['folder_id']);
        }

        if (!empty($filters['list_id'])) {
            $query->where('list_id', $filters['list_id']);
        }

        if (!empty($filters['parent_task_id'])) {
            $query->where('parent_task_id', $filters['parent_task_id']);
        } elseif (!isset($filters['include_subtasks']) || !$filters['include_subtasks']) {
            $query->whereNull('parent_task_id');
        }

        if (!empty($filters['status_id'])) {
            $query->where('status_id', $filters['status_id']);
        }

        if (!empty($filters['status_category'])) {
            $query->whereHas('status', function ($q) use ($filters) {
                $q->where('status_category', $filters['status_category']);
            });
        }

        if (!empty($filters['priority_id'])) {
            $query->where('priority_id', $filters['priority_id']);
        }

        if (!empty($filters['task_type_id'])) {
            $query->where('task_type_id', $filters['task_type_id']);
        }

        if (!empty($filters['assignee_id'])) {
            $query->whereHas('assignedEmployees', function ($q) use ($filters) {
                $q->where('hris_employees.id', $filters['assignee_id']);
            });
        }

        if (!empty($filters['due_date_from'])) {
            $query->whereDate('due_date', '>=', $filters['due_date_from']);
        }

        if (!empty($filters['due_date_to'])) {
            $query->whereDate('due_date', '<=', $filters['due_date_to']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('task_code', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['created_at', 'due_date', 'sort_order', 'priority_id', 'status_id', 'title'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            $query->latest();
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?TmTask
    {
        return TmTask::with([
            'space:id,space_name,space_slug,color_hex',
            'folder:id,folder_name',
            'list:id,list_name',
            'status:id,status_name,status_category,color_hex',
            'priority:id,priority_name,color_hex,level_weight,target_sla_hours',
            'taskType:id,type_name,icon_name,color_hex',
            'createdBy:id,nik,full_name,email_office',
            'approvedBy:id,nik,full_name',
            'assignedEmployees:id,nik,full_name,email_office,avatar_url',
            'watchers.employee:id,nik,full_name',
            'tags:id,tag_name,color_hex',
            'checklists.items.assignee:id,full_name',
            'attachments.uploader:id,full_name',
            'comments.author:id,full_name,avatar_url',
            'comments.replies.author:id,full_name,avatar_url',
            'timeTrackings.employee:id,full_name',
            'dependencies.dependsOnTask:id,task_code,title',
            'subtasks' => function ($q) {
                $q->with(['status:id,status_name,color_hex', 'priority:id,priority_name,color_hex', 'assignedEmployees:id,nik,full_name']);
            },
            'activityLogs.performer:id,nik,full_name',
        ])->find($id);
    }

    public function findByCode(string $taskCode): ?TmTask
    {
        return TmTask::where('task_code', $taskCode)->first();
    }

    public function create(array $data): TmTask
    {
        return TmTask::create($data);
    }

    public function update(TmTask $task, array $data): bool
    {
        return $task->update($data);
    }

    public function delete(TmTask $task): bool
    {
        return $task->delete();
    }

    public function getSubtasks(int $taskId): Collection
    {
        return TmTask::with([
            'status:id,status_name,color_hex',
            'priority:id,priority_name,color_hex',
            'assignedEmployees:id,nik,full_name,avatar_url'
        ])
        ->where('parent_task_id', $taskId)
        ->orderBy('sort_order')
        ->get();
    }

    public function generateTaskCode(int $spaceId): string
    {
        $space = TmSpace::with('department')->find($spaceId);
        $deptCode = $space && $space->department ? strtoupper($space->department->dept_code) : 'GEN';
        $year = date('Y');

        $latestTask = TmTask::where('task_code', 'like', "TSK-{$deptCode}-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;
        if ($latestTask && preg_match('/TSK-' . preg_quote($deptCode, '/') . '-' . $year . '-(\d+)/', $latestTask->task_code, $matches)) {
            $nextNumber = ((int)$matches[1]) + 1;
        }

        return sprintf('TSK-%s-%s-%05d', $deptCode, $year, $nextNumber);
    }

    public function logActivity(int $taskId, int $performerId, string $actionType, ?string $fieldName = null, ?string $oldValue = null, ?string $newValue = null): void
    {
        TmTaskActivityLog::create([
            'task_id'                  => $taskId,
            'performed_by_employee_id' => $performerId,
            'action_type'              => $actionType,
            'field_name'               => $fieldName,
            'old_value'                => $oldValue,
            'new_value'                => $newValue,
        ]);
    }
}
