<?php

namespace App\Modules\TaskManagement\Repositories;

use App\Models\TmTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TaskRepositoryInterface
{
    public function getFilteredTasks(array $filters, int $perPage = 15): LengthAwarePaginator;
    public function findById(int $id): ?TmTask;
    public function findByCode(string $taskCode): ?TmTask;
    public function create(array $data): TmTask;
    public function update(TmTask $task, array $data): bool;
    public function delete(TmTask $task): bool;
    public function getSubtasks(int $taskId): Collection;
    public function generateTaskCode(int $spaceId): string;
    public function logActivity(int $taskId, int $performerId, string $actionType, ?string $fieldName = null, ?string $oldValue = null, ?string $newValue = null): void;
}
