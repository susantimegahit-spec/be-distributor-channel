<?php

namespace App\Modules\Distributor\Repositories;

use App\Models\Distributor;
use Illuminate\Database\Eloquent\Collection;

class DistributorRepository implements DistributorRepositoryInterface
{
    /**
     * Get all distributors.
     *
     * @return Collection<int, Distributor>
     */
    public function getAll(): Collection
    {
        return Distributor::all();
    }

    /**
     * Find distributor by ID.
     *
     * @param  int  $id
     * @return Distributor|null
     */
    public function getById(int $id): ?Distributor
    {
        return Distributor::find($id);
    }

    /**
     * Create or update a distributor by code.
     *
     * @param  array  $data
     * @return Distributor
     */
    public function upsertByCode(array $data): Distributor
    {
        return Distributor::updateOrCreate(
            ['code_customer' => $data['code_customer']],
            $data
        );
    }
}
