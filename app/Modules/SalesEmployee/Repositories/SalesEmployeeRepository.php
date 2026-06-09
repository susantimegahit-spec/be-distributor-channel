<?php

namespace App\Modules\SalesEmployee\Repositories;

use App\Models\SalesEmployee;
use Illuminate\Database\Eloquent\Collection;

class SalesEmployeeRepository implements SalesEmployeeRepositoryInterface
{
    /**
     * Get all sales employees.
     *
     * @param  array  $filters
     * @return Collection<int, SalesEmployee>
     */
    public function getAll(array $filters = []): Collection
    {
        $query = SalesEmployee::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('slp_code', 'like', "%{$search}%")
                  ->orWhere('slp_name', 'ilike', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Create or update a sales employee by code.
     *
     * @param  array  $data
     * @return SalesEmployee
     */
    public function upsertByCode(array $data): SalesEmployee
    {
        return SalesEmployee::updateOrCreate(
            ['slp_code' => $data['slp_code']],
            $data
        );
    }
}
