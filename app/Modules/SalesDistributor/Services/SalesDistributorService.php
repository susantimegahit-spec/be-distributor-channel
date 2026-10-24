<?php

namespace App\Modules\SalesDistributor\Services;

use App\Modules\SalesDistributor\Repositories\SalesDistributorRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Models\SalesDistributorMapping;
use Illuminate\Pagination\LengthAwarePaginator;

class SalesDistributorService
{
    protected SalesDistributorRepositoryInterface $repository;
    protected AuditLogService $auditLogService;

    /**
     * SalesDistributorService constructor.
     *
     * @param  SalesDistributorRepositoryInterface  $repository
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(
        SalesDistributorRepositoryInterface $repository,
        AuditLogService $auditLogService
    ) {
        $this->repository = $repository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get all mappings.
     *
     * @param  array  $filters
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->getAll($filters);
    }

    /**
     * Get detail of a mapping.
     *
     * @param  int  $id
     * @return SalesDistributorMapping|null
     */
    public function getById(int $id): ?SalesDistributorMapping
    {
        return $this->repository->getById($id);
    }

    /**
     * Create a new mapping.
     *
     * @param  array  $data
     * @param  int|null  $userId
     * @return SalesDistributorMapping
     */
    public function create(array $data, ?int $userId = null): SalesDistributorMapping
    {
        $mapping = $this->repository->create($data);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'CREATE_SALES_DISTRIBUTOR_MAPPING',
                "Created sales-distributor mapping for Customer {$mapping->code_customer} and Sales {$mapping->slp_code}."
            );
        }

        return $mapping;
    }

    /**
     * Update an existing mapping.
     *
     * @param  SalesDistributorMapping  $mapping
     * @param  array  $data
     * @param  int|null  $userId
     * @return SalesDistributorMapping
     */
    public function update(SalesDistributorMapping $mapping, array $data, ?int $userId = null): SalesDistributorMapping
    {
        $updated = $this->repository->update($mapping, $data);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'UPDATE_SALES_DISTRIBUTOR_MAPPING',
                "Updated sales-distributor mapping ID {$mapping->id}."
            );
        }

        return $updated;
    }

    /**
     * Delete a mapping.
     *
     * @param  SalesDistributorMapping  $mapping
     * @param  int|null  $userId
     * @return bool
     */
    public function delete(SalesDistributorMapping $mapping, ?int $userId = null): bool
    {
        $deleted = $this->repository->delete($mapping);

        if ($deleted && $userId) {
            $this->auditLogService->log(
                $userId,
                'DELETE_SALES_DISTRIBUTOR_MAPPING',
                "Deleted sales-distributor mapping ID {$mapping->id} for Customer {$mapping->code_customer} and Sales {$mapping->slp_code}."
            );
        }

        return (bool) $deleted;
    }
}
