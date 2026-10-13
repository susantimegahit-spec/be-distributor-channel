<?php

namespace App\Modules\MasterUnit\Repositories;

use App\Models\MasterUnit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MasterUnitRepositoryInterface
{
    /**
     * Get all master units with optional filters.
     *
     * @param  array  $filters
     * @return Collection<int, MasterUnit>|LengthAwarePaginator
     */
    public function getAll(array $filters = []): Collection|LengthAwarePaginator;

    /**
     * Find a master unit by ID.
     *
     * @param  int  $id
     * @return MasterUnit|null
     */
    public function findById(int $id): ?MasterUnit;

    /**
     * Find a master unit by unit_code.
     *
     * @param  string  $code
     * @return MasterUnit|null
     */
    public function findByCode(string $code): ?MasterUnit;

    /**
     * Create a new master unit.
     *
     * @param  array  $data
     * @return MasterUnit
     */
    public function create(array $data): MasterUnit;

    /**
     * Update an existing master unit.
     *
     * @param  int  $id
     * @param  array  $data
     * @return MasterUnit
     */
    public function update(int $id, array $data): MasterUnit;

    /**
     * Delete a master unit.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool;
}
