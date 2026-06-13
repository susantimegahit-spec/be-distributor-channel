<?php

namespace App\Modules\Claim\Repositories;

interface ResultRepositoryInterface
{
    /**
     * Bulk insert calculation results.
     *
     * @param array $results
     * @return bool
     */
    public function insertResults(array $results);

    /**
     * Get paginated results with optional filters.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateResults(array $filters, int $perPage = 15);

    /**
     * Get overall summary statistics for dashboard.
     *
     * @return array
     */
    public function getDashboardSummary();
}
