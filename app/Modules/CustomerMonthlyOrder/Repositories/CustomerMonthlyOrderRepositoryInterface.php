<?php

namespace App\Modules\CustomerMonthlyOrder\Repositories;

use App\Models\CustomerMonthlyOrder;
use Illuminate\Database\Eloquent\Collection;

interface CustomerMonthlyOrderRepositoryInterface
{
    /**
     * Get all customer monthly orders.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Get customer monthly order by ID.
     *
     * @param  int  $id
     * @return CustomerMonthlyOrder|null
     */
    public function getById(int $id): ?CustomerMonthlyOrder;

    /**
     * Create a new customer monthly order.
     *
     * @param  array  $data
     * @return CustomerMonthlyOrder
     */
    public function create(array $data): CustomerMonthlyOrder;

    /**
     * Update an existing customer monthly order.
     *
     * @param  CustomerMonthlyOrder  $order
     * @param  array  $data
     * @return CustomerMonthlyOrder
     */
    public function update(CustomerMonthlyOrder $order, array $data): CustomerMonthlyOrder;

    /**
     * Delete a customer monthly order.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool;
}
