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

    /**
     * Get report grouped by depo.
     *
     * @param  array  $filters
     * @return array
     */
    public function getReportByDepo(array $filters = []): array;

    /**
     * Get report grouped by year / month.
     *
     * @param  array  $filters
     * @return array
     */
    public function getReportByYear(array $filters = []): array;

    /**
     * Get detailed monthly report grouped by depo, item, and brand.
     *
     * @param  array  $filters
     * @return array
     */
    public function getReportDetailed(array $filters = []): array;
}
