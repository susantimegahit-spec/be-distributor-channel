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
     * Create or update a warehouse by code.
     *
     * @param  array  $data
     * @return Warehouse
     */
    public function upsertByCode(array $data): Warehouse;
}
