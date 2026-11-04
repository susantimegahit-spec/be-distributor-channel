<?php

namespace App\Modules\Vat\Repositories;

use App\Models\Vat;
use Illuminate\Database\Eloquent\Collection;

interface VatRepositoryInterface
{
    /**
     * Get all vats.
     *
     * @param  array  $filters
     * @return Collection<int, Vat>
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Create or update a vat by code.
     *
     * @param  array  $data
     * @return Vat
     */
    public function upsertByCode(array $data): Vat;
}
