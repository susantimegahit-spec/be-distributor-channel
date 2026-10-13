<?php

namespace App\Modules\SalesDistributor\Repositories;

use App\Models\SalesDistributorMapping;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SalesDistributorRepositoryInterface
{
    /**
     * Get all mappings with filters.
     *
     * @param  array  $filters
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = []): LengthAwarePaginator;

    /**
     * Get details of a mapping by ID.
     *
     * @param  int  $id
     * @return SalesDistributorMapping|null
     */
    public function getById(int $id): ?SalesDistributorMapping;

    /**
     * Create a new mapping.
     *
     * @param  array  $data
     * @return SalesDistributorMapping
     */
    public function create(array $data): SalesDistributorMapping;

    /**
     * Update an existing mapping.
     *
     * @param  SalesDistributorMapping  $mapping
     * @param  array  $data
     * @return SalesDistributorMapping
     */
    public function update(SalesDistributorMapping $mapping, array $data): SalesDistributorMapping;

    /**
     * Delete a mapping.
     *
     * @param  SalesDistributorMapping  $mapping
     * @return bool|null
     */
    public function delete(SalesDistributorMapping $mapping): ?bool;
}
