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

        if (isset($filters['sales_item_status']) && $filters['sales_item_status'] !== '') {
            $query->where('items.sell_item', strtoupper(trim((string)$filters['sales_item_status'])));
        }

        if (isset($filters['purchase_item_status']) && $filters['purchase_item_status'] !== '') {
            $query->where('items.prchse_item', strtoupper(trim((string)$filters['purchase_item_status'])));
        }

        if (isset($filters['inventory_item_status']) && $filters['inventory_item_status'] !== '') {
            $query->where('items.invnt_item', strtoupper(trim((string)$filters['inventory_item_status'])));
        }

        if (isset($filters['itms_grp_cod']) && $filters['itms_grp_cod'] !== '') {
            $query->where('items.itms_grp_cod', trim((string)$filters['itms_grp_cod']));
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

    /**
     * Bulk create or update items.
     *
     * @param  array  $rows
     * @return int
     */
    public function upsertBatch(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $count = 0;
        $chunks = array_chunk($rows, 500);
        foreach ($chunks as $chunk) {
            Item::upsert(
                $chunk,
                ['item_code'],
                [
                    'item_name',
                    'suom_entry',
                    'sal_unit_msr',
                    'per_kg',
                    'iuom_entry',
                    'invntry_uom',
                    'puom_entry',
                    'pur_pack_msr',
                    'prchse_item',
                    'sell_item',
                    'invnt_item',
                    'itms_grp_cod',
                    'brand',
                    'status',
                    'updated_at',
                ]
            );
            $count += count($chunk);
        }

        return $count;
    }
}
