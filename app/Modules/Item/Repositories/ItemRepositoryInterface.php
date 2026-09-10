<?php

namespace App\Modules\Item\Repositories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

interface ItemRepositoryInterface
{
    /**
     * Get all items.
     *
     * @param  array  $filters
     * @return Collection<int, Item>
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Create or update an item by code.
     *
     * @param  array  $data
     * @return Item
     */
    public function upsertByCode(array $data): Item;
}
