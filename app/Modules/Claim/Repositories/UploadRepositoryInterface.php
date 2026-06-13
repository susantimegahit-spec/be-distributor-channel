<?php

namespace App\Modules\Claim\Repositories;

interface UploadRepositoryInterface
{
    /**
     * Create a new upload batch.
     *
     * @param array $data
     * @return \App\Models\TrxProgramUploadBatch
     */
    public function createBatch(array $data);

    /**
     * Bulk insert parsed upload rows.
     *
     * @param array $rows
     * @return bool
     */
    public function insertUploadRows(array $rows);

    /**
     * Get paginated list of batches.
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getBatchesPaginated(int $perPage = 15);

    /**
     * Find a batch by ID with calculated summary stats.
     *
     * @param int $id
     * @return array|null
     */
    public function findBatchWithSummary(int $id);
}
