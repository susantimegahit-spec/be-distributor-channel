<?php

namespace App\Modules\Claim\Repositories;

use App\Models\TrxClaimBalanceLedger;
use Illuminate\Support\Facades\DB;

class TrxClaimBalanceLedgerRepository implements TrxClaimBalanceLedgerRepositoryInterface
{
    /**
     * Get paginated list of claim balance ledger entries.
     */
    public function getLedgerPaginated(array $filters, int $perPage = 15)
    {
        $query = TrxClaimBalanceLedger::query();

        if (!empty($filters['customer_codes'])) {
            $query->whereIn('customer_code', $filters['customer_codes']);
        } elseif (!empty($filters['customer_code'])) {
            $query->where('customer_code', $filters['customer_code']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('transaction_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('transaction_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->with(['distributor', 'uploadBatch'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Record a new ledger entry.
     * With on-the-fly summing, we no longer need to calculate or store a running balance.
     */
    public function recordTransaction(array $data)
    {
        return TrxClaimBalanceLedger::create($data);
    }

    /**
     * Update an existing CLAIM ledger entry by batch_id (finance verify flow).
     * With on-the-fly summing, we simply update the debit/credit values directly.
     * No delta calculation or cascading updates to later rows needed!
     * Falls back to inserting a new entry if no existing one is found.
     */
    public function updateOrRecordClaimByBatch(int $batchId, array $data)
    {
        return DB::transaction(function () use ($batchId, $data) {
            $customerCode = $data['customer_code'];

            // Try to find an existing CLAIM entry for this batch
            $existing = TrxClaimBalanceLedger::where('customer_code', $customerCode)
                ->where('batch_id', $batchId)
                ->where('type', 'CLAIM')
                ->lockForUpdate()
                ->orderBy('id', 'asc')
                ->first();

            if (!$existing) {
                return $this->recordTransaction($data);
            }

            // Directly update the fields
            $existing->update($data);

            return $existing->fresh();
        });
    }

    /**
     * Get current available balance for a customer.
     * Real-time calculation: SUM(debit) - SUM(credit)
     */
    public function getCurrentBalance(string $customerCode)
    {
        $sum = TrxClaimBalanceLedger::where('customer_code', $customerCode)
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->first();

        return $sum ? (float)$sum->balance : 0.00;
    }
}
