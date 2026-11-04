<?php

namespace App\Modules\Distributor\Repositories;

use App\Models\CustomerShipto;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerShiptoRepositoryInterface
{
    /**
     * Get paginated customer shiptos with filters.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Upsert a record by card_code and address.
     *
     * @param array $data
     * @return CustomerShipto
     */
    public function upsert(array $data): CustomerShipto;
}
