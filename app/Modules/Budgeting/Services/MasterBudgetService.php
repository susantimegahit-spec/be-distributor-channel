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
        if (empty($data['budget_code'])) {
            $data['budget_code'] = $this->generateBudgetCode(
                isset($data['period_year']) ? (int)$data['period_year'] : null,
                isset($data['period_month']) ? (int)$data['period_month'] : null
            );
        }

        if ($userId) {
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
        }
        return $this->repository->create($data);
    }

    /**
     * Generate unique sequential budget code (e.g. BDG-202608-0001).
     */
    public function generateBudgetCode(?int $year = null, ?int $month = null): string
    {
        $yearStr = (string)($year ?? date('Y'));
        $monthStr = $month ? str_pad((string)$month, 2, '0', STR_PAD_LEFT) : date('m');
        $prefix = "BDG-{$yearStr}{$monthStr}-";

        $lastBudget = MasterBudget::where('budget_code', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNum = 1;
        if ($lastBudget) {
            $parts = explode('-', $lastBudget->budget_code);
            $lastSeq = end($parts);
            if (is_numeric($lastSeq)) {
                $nextNum = (int)$lastSeq + 1;
            }
        }

        $code = $prefix . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);

        while (MasterBudget::where('budget_code', $code)->exists()) {
            $nextNum++;
            $code = $prefix . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);
        }

        return $code;
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
