<?php

namespace App\Modules\SalesOrder\Repositories;

use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Collection;

interface SalesOrderRepositoryInterface
{
    /**
     * Get all sales orders.
     *
     * @param  array  $filters
     * @return Collection<int, SalesOrder>
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Find a sales order by ID.
     *
     * @param  int  $id
     * @return SalesOrder|null
     */
    public function getById(int $id): ?SalesOrder;

    /**
     * Create a new sales order with its detail lines.
     *
     * @param  array  $data
     * @return SalesOrder
     */
    public function create(array $data): SalesOrder;

    /**
     * Update an existing sales order and its detail lines.
     *
     * @param  SalesOrder  $salesOrder
     * @param  array  $data
     * @return SalesOrder
     */
    public function update(SalesOrder $salesOrder, array $data): SalesOrder;

    /**
     * Delete a sales order.
     *
     * @param  SalesOrder  $salesOrder
     * @return bool
     */
    public function delete(SalesOrder $salesOrder): bool;
}
