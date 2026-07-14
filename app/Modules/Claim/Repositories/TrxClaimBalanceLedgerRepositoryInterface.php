<?php

namespace App\Modules\Claim\Repositories;

interface TrxClaimBalanceLedgerRepositoryInterface
{
    /**
     * Get paginated list of claim balance ledger entries.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getLedgerPaginated(array $filters, int $perPage = 15);

    /**
     * Record a new ledger entry.
     * This method must handle running balance calculation and locking.
     *
     * @param array $data
     * @return \App\Models\TrxClaimBalanceLedger
     */
    public function recordTransaction(array $data);

    /**
     * Update an existing CLAIM ledger entry by batch_id (when finance verifies).
     * If no existing entry is found, a new one will be inserted.
     *
     * @param int $batchId
     * @param array $data
     * @return \App\Models\TrxClaimBalanceLedger
     */
    public function updateOrRecordClaimByBatch(int $batchId, array $data);

    /**
     * Get current available balance for a customer.
     *
     * @param string $customerCode
     * @return float
     */
    public function getCurrentBalance(string $customerCode);
}
