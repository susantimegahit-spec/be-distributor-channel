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
            $itemCodes = \App\Models\DistributorItemPrice::where('code_customer', $filters['code_customer'])
                ->where('status', 1)
                ->pluck('item_code');
            $query->whereIn('item_code', $itemCodes);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $likeOperator = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('item_code', $likeOperator, "%{$search}%")
                  ->orWhere('item_name', $likeOperator, "%{$search}%");
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
