<?php

namespace App\Modules\PurchasingRequest\Repositories;

use App\Models\PurchaseRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PurchaseRequestRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;
    public function findById(int $id): ?PurchaseRequest;
    public function create(array $data, array $details): PurchaseRequest;
    public function update(int $id, array $data, ?array $details = null): ?PurchaseRequest;
    public function delete(int $id): bool;
    public function updateStatus(int $id, string $status, ?int $userId = null): ?PurchaseRequest;
}
