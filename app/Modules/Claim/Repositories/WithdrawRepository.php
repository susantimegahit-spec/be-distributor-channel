<?php

namespace App\Modules\Claim\Repositories;

use App\Models\TrxProgramWithdraw;
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
     * When requested by customer, it defaults to PENDING and does not record credit in ledger yet.
     */
    public function createWithdraw(array $data)
    {
        return TrxProgramWithdraw::create($data);
    }

    /**
     * Update withdrawal status.
     * Transaction is only recorded as credit in the ledger when status transitions to APPROVED.
     */
    public function updateStatus(int $id, string $status, ?string $transferDate = null)
    {
        return DB::transaction(function () use ($id, $status, $transferDate) {
            $withdraw = TrxProgramWithdraw::findOrFail($id);
            $oldStatus = $withdraw->status;

            $withdraw->status = $status;
            if ($transferDate !== null) {
                $withdraw->transfer_date = $transferDate;
            }
            $withdraw->save();

            // 1. Record credit in ledger only when approved
            if ($status === 'APPROVED' && $oldStatus !== 'APPROVED') {
                $this->ledgerRepository->recordTransaction([
                    'customer_code'      => $withdraw->customer_code,
                    'ref_number'         => $withdraw->withdraw_no,
                    'transaction_date'   => now()->toDateString(),
                    'type'               => 'WITHDRAW',
                    'debit'              => 0.00,
                    'credit'             => $withdraw->amount,
                    'description'        => "Penarikan Dana " . $withdraw->withdraw_no,
                    'referenceable_id'   => $withdraw->id,
                    'referenceable_type' => TrxProgramWithdraw::class,
                ]);
            }

            // 2. If it was already approved and somehow gets rejected, refund/reverse the credit
            if ($status === 'REJECTED' && $oldStatus === 'APPROVED') {
                $this->ledgerRepository->recordTransaction([
                    'customer_code'      => $withdraw->customer_code,
                    'ref_number'         => $withdraw->withdraw_no,
                    'transaction_date'   => now()->toDateString(),
                    'type'               => 'CORRECTION',
                    'debit'              => $withdraw->amount,
                    'credit'             => 0.00,
                    'description'        => "Pengembalian dana penarikan ditolak: " . $withdraw->withdraw_no,
                    'referenceable_id'   => $withdraw->id,
                    'referenceable_type' => TrxProgramWithdraw::class,
                ]);
            }

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
