<?php

namespace App\Modules\MasterUnit\Repositories;

use App\Models\MasterUnit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MasterUnitRepository implements MasterUnitRepositoryInterface
{
    /**
     * Get all master units with optional filters.
     *
     * @param  array  $filters
     * @return Collection<int, MasterUnit>|LengthAwarePaginator
     */
    public function getAll(array $filters = []): Collection|LengthAwarePaginator
    {
        $query = MasterUnit::query()->withCount('warehouses');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('unit_code', 'ilike', "%{$search}%")
                  ->orWhere('unit_name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortDir = strtolower($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        if (!empty($filters['per_page'])) {
            $perPage = (int) $filters['per_page'];
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Find a master unit by ID.
     *
     * @param  int  $id
     * @return MasterUnit|null
     */
    public function findById(int $id): ?MasterUnit
    {
        return MasterUnit::with('warehouses')->find($id);
    }

    /**
     * Find a master unit by unit_code.
     *
     * @param  string  $code
     * @return MasterUnit|null
     */
    public function findByCode(string $code): ?MasterUnit
    {
        return MasterUnit::where('unit_code', $code)->first();
    }

    /**
     * Create a new master unit.
     *
     * @param  array  $data
     * @return MasterUnit
     */
    public function create(array $data): MasterUnit
    {
        return MasterUnit::create($data);
    }

    /**
     * Update an existing master unit.
     *
     * @param  int  $id
     * @param  array  $data
     * @return MasterUnit
     */
    public function update(int $id, array $data): MasterUnit
    {
        $unit = MasterUnit::findOrFail($id);
        $unit->update($data);
        return $unit->fresh(['warehouses']);
    }

    /**
     * Delete a master unit.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $unit = MasterUnit::findOrFail($id);
        return (bool) $unit->delete();
    }
}
