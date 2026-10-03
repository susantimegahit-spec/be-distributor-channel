<?php

namespace App\Modules\Discount\Repositories;

use App\Models\DiscountType;
use Illuminate\Database\Eloquent\Collection;

interface DiscountTypeRepositoryInterface
{
    /**
     * Get all Discount Types with optional filters.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Create or update a Discount Type by fld_value.
     *
     * @param  array  $data
     * @return DiscountType
     */
    public function upsert(array $data): DiscountType;
}
