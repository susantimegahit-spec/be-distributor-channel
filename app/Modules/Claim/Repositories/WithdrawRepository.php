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
     */
    public function createWithdraw(array $data)
    {
        return DB::transaction(function () use ($data) {
            $withdraw = TrxProgramWithdraw::create($data);

            // Record in ledger
            $this->ledgerRepository->recordTransaction([
                'customer_code' => $withdraw->customer_code,
                'ref_number' => $withdraw->withdraw_no,
                'transaction_date' => now()->toDateString(),
                'type' => 'WITHDRAW',
                'debit' => 0.00,
                'credit' => $withdraw->amount,
                'description' => "Pengajuan Penarikan Dana " . $withdraw->withdraw_no,
                'referenceable_id' => $withdraw->id,
                'referenceable_type' => TrxProgramWithdraw::class,
            ]);

            return $withdraw;
        });
    }

    /**
     * Update withdrawal status.
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

            // If status is rejected, refund the balance
            if ($status === 'REJECTED' && $oldStatus !== 'REJECTED') {
                $this->ledgerRepository->recordTransaction([
                    'customer_code' => $withdraw->customer_code,
                    'ref_number' => $withdraw->withdraw_no,
                    'transaction_date' => now()->toDateString(),
                    'type' => 'CORRECTION',
                    'debit' => $withdraw->amount,
                    'credit' => 0.00,
                    'description' => "Pengembalian dana penarikan ditolak: " . $withdraw->withdraw_no,
                    'referenceable_id' => $withdraw->id,
                    'referenceable_type' => TrxProgramWithdraw::class,
                ]);
            }
            // If status was rejected and transitions back (unlikely but possible)
            elseif ($oldStatus === 'REJECTED' && $status !== 'REJECTED') {
                $this->ledgerRepository->recordTransaction([
                    'customer_code' => $withdraw->customer_code,
                    'ref_number' => $withdraw->withdraw_no,
                    'transaction_date' => now()->toDateString(),
                    'type' => 'WITHDRAW',
                    'debit' => 0.00,
                    'credit' => $withdraw->amount,
                    'description' => "Pengajuan kembali Penarikan Dana " . $withdraw->withdraw_no,
                    'referenceable_id' => $withdraw->id,
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
