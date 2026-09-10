<?php

namespace App\Modules\DistributorItemPrice\Repositories;

use App\Models\DistributorItemPrice;
use Illuminate\Database\Eloquent\Collection;

class DistributorItemPriceRepository implements DistributorItemPriceRepositoryInterface
{
    /**
     * Get all distributor item prices.
     *
     * @param  array  $filters
     * @return Collection<int, DistributorItemPrice>
     */
    public function getAll(array $filters = []): Collection
    {
        $query = DistributorItemPrice::query()
            ->select('distributor_item_prices.*', 'distributors.name as customer_name', 'distributors.depo as depo', 'items.item_name')
            ->leftJoin('distributors', 'distributor_item_prices.code_customer', '=', 'distributors.code_customer')
            ->leftJoin('items', 'distributor_item_prices.item_code', '=', 'items.item_code');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('distributor_item_prices.code_customer', 'ilike', "%{$search}%")
                  ->orWhere('distributors.name', 'ilike', "%{$search}%")
                  ->orWhere('distributor_item_prices.item_code', 'ilike', "%{$search}%")
                  ->orWhere('items.item_name', 'ilike', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Get distributor item price by ID.
     *
     * @param  int  $id
     * @return DistributorItemPrice|null
     */
    public function getById(int $id): ?DistributorItemPrice
    {
        return DistributorItemPrice::query()
            ->select('distributor_item_prices.*', 'distributors.name as customer_name', 'distributors.depo as depo', 'items.item_name')
            ->leftJoin('distributors', 'distributor_item_prices.code_customer', '=', 'distributors.code_customer')
            ->leftJoin('items', 'distributor_item_prices.item_code', '=', 'items.item_code')
            ->where('distributor_item_prices.id', $id)
            ->first();
    }

    /**
     * Create a new distributor item price.
     *
     * @param  array  $data
     * @return DistributorItemPrice
     */
    public function create(array $data): DistributorItemPrice
    {
        return DistributorItemPrice::create($data);
    }

    /**
     * Update an existing distributor item price.
     *
     * @param  DistributorItemPrice  $distributorItemPrice
     * @param  array  $data
     * @return DistributorItemPrice
     */
    public function update(DistributorItemPrice $distributorItemPrice, array $data): DistributorItemPrice
    {
        $distributorItemPrice->update($data);
        return $distributorItemPrice;
    }

    /**
     * Delete a distributor item price.
     *
     * @param  DistributorItemPrice  $distributorItemPrice
     * @return bool
     */
    public function delete(DistributorItemPrice $distributorItemPrice): bool
    {
        return $distributorItemPrice->delete();
    }
}
