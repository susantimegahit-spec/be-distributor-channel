<?php

namespace App\Modules\SalesEmployee\Repositories;

use App\Models\SalesEmployee;
use Illuminate\Database\Eloquent\Collection;

interface SalesEmployeeRepositoryInterface
{
    /**
     * Get all sales employees.
     *
     * @param  array  $filters
     * @return Collection<int, SalesEmployee>
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Create or update a sales employee by code.
     *
     * @param  array  $data
     * @return SalesEmployee
     */
    public function upsertByCode(array $data): SalesEmployee;
}
