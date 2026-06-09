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

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('item_code', 'ilike', "%{$search}%")
                  ->orWhere('item_name', 'ilike', "%{$search}%");
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
