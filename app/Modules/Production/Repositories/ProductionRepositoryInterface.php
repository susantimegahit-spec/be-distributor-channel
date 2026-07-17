<?php

namespace App\Modules\Production\Repositories;

use App\Models\ProductionResource;
use App\Models\ProductionItem;
use Illuminate\Database\Eloquent\Collection;

interface ProductionRepositoryInterface
{
    /**
     * Get all production resources.
     *
     * @param  array  $filters
     * @return Collection<int, ProductionResource>
     */
    public function getAllResources(array $filters = []): Collection;

    /**
     * Create or update a production resource by code.
     *
     * @param  array  $data
     * @return ProductionResource
     */
    public function upsertResource(array $data): ProductionResource;

    /**
     * Get all production items.
     *
     * @param  array  $filters
     * @return Collection<int, ProductionItem>
     */
    public function getAllItems(array $filters = []): Collection;

    /**
     * Create or update a production item by code.
     *
     * @param  array  $data
     * @return ProductionItem
     */
    public function upsertItem(array $data): ProductionItem;
}
