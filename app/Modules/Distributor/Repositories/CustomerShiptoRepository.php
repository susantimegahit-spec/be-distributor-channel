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
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(alias) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(city) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(street) LIKE ?', ["%{$search}%"]);
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
                'alias' => $data['alias'] ?? null,
                'city' => $data['city'] ?? null,
                'street' => $data['street'] ?? null,
                'transport_mode' => $data['transport_mode'] ?? null,
            ]
        );
    }
}
