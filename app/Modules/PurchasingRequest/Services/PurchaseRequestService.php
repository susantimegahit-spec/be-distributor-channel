<?php

namespace App\Modules\PurchasingRequest\Services;

use App\Models\PurchaseRequest;
use App\Modules\PurchasingRequest\Repositories\PurchaseRequestRepositoryInterface;

class PurchaseRequestService
{
    protected PurchaseRequestRepositoryInterface $repository;

    public function __construct(PurchaseRequestRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getList(array $filters = [], int $perPage = 15)
    {
        return $this->repository->getAll($filters, $perPage);
    }

    public function getDetail(int $id): ?PurchaseRequest
    {
        return $this->repository->findById($id);
    }

    public function create(array $payload, ?int $userId = null): PurchaseRequest
    {
        $details = $payload['details'] ?? [];
        unset($payload['details']);

        if ($userId) {
            $payload['created_by'] = $userId;
            $payload['updated_by'] = $userId;
            $payload['requester_id'] = $payload['requester_id'] ?? $userId;
        }

        return $this->repository->create($payload, $details);
    }

    public function update(int $id, array $payload, ?int $userId = null): ?PurchaseRequest
    {
        $details = $payload['details'] ?? null;
        unset($payload['details']);

        if ($userId) {
            $payload['updated_by'] = $userId;
        }

        return $this->repository->update($id, $payload, $details);
    }

    public function updateStatus(int $id, string $status, ?int $userId = null): ?PurchaseRequest
    {
        return $this->repository->updateStatus($id, $status, $userId);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
