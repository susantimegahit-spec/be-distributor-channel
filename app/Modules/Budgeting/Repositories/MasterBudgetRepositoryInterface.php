<?php

namespace App\Modules\Budgeting\Repositories;

use App\Models\MasterBudget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface MasterBudgetRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;
    public function findById(int $id): ?MasterBudget;
    public function create(array $data): MasterBudget;
    public function update(int $id, array $data): ?MasterBudget;
    public function delete(int $id): bool;
}
