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
     * This method must handle running balance calculation and locking.
     */
    public function recordTransaction(array $data)
    {
        return DB::transaction(function () use ($data) {
            $customerCode = $data['customer_code'];
            $debit = (float)($data['debit'] ?? 0.00);
            $credit = (float)($data['credit'] ?? 0.00);

            // Fetch the last record for this customer, locking it to prevent race conditions
            $lastLedger = TrxClaimBalanceLedger::where('customer_code', $customerCode)
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            $previousBalance = $lastLedger ? (float)$lastLedger->running_balance : 0.00;
            $runningBalance = $previousBalance + $debit - $credit;

            $data['running_balance'] = $runningBalance;

            return TrxClaimBalanceLedger::create($data);
        });
    }

    /**
     * Update an existing CLAIM ledger entry by batch_id (finance verify flow).
     * Updates debit/credit and cascades running_balance to all subsequent rows.
     * Falls back to inserting a new entry if no existing one is found.
     */
    public function updateOrRecordClaimByBatch(int $batchId, array $data)
    {
        return DB::transaction(function () use ($batchId, $data) {
            $customerCode = $data['customer_code'];
            $newDebit  = (float)($data['debit']  ?? 0.00);
            $newCredit = (float)($data['credit'] ?? 0.00);

            // Try to find an existing CLAIM entry for this batch
            $existing = TrxClaimBalanceLedger::where('customer_code', $customerCode)
                ->where('batch_id', $batchId)
                ->where('type', 'CLAIM')
                ->lockForUpdate()
                ->orderBy('id', 'asc')
                ->first();

            if (!$existing) {
                // No entry yet — fall back to a normal insert
                return $this->recordTransaction($data);
            }

            $oldDebit  = (float)$existing->debit;
            $oldCredit = (float)$existing->credit;
            $delta = ($newDebit - $newCredit) - ($oldDebit - $oldCredit);

            // Update the existing row
            $existing->update(array_merge($data, [
                'debit'           => $newDebit,
                'credit'          => $newCredit,
                'running_balance' => (float)$existing->running_balance + $delta,
            ]));

            // Cascade the balance delta to all later rows of this customer
            if ($delta != 0) {
                TrxClaimBalanceLedger::where('customer_code', $customerCode)
                    ->where('id', '>', $existing->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->each(function ($row) use ($delta) {
                        $row->update(['running_balance' => (float)$row->running_balance + $delta]);
                    });
            }

            return $existing->fresh();
        });
    }

    /**
     * Get current available balance for a customer.
     */
    public function getCurrentBalance(string $customerCode)
    {
        $lastLedger = TrxClaimBalanceLedger::where('customer_code', $customerCode)
            ->orderBy('id', 'desc')
            ->first();

        return $lastLedger ? (float)$lastLedger->running_balance : 0.00;
    }
}
