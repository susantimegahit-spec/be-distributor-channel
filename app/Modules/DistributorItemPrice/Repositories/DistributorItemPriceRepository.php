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

        if (!empty($filters['code_customer'])) {
            $codes = is_array($filters['code_customer']) ? $filters['code_customer'] : [$filters['code_customer']];
            $query->whereIn('distributor_item_prices.code_customer', $codes);
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $query->where('distributor_item_prices.status', $filters['status']);
        }

        if (!empty($filters['item_code'])) {
            $query->where('distributor_item_prices.item_code', $filters['item_code']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $likeOperator = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('distributor_item_prices.code_customer', $likeOperator, "%{$search}%")
                  ->orWhere('distributors.name', $likeOperator, "%{$search}%")
                  ->orWhere('distributor_item_prices.item_code', $likeOperator, "%{$search}%")
                  ->orWhere('items.item_name', $likeOperator, "%{$search}%");
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
