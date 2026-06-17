<?php

namespace App\Modules\SalesDistributor\Repositories;

use App\Models\SalesDistributorMapping;
use Illuminate\Pagination\LengthAwarePaginator;

class SalesDistributorRepository implements SalesDistributorRepositoryInterface
{
    /**
     * Get all mappings with filters.
     *
     * @param  array  $filters
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = SalesDistributorMapping::query()
            ->with(['distributor', 'salesEmployee']);

        if (!empty($filters['code_customer'])) {
            $query->where('code_customer', $filters['code_customer']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code_customer', 'like', "%{$search}%")
                  ->orWhereHas('distributor', function ($sub) use ($search) {
                      $sub->where('name', 'ilike', "%{$search}%");
                  })
                  ->orWhereHas('salesEmployee', function ($sub) use ($search) {
                      $sub->where('slp_name', 'ilike', "%{$search}%");
                  });
            });
        }

        $perPage = !empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get details of a mapping by ID.
     *
     * @param  int  $id
     * @return SalesDistributorMapping|null
     */
    public function getById(int $id): ?SalesDistributorMapping
    {
        return SalesDistributorMapping::with(['distributor', 'salesEmployee'])->find($id);
    }

    /**
     * Create a new mapping.
     *
     * @param  array  $data
     * @return SalesDistributorMapping
     */
    public function create(array $data): SalesDistributorMapping
    {
        return SalesDistributorMapping::create($data);
    }

    /**
     * Update an existing mapping.
     *
     * @param  SalesDistributorMapping  $mapping
     * @param  array  $data
     * @return SalesDistributorMapping
     */
    public function update(SalesDistributorMapping $mapping, array $data): SalesDistributorMapping
    {
        $mapping->update($data);
        return $mapping;
    }

    /**
     * Delete a mapping.
     *
     * @param  SalesDistributorMapping  $mapping
     * @return bool|null
     */
    public function delete(SalesDistributorMapping $mapping): ?bool
    {
        return $mapping->delete();
    }
}
