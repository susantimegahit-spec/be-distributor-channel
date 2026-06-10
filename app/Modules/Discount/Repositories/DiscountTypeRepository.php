<?php

namespace App\Modules\Discount\Repositories;

use App\Models\DiscountType;
use Illuminate\Database\Eloquent\Collection;

class DiscountTypeRepository implements DiscountTypeRepositoryInterface
{
    /**
     * Get all Discount Types with optional filters.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        $query = DiscountType::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('fld_value', 'ilike', "%{$search}%")
                  ->orWhere('descr', 'ilike', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Create or update a Discount Type by fld_value.
     *
     * @param  array  $data
     * @return DiscountType
     */
    public function upsert(array $data): DiscountType
    {
        return DiscountType::updateOrCreate(
            [
                'fld_value' => $data['fld_value'],
            ],
            [
                'descr' => $data['descr'],
                'status' => $data['status'] ?? 1,
            ]
        );
    }
}
