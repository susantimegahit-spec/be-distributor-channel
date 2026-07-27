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

    /**
     * Get all production BOMs.
     *
     * @param  array  $filters
     * @return Collection<\App\Models\ProductionBom>
     */
    public function getAllBoms(array $filters = []): Collection;

    /**
     * Get production BOM by ID.
     *
     * @param  int  $id
     * @return \App\Models\ProductionBom|null
     */
    public function getBomById(int $id): ?\App\Models\ProductionBom;

    /**
     * Create a new production BOM.
     *
     * @param  array  $data
     * @return \App\Models\ProductionBom
     */
    public function createBom(array $data): \App\Models\ProductionBom;

    /**
     * Update an existing production BOM.
     *
     * @param  \App\Models\ProductionBom  $bom
     * @param  array  $data
     * @return \App\Models\ProductionBom
     */
    public function updateBom(\App\Models\ProductionBom $bom, array $data): \App\Models\ProductionBom;

    /**
     * Delete a production BOM.
     *
     * @param  \App\Models\ProductionBom  $bom
     * @return bool
     */
    public function deleteBom(\App\Models\ProductionBom $bom): bool;

    /**
     * Get all production orders.
     *
     * @param  array  $filters
     * @return Collection<int, \App\Models\ProductionOrder>
     */
    public function getAllOrders(array $filters = []): Collection;

    /**
     * Get production order by ID.
     *
     * @param  int  $id
     * @return \App\Models\ProductionOrder|null
     */
    public function getOrderById(int $id): ?\App\Models\ProductionOrder;

    /**
     * Create a new production order.
     *
     * @param  array  $data
     * @return \App\Models\ProductionOrder
     */
    public function createOrder(array $data): \App\Models\ProductionOrder;

    /**
     * Update an existing production order.
     *
     * @param  \App\Models\ProductionOrder  $order
     * @param  array  $data
     * @return \App\Models\ProductionOrder
     */
    public function updateOrder(\App\Models\ProductionOrder $order, array $data): \App\Models\ProductionOrder;

    /**
     * Delete a production order.
     *
     * @param  \App\Models\ProductionOrder  $order
     * @return bool
     */
    public function deleteOrder(\App\Models\ProductionOrder $order): bool;
}
