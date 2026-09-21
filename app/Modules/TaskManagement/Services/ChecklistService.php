<?php

namespace App\Modules\TaskManagement\Services;

use App\Models\TmTaskChecklist;
use App\Models\TmTaskChecklistItem;
use App\Modules\TaskManagement\Repositories\TaskRepositoryInterface;
use Exception;

class ChecklistService
{
    protected TaskRepositoryInterface $taskRepo;

    public function __construct(TaskRepositoryInterface $taskRepo)
    {
        $this->taskRepo = $taskRepo;
    }

    public function createChecklist(int $taskId, string $title): TmTaskChecklist
    {
        return TmTaskChecklist::create([
            'task_id'         => $taskId,
            'checklist_title' => $title,
        ]);
    }

    public function addItem(int $checklistId, array $data): TmTaskChecklistItem
    {
        $data['checklist_id'] = $checklistId;
        return TmTaskChecklistItem::create($data);
    }

    public function toggleItem(int $itemId, int $performerEmployeeId): TmTaskChecklistItem
    {
        $item = TmTaskChecklistItem::findOrFail($itemId);
        $newStatus = !$item->is_completed;

        $item->update([
            'is_completed'             => $newStatus,
            'completed_at'             => $newStatus ? now() : null,
            'completed_by_employee_id' => $newStatus ? $performerEmployeeId : null,
        ]);

        $task = $item->checklist ? $item->checklist->task : null;
        if ($task) {
            $this->taskRepo->logActivity(
                $task->id,
                $performerEmployeeId,
                'CHECKLIST_TOGGLED',
                'is_completed',
                $newStatus ? 'Uncompleted' : 'Completed',
                $newStatus ? 'Completed: ' . $item->item_text : 'Uncompleted: ' . $item->item_text
            );
        }

        return $item;
    }

    public function deleteItem(int $itemId): bool
    {
        $item = TmTaskChecklistItem::findOrFail($itemId);
        return $item->delete();
    }

    public function deleteChecklist(int $checklistId): bool
    {
        $checklist = TmTaskChecklist::findOrFail($checklistId);
        return $checklist->delete();
    }
}
