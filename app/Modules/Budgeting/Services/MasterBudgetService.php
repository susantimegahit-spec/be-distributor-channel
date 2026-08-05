<?php

namespace App\Modules\Budgeting\Services;

use App\Models\MasterBudget;
use App\Modules\Budgeting\Repositories\MasterBudgetRepositoryInterface;

class MasterBudgetService
{
    protected MasterBudgetRepositoryInterface $repository;

    public function __construct(MasterBudgetRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getList(array $filters = [], int $perPage = 15)
    {
        return $this->repository->getAll($filters, $perPage);
    }

    public function getDetail(int $id): ?MasterBudget
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, ?int $userId = null): MasterBudget
    {
        if ($userId) {
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
        }
        return $this->repository->create($data);
    }

    public function update(int $id, array $data, ?int $userId = null): ?MasterBudget
    {
        if ($userId) {
            $data['updated_by'] = $userId;
        }
        return $this->repository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
