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
     * Get all results without pagination.
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getResults(array $filters);

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
     * @param string|null $claimType
     * @return int
     */
    public function verifyResults(array $ids, bool $status, ?string $claimType = null);

    /**
     * Get reward summary statistics (claimed, verified, withdrawn, balance).
     *
     * @param array|string|null $customerCodes
     * @return array
     */
    public function getRewardSummary($customerCodes = null);
}
