<?php

namespace App\Modules\Claim\Repositories;

interface WithdrawRepositoryInterface
{
    /**
     * Get paginated list of withdrawals.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getWithdrawsPaginated(array $filters, int $perPage = 15);

    /**
     * Create a new withdrawal record.
     *
     * @param array $data
     * @return \App\Models\TrxProgramWithdraw
     */
    public function createWithdraw(array $data);

    /**
     * Update withdrawal status.
     *
     * @param int $id
     * @param string $status
     * @return \App\Models\TrxProgramWithdraw
     */
    public function updateStatus(int $id, string $status);

    /**
     * Find a withdrawal by ID.
     *
     * @param int $id
     * @return \App\Models\TrxProgramWithdraw|null
     */
    public function findById(int $id);
}
