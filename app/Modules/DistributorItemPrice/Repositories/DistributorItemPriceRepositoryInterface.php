<?php

namespace App\Modules\DistributorItemPrice\Repositories;

use App\Models\DistributorItemPrice;
use Illuminate\Database\Eloquent\Collection;

interface DistributorItemPriceRepositoryInterface
{
    /**
     * Get all distributor item prices.
     *
     * @param  array  $filters
     * @return Collection<int, DistributorItemPrice>
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Get distributor item price by ID.
     *
     * @param  int  $id
     * @return DistributorItemPrice|null
     */
    public function getById(int $id): ?DistributorItemPrice;

    /**
     * Create a new distributor item price.
     *
     * @param  array  $data
     * @return DistributorItemPrice
     */
    public function create(array $data): DistributorItemPrice;

    /**
     * Update an existing distributor item price.
     *
     * @param  DistributorItemPrice  $distributorItemPrice
     * @param  array  $data
     * @return DistributorItemPrice
     */
    public function update(DistributorItemPrice $distributorItemPrice, array $data): DistributorItemPrice;

    /**
     * Delete a distributor item price.
     *
     * @param  DistributorItemPrice  $distributorItemPrice
     * @return bool
     */
    public function delete(DistributorItemPrice $distributorItemPrice): bool;
}
