<?php

namespace App\Modules\Warehouse\Repositories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

interface WarehouseRepositoryInterface
{
    /**
     * Get all warehouses.
     *
     * @param  array  $filters
     * @return Collection<int, Warehouse>
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Find a warehouse by ID.
     *
     * @param  int  $id
     * @return Warehouse|null
     */
    public function findById(int $id): ?Warehouse;

    /**
     * Create a new warehouse.
     *
     * @param  array  $data
     * @return Warehouse
     */
    public function create(array $data): Warehouse;

    /**
     * Update an existing warehouse.
     *
     * @param  int  $id
     * @param  array  $data
     * @return Warehouse
     */
    public function update(int $id, array $data): Warehouse;

    /**
     * Delete a warehouse.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Create or update a warehouse by code.
     *
     * @param  array  $data
     * @return Warehouse
     */
    public function upsertByCode(array $data): Warehouse;
}
