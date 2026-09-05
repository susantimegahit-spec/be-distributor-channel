<?php

namespace App\Modules\Item\Repositories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

class ItemRepository implements ItemRepositoryInterface
{
    /**
     * Get all items.
     *
     * @param  array  $filters
     * @return Collection<int, Item>
     */
    public function getAll(array $filters = []): Collection
    {
        $query = Item::query();

        if (!empty($filters['code_customer'])) {
            $codeCustomer = $filters['code_customer'];
            $query->join('distributor_item_prices', function ($join) use ($codeCustomer) {
                $join->on('items.item_code', '=', 'distributor_item_prices.item_code')
                     ->where('distributor_item_prices.code_customer', '=', $codeCustomer)
                     ->where('distributor_item_prices.status', '=', 1);
            })
            ->select('items.*', 'distributor_item_prices.price as price');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $likeOperator = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('items.item_code', $likeOperator, "%{$search}%")
                  ->orWhere('items.item_name', $likeOperator, "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Create or update an item by code.
     *
     * @param  array  $data
     * @return Item
     */
    public function upsertByCode(array $data): Item
    {
        return Item::updateOrCreate(
            ['item_code' => $data['item_code']],
            $data
        );
    }
}
