<?php

namespace App\Modules\Production\Repositories;

use App\Models\ProductionResource;
use App\Models\ProductionItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductionRepository implements ProductionRepositoryInterface
{
    /**
     * Get all production resources.
     *
     * @param  array  $filters
     * @return Collection<int, ProductionResource>
     */
    public function getAllResources(array $filters = []): Collection
    {
        $query = ProductionResource::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $likeOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('res_code', $likeOperator, "%{$search}%")
                  ->orWhere('res_name', $likeOperator, "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Create or update a production resource by code.
     *
     * @param  array  $data
     * @return ProductionResource
     */
    public function upsertResource(array $data): ProductionResource
    {
        return ProductionResource::updateOrCreate(
            ['res_code' => $data['res_code']],
            $data
        );
    }

    /**
     * Get all production items.
     *
     * @param  array  $filters
     * @return Collection<int, ProductionItem>
     */
    public function getAllItems(array $filters = []): Collection
    {
        $query = ProductionItem::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $likeOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('item_code', $likeOperator, "%{$search}%")
                  ->orWhere('item_name', $likeOperator, "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Create or update a production item by code.
     *
     * @param  array  $data
     * @return ProductionItem
     */
    public function upsertItem(array $data): ProductionItem
    {
        return ProductionItem::updateOrCreate(
            ['item_code' => $data['item_code']],
            $data
        );
    }
}
