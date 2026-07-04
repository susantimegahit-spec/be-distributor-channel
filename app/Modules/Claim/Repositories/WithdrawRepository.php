<?php

namespace App\Modules\Claim\Repositories;

use App\Models\TrxProgramWithdraw;

class WithdrawRepository implements WithdrawRepositoryInterface
{
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
        return TrxProgramWithdraw::create($data);
    }

    /**
     * Update withdrawal status.
     */
    public function updateStatus(int $id, string $status, ?string $transferDate = null)
    {
        $withdraw = TrxProgramWithdraw::findOrFail($id);
        $withdraw->status = $status;
        if ($transferDate !== null) {
            $withdraw->transfer_date = $transferDate;
        }
        $withdraw->save();
        
        return $withdraw;
    }

    /**
     * Find a withdrawal by ID.
     */
    public function findById(int $id)
    {
        return TrxProgramWithdraw::find($id);
    }
}
