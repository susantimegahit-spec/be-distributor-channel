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
     * Get current available balance for a customer.
     *
     * @param string $customerCode
     * @return float
     */
    public function getCurrentBalance(string $customerCode);
}
