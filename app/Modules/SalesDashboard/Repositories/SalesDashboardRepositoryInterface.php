<?php

namespace App\Modules\SalesDashboard\Repositories;

use App\Models\SalesDashboardData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SalesDashboardRepositoryInterface
{
    /**
     * Create or update a dashboard data record.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return SalesDashboardData
     */
    public function updateOrCreateRecord(array $attributes, array $values): SalesDashboardData;

    /**
     * Get paginated raw records with optional filters.
     *
     * @param  array  $filters
     * @param  int  $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Find a record by ID.
     *
     * @param  int  $id
     * @return SalesDashboardData|null
     */
    public function findById(int $id): ?SalesDashboardData;

    /**
     * Delete a record by ID.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Bulk update target_amount or cmo_amount to 0 for a specific month, year, and optionally customer.
     * If all amounts (target, cmo, so, do) become 0, the record will be deleted.
     *
     * @param  string  $type  'target'|'cmo'
     * @param  int  $month
     * @param  int  $year
     * @param  string|null  $customerCode
     * @return int  Number of records updated or deleted
     */
    public function bulkResetAmount(string $type, int $month, int $year, ?string $customerCode = null): int;

    /**
     * Get list of records for comparison dashboard.
     *
     * @param  int  $month
     * @param  int  $year
     * @param  array  $filters
     * @return Collection
     */
    public function getComparisonData(int $month, int $year, array $filters = []): Collection;
}
