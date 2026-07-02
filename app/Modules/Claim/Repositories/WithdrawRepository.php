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
        $query = TrxProgramWithdraw::query();

        if (!empty($filters['customer_code'])) {
            $query->where('customer_code', $filters['customer_code']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
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
    public function updateStatus(int $id, string $status)
    {
        $withdraw = TrxProgramWithdraw::findOrFail($id);
        $withdraw->status = $status;
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
