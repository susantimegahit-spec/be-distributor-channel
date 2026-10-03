<?php

namespace App\Modules\SalesReturn\Repositories;

use App\Models\SalesReturn;
use Illuminate\Database\Eloquent\Collection;

interface SalesReturnRepositoryInterface
{
    /**
     * Get all sales returns.
     *
     * @param  array  $filters
     * @return Collection<int, SalesReturn>
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Find a sales return by ID.
     *
     * @param  int  $id
     * @return SalesReturn|null
     */
    public function getById(int $id): ?SalesReturn;

    /**
     * Create a new sales return with detail lines and attachments.
     *
     * @param  array  $data
     * @return SalesReturn
     */
    public function create(array $data): SalesReturn;

    /**
     * Update an existing sales return.
     *
     * @param  SalesReturn  $salesReturn
     * @param  array  $data
     * @return SalesReturn
     */
    public function update(SalesReturn $salesReturn, array $data): SalesReturn;

    /**
     * Delete a sales return.
     *
     * @param  SalesReturn  $salesReturn
     * @return bool
     */
    public function delete(SalesReturn $salesReturn): bool;
}
