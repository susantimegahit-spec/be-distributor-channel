<?php

namespace App\Modules\Warehouse\Repositories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

class WarehouseRepository implements WarehouseRepositoryInterface
{
    /**
     * Get all warehouses.
     *
     * @param  array  $filters
     * @return Collection<int, Warehouse>
     */
    public function getAll(array $filters = []): Collection
    {
        $query = Warehouse::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('whs_code', 'ilike', "%{$search}%")
                  ->orWhere('whs_name', 'ilike', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Create or update a warehouse by code.
     *
     * @param  array  $data
     * @return Warehouse
     */
    public function upsertByCode(array $data): Warehouse
    {
        return Warehouse::updateOrCreate(
            ['whs_code' => $data['whs_code']],
            $data
        );
    }
}
