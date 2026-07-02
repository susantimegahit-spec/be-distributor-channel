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

    /**
     * Bulk verify result records.
     *
     * @param array $ids
     * @param bool $status
     * @return int
     */
    public function verifyResults(array $ids, bool $status);

    /**
     * Get reward summary statistics (claimed, verified, withdrawn, balance).
     *
     * @param string|null $customerCode
     * @return array
     */
    public function getRewardSummary(string $customerCode = null);
}
