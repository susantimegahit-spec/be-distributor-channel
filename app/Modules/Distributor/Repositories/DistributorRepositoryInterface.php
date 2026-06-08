<?php

namespace App\Modules\Distributor\Repositories;

use App\Models\Distributor;
use Illuminate\Database\Eloquent\Collection;

interface DistributorRepositoryInterface
{
    /**
     * Get all distributors.
     *
     * @return Collection<int, Distributor>
     */
    public function getAll(): Collection;

    /**
     * Find distributor by ID.
     *
     * @param  int  $id
     * @return Distributor|null
     */
    public function getById(int $id): ?Distributor;

    /**
     * Create or update a distributor by code.
     *
     * @param  array  $data
     * @return Distributor
     */
    public function upsertByCode(array $data): Distributor;
}
