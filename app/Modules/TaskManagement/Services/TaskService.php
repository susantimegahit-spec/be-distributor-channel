<?php

namespace App\Modules\TaskManagement\Services;

use App\Models\TmTask;
use App\Models\TmTaskAssignee;
use App\Models\TmMasterStatus;
use App\Models\TmMasterPriority;
use App\Models\TmMasterTaskType;
use App\Models\HrisEmployee;
use App\Modules\TaskManagement\Repositories\TaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Exception;

class TaskService
{
    protected TaskRepositoryInterface $taskRepo;

    public function __construct(TaskRepositoryInterface $taskRepo)
    {
        $this->taskRepo = $taskRepo;
    }

    public function listTasks(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->taskRepo->getFilteredTasks($filters, $perPage);
    }

    public function getTaskDetail(int $id): ?TmTask
    {
        return $this->taskRepo->findById($id);
    }

    public function createTask(array $data, int $creatorEmployeeId): TmTask
    {
        return DB::transaction(function () use ($data, $creatorEmployeeId) {
            if (empty($data['task_code'])) {
                $data['task_code'] = $this->taskRepo->generateTaskCode($data['space_id']);
            }

            if (empty($data['status_id'])) {
                $defaultStatus = TmMasterStatus::where('is_default', true)->first()
                    ?? TmMasterStatus::where('status_category', 'TO_DO')->first();
                $data['status_id'] = $defaultStatus ? $defaultStatus->id : 1;
            }

            if (empty($data['priority_id'])) {
                $normalPriority = TmMasterPriority::where('priority_code', 'NORMAL')->first();
                $data['priority_id'] = $normalPriority ? $normalPriority->id : 1;
            }

            if (empty($data['task_type_id'])) {
                $defaultType = TmMasterTaskType::where('type_code', 'TASK')->first();
                $data['task_type_id'] = $defaultType ? $defaultType->id : 1;
            }

            $data['created_by_employee_id'] = $creatorEmployeeId;

            $assigneeIds = $data['assignee_ids'] ?? [];
            unset($data['assignee_ids']);

            $task = $this->taskRepo->create($data);

            if (!empty($assigneeIds)) {
                $this->syncAssignees($task->id, $assigneeIds, $creatorEmployeeId);
            }

            $this->taskRepo->logActivity(
                $task->id,
                $creatorEmployeeId,
                'TASK_CREATED',
                null,
                null,
                $task->title
            );

            return $this->taskRepo->findById($task->id);
        });
    }

    public function updateTask(int $taskId, array $data, int $performerEmployeeId): TmTask
    {
        return DB::transaction(function () use ($taskId, $data, $performerEmployeeId) {
            $task = $this->taskRepo->findById($taskId);
            if (!$task) {
                throw new Exception("Task with ID {$taskId} not found.");
            }

            $assigneeIds = null;
            if (isset($data['assignee_ids'])) {
                $assigneeIds = $data['assignee_ids'];
                unset($data['assignee_ids']);
            }

            // Detect field changes for activity logging
            foreach ($data as $key => $newVal) {
                $oldVal = $task->{$key};
                if ($oldVal != $newVal) {
                    $this->taskRepo->logActivity(
                        $task->id,
                        $performerEmployeeId,
                        'FIELD_UPDATED',
                        $key,
                        (string)$oldVal,
                        (string)$newVal
                    );
                }
            }

            $this->taskRepo->update($task, $data);

            if ($assigneeIds !== null) {
                $this->syncAssignees($task->id, $assigneeIds, $performerEmployeeId);
            }

            return $this->taskRepo->findById($task->id);
        });
    }

    public function updateStatus(int $taskId, int $newStatusId, int $performerEmployeeId): TmTask
    {
        return DB::transaction(function () use ($taskId, $newStatusId, $performerEmployeeId) {
            $task = $this->taskRepo->findById($taskId);
            if (!$task) {
                throw new Exception("Task with ID {$taskId} not found.");
            }

            $newStatus = TmMasterStatus::findOrFail($newStatusId);
            $oldStatusName = $task->status ? $task->status->status_name : 'Unknown';

            $updateData = ['status_id' => $newStatusId];
            if ($newStatus->is_closed_status) {
                $updateData['completed_at'] = now();
                $updateData['progress_percentage'] = 100;
            } elseif ($task->status && $task->status->is_closed_status && !$newStatus->is_closed_status) {
                $updateData['completed_at'] = null;
            }

            $this->taskRepo->update($task, $updateData);

            $this->taskRepo->logActivity(
                $task->id,
                $performerEmployeeId,
                'STATUS_CHANGED',
                'status_id',
                $oldStatusName,
                $newStatus->status_name
            );

            return $this->taskRepo->findById($task->id);
        });
    }

    public function syncAssignees(int $taskId, array $employeeIds, int $assignedByEmployeeId): void
    {
        $existing = TmTaskAssignee::where('task_id', $taskId)->pluck('employee_id')->toArray();
        $toAdd = array_diff($employeeIds, $existing);
        $toRemove = array_diff($existing, $toAdd);

        foreach ($toAdd as $empId) {
            TmTaskAssignee::firstOrCreate([
                'task_id'                  => $taskId,
                'employee_id'              => $empId,
            ], [
                'assigned_by_employee_id' => $assignedByEmployeeId,
            ]);

            $emp = HrisEmployee::find($empId);
            $this->taskRepo->logActivity(
                $taskId,
                $assignedByEmployeeId,
                'ASSIGNEE_ADDED',
                'employee_id',
                null,
                $emp ? $emp->full_name : (string)$empId
            );
        }

        if (!empty($toRemove)) {
            TmTaskAssignee::where('task_id', $taskId)->whereIn('employee_id', $toRemove)->delete();
            foreach ($toRemove as $empId) {
                $emp = HrisEmployee::find($empId);
                $this->taskRepo->logActivity(
                    $taskId,
                    $assignedByEmployeeId,
                    'ASSIGNEE_REMOVED',
                    'employee_id',
                    $emp ? $emp->full_name : (string)$empId,
                    null
                );
            }
        }
    }

    public function deleteTask(int $taskId, int $performerEmployeeId): bool
    {
        $task = $this->taskRepo->findById($taskId);
        if (!$task) {
            throw new Exception("Task not found.");
        }

        $this->taskRepo->logActivity(
            $taskId,
            $performerEmployeeId,
            'TASK_DELETED',
            null,
            $task->title,
            null
        );

        return $this->taskRepo->delete($task);
    }

    public function getSummaryMetrics(?int $spaceId = null): array
    {
        $query = TmTask::query();
        if ($spaceId) {
            $query->where('space_id', $spaceId);
        }

        $total = (clone $query)->count();
        $todo = (clone $query)->whereHas('status', fn($q) => $q->where('status_category', 'TO_DO'))->count();
        $inProgress = (clone $query)->whereHas('status', fn($q) => $q->where('status_category', 'IN_PROGRESS'))->count();
        $inReview = (clone $query)->whereHas('status', fn($q) => $q->where('status_category', 'REVIEW'))->count();
        $done = (clone $query)->whereHas('status', fn($q) => $q->where('status_category', 'DONE'))->count();
        $overdue = (clone $query)->whereNull('completed_at')->where('due_date', '<', now())->count();

        return [
            'total_tasks'       => $total,
            'to_do'             => $todo,
            'in_progress'       => $inProgress,
            'in_review'         => $inReview,
            'done'              => $done,
            'overdue'           => $overdue,
            'completion_rate'   => $total > 0 ? round(($done / $total) * 100, 1) : 0,
        ];
    }
}
