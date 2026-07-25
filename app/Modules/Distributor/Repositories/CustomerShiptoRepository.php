<?php

namespace App\Modules\Distributor\Repositories;

use App\Models\CustomerShipto;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerShiptoRepository implements CustomerShiptoRepositoryInterface
{
    /**
     * Get paginated customer shiptos with filters.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CustomerShipto::query()->with('distributor');

        if (!empty($filters['card_code'])) {
            $query->where('card_code', $filters['card_code']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('street', 'like', "%{$search}%");
            });
        }

        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Upsert a record by card_code and address.
     *
     * @param array $data
     * @return CustomerShipto
     */
    public function upsert(array $data): CustomerShipto
    {
        return CustomerShipto::updateOrCreate(
            [
                'card_code' => $data['card_code'],
                'address' => $data['address'],
            ],
            [
                'name' => $data['name'] ?? null,
                'city' => $data['city'] ?? null,
                'street' => $data['street'] ?? null,
            ]
        );
    }
}
