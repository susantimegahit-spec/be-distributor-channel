<?php

namespace App\Modules\Claim\Repositories;

use App\Models\TrxProgramWithdraw;
use App\Models\TrxClaimBalanceLedger;
use Illuminate\Support\Facades\DB;

class WithdrawRepository implements WithdrawRepositoryInterface
{
    /**
     * @var TrxClaimBalanceLedgerRepositoryInterface
     */
    protected TrxClaimBalanceLedgerRepositoryInterface $ledgerRepository;

    /**
     * WithdrawRepository constructor.
     */
    public function __construct(TrxClaimBalanceLedgerRepositoryInterface $ledgerRepository)
    {
        $this->ledgerRepository = $ledgerRepository;
    }

    /**
     * Get paginated list of withdrawals.
     */
    public function getWithdrawsPaginated(array $filters, int $perPage = 15)
    {
        $query = TrxProgramWithdraw::query()
            ->leftJoin('distributors as d', 'd.code_customer', '=', 'trx_program_withdraw.customer_code')
            ->select([
                'trx_program_withdraw.*',
                'd.code_customer as code_customer',
                'd.name as customer_name',
                'd.name as name_customer',
                'd.depo as depo'
            ]);

        if (!empty($filters['customer_codes'])) {
            $query->whereIn('trx_program_withdraw.customer_code', $filters['customer_codes']);
        } elseif (!empty($filters['customer_code'])) {
            $query->where('trx_program_withdraw.customer_code', $filters['customer_code']);
        }

        if (!empty($filters['status'])) {
            $query->where('trx_program_withdraw.status', $filters['status']);
        }

        return $query->orderBy('trx_program_withdraw.created_at', 'desc')->paginate($perPage);
    }

    /**
     * Create a new withdrawal record.
     * When requested by customer, it creates a pending withdraw record in DB,
     * and also creates a pending ledger entry with credit = 0.00 (doesn't reduce balance yet).
     */
    public function createWithdraw(array $data)
    {
        return DB::transaction(function () use ($data) {
            $withdraw = TrxProgramWithdraw::create($data);

            $lines = is_string($withdraw->lines) ? json_decode($withdraw->lines, true) : $withdraw->lines;
            if (empty($lines)) {
                $lines = [
                    [
                        'batch_id' => $withdraw->batch_id,
                        'amount' => $withdraw->amount
                    ]
                ];
            }

            foreach ($lines as $line) {
                // Record in ledger with credit = line amount directly (distinguished by joined w.status = PENDING)
                $this->ledgerRepository->recordTransaction([
                    'customer_code'      => $withdraw->customer_code,
                    'ref_number'         => $withdraw->withdraw_no,
                    'batch_id'           => (int)$line['batch_id'],
                    'transaction_date'   => now()->toDateString(),
                    'type'               => 'WITHDRAW',
                    'debit'              => 0.00,
                    'credit'             => (float)$line['amount'],
                    'status'             => 'PENDING',
                    'description'        => "Pengajuan Penarikan Dana " . $withdraw->withdraw_no,
                    'referenceable_id'   => $withdraw->id,
                    'referenceable_type' => TrxProgramWithdraw::class,
                ]);
            }

            return $withdraw;
        });
    }

