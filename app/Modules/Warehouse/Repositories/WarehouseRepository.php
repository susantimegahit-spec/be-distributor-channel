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
        $query = Warehouse::with('unit');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('whs_code', 'ilike', "%{$search}%")
                  ->orWhere('whs_name', 'ilike', "%{$search}%")
                  ->orWhereHas('unit', function ($uq) use ($search) {
                      $uq->where('unit_code', 'ilike', "%{$search}%")
                         ->orWhere('unit_name', 'ilike', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['master_unit_id'])) {
            $unitId = (string) $filters['master_unit_id'];
            $query->where(function ($q) use ($unitId) {
                $q->where('master_unit_id', $unitId)
                  ->orWhereHas('unit', function ($uq) use ($unitId) {
                      $uq->where('unit_code', $unitId);
                  });
            });
        }

        if (!empty($filters['unit_code'])) {
            $unitCode = (string) $filters['unit_code'];
            $query->where(function ($q) use ($unitCode) {
                $q->where('master_unit_id', $unitCode)
                  ->orWhereHas('unit', function ($uq) use ($unitCode) {
                      $uq->where('unit_code', $unitCode);
                  });
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = strtoupper((string) $filters['status']);
            if ($status === '1') $status = 'ACTIVE';
            if ($status === '0') $status = 'INACTIVE';
            $query->where('status', $status);
        }

        $sortBy = $filters['sort_by'] ?? 'whs_code';
        $sortDir = strtolower($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->get();
    }

    /**
     * Find a warehouse by ID.
     *
     * @param  int  $id
     * @return Warehouse|null
     */
    public function findById(int $id): ?Warehouse
    {
        return Warehouse::with('unit')->find($id);
    }

    /**
     * Create a new warehouse.
     *
     * @param  array  $data
     * @return Warehouse
     */
    public function create(array $data): Warehouse
    {
        if (!empty($data['master_unit_id']) && is_numeric($data['master_unit_id'])) {
            $unit = \App\Models\MasterUnit::find($data['master_unit_id']);
            if ($unit) {
                $data['master_unit_id'] = $unit->unit_code;
            }
        }

        $warehouse = Warehouse::create($data);
        return $warehouse->load('unit');
    }

    /**
     * Update an existing warehouse.
     *
     * @param  int  $id
     * @param  array  $data
     * @return Warehouse
     */
    public function update(int $id, array $data): Warehouse
    {
        if (isset($data['master_unit_id']) && is_numeric($data['master_unit_id'])) {
            $unit = \App\Models\MasterUnit::find($data['master_unit_id']);
            if ($unit) {
                $data['master_unit_id'] = $unit->unit_code;
            }
        }

        $warehouse = Warehouse::findOrFail($id);
        $warehouse->update($data);
        return $warehouse->fresh(['unit']);
    }

    /**
     * Delete a warehouse.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $warehouse = Warehouse::findOrFail($id);
        return (bool) $warehouse->delete();
    }

    /**
     * Create or update a warehouse by code (preserving existing master_unit_id).
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
