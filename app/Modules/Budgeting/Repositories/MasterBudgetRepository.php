<?php

namespace App\Modules\Budgeting\Repositories;

use App\Models\MasterBudget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MasterBudgetRepository implements MasterBudgetRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = MasterBudget::query();

        if (!empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (!empty($filters['cost_center'])) {
            $query->where('cost_center', $filters['cost_center']);
        }

        if (!empty($filters['budget_category'])) {
            $query->where('budget_category', $filters['budget_category']);
        }

        if (!empty($filters['period_year'])) {
            $query->where('period_year', (int)$filters['period_year']);
        }

        if (!empty($filters['period_month'])) {
            $query->where('period_month', (int)$filters['period_month']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('budget_code', 'like', $search)
                  ->orWhere('department', 'like', $search)
                  ->orWhere('cost_center', 'like', $search)
                  ->orWhere('budget_category', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        $query->orderBy('period_year', 'desc')
              ->orderBy('period_month', 'desc')
              ->orderBy('id', 'desc');

        if (isset($filters['paginate']) && $filters['paginate'] === 'false') {
            return $query->get();
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?MasterBudget
    {
        return MasterBudget::find($id);
    }

    public function create(array $data): MasterBudget
    {
        return MasterBudget::create($data);
    }

    public function update(int $id, array $data): ?MasterBudget
    {
        $budget = $this->findById($id);
        if ($budget) {
            $budget->update($data);
            return $budget->fresh();
        }
        return null;
    }

    public function delete(int $id): bool
    {
        $budget = $this->findById($id);
        if ($budget) {
            return $budget->delete();
        }
        return false;
    }
}