    /**
     * Update withdrawal status.
     * Resolves ID which can be either the direct withdrawal ID or the ledger record ID.
     */
    public function updateStatus(int $id, string $status, ?string $transferDate = null)
    {
        return DB::transaction(function () use ($id, $status, $transferDate) {
            // 1. Try to resolve and find the ledger entry first
            $ledger = TrxClaimBalanceLedger::find($id);

            if ($ledger && $ledger->referenceable_type === TrxProgramWithdraw::class) {
                // It is a specific ledger row approval!
                $ledger->update([
                    'status' => $status,
                    'credit' => $status === 'REJECTED' ? 0.00 : $ledger->credit,
                    'description' => $status === 'REJECTED' 
                        ? "Penarikan Dana Ditolak: " . ($ledger->ref_number ?: '')
                        : "Penarikan Dana " . ($ledger->ref_number ?: '')
                ]);

                // Update parent withdraw status if it exists and all lines are resolved
                if ($ledger->referenceable_id) {
                    $withdraw = TrxProgramWithdraw::find($ledger->referenceable_id);
                    if ($withdraw) {
                        if ($transferDate !== null) {
                            $withdraw->transfer_date = $transferDate;
                            $withdraw->save();
                        }
                        
                        $siblings = TrxClaimBalanceLedger::where('referenceable_type', TrxProgramWithdraw::class)
                            ->where('referenceable_id', $withdraw->id)
                            ->get();

                        $totalCount = $siblings->count();
                        $approvedCount = $siblings->where('status', 'APPROVED')->count();
                        $rejectedCount = $siblings->where('status', 'REJECTED')->count();

                        if ($approvedCount === $totalCount) {
                            $withdraw->update(['status' => 'APPROVED']);
                        } elseif ($rejectedCount === $totalCount) {
                            $withdraw->update(['status' => 'REJECTED']);
                        } elseif ($approvedCount + $rejectedCount === $totalCount) {
                            $withdraw->update(['status' => 'APPROVED']); // Mixed resolves to APPROVED
                        }
                    }
                }

                return $ledger->referenceable;
            }

            // 2. Fallback: If not found as ledger entry, check if it's a withdraw header ID
            $withdraw = TrxProgramWithdraw::find($id);

            if (!$withdraw) {
                // Try resolving via polymorphic relation in ledger table
                $ledgerRef = TrxClaimBalanceLedger::where('id', $id)
                    ->where('referenceable_type', TrxProgramWithdraw::class)
                    ->first();

                if ($ledgerRef && $ledgerRef->referenceable_id) {
                    $withdraw = TrxProgramWithdraw::find($ledgerRef->referenceable_id);
                }
            }

            if (!$withdraw) {
                // Try resolving via ref_number mapping
                $ledgerRef = TrxClaimBalanceLedger::find($id);
                if ($ledgerRef && $ledgerRef->ref_number) {
                    $withdraw = TrxProgramWithdraw::where('withdraw_no', $ledgerRef->ref_number)->first();
                }
            }

            // Fallback: If still not found, but it is a valid ledger entry of type WITHDRAW,
            // create the withdrawal request record on-the-fly so it can be approved successfully.
            if (!$withdraw) {
                $ledgerRef = TrxClaimBalanceLedger::find($id);
                if ($ledgerRef && $ledgerRef->type === 'WITHDRAW') {
                    $amount = (float)$ledgerRef->credit > 0 ? (float)$ledgerRef->credit : 0.00;

                    $withdraw = TrxProgramWithdraw::create([
                        'withdraw_no'   => $ledgerRef->ref_number ?: ('WD-' . date('Ymd') . '-' . str_pad($ledgerRef->id, 3, '0', STR_PAD_LEFT)),
                        'customer_code' => $ledgerRef->customer_code,
                        'batch_id'      => $ledgerRef->batch_id,
                        'lines'         => [['batch_id' => $ledgerRef->batch_id, 'amount' => $amount]],
                        'amount'        => $amount,
                        'status'        => 'PENDING',
                        'created_by'    => 'system_fallback',
                    ]);

                    $ledgerRef->update([
                        'referenceable_id'   => $withdraw->id,
                        'referenceable_type' => TrxProgramWithdraw::class,
                    ]);
                }
            }

            if (!$withdraw) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Withdrawal record not found for ID: {$id}");
            }

            $withdraw->status = $status;
            if ($transferDate !== null) {
                $withdraw->transfer_date = $transferDate;
            }
            $withdraw->save();

            // Update all ledger lines for this withdrawal
            TrxClaimBalanceLedger::where('referenceable_type', TrxProgramWithdraw::class)
                ->where('referenceable_id', $withdraw->id)
                ->update([
                    'status' => $status,
                    'credit' => $status === 'REJECTED' ? 0.00 : DB::raw('credit'),
                ]);

            return $withdraw;
        });
    }

    /**
     * Find a withdrawal by ID.
     */
    public function findById(int $id)
    {
        return TrxProgramWithdraw::find($id);
    }
}
