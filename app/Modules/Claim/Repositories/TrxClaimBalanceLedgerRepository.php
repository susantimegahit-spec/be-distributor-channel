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

        return $query->orderBy('transaction_date', 'desc')
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
