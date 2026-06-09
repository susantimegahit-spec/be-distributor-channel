<?php

namespace App\Modules\Distributor\Repositories;

use App\Models\Distributor;
use Illuminate\Database\Eloquent\Collection;

class DistributorRepository implements DistributorRepositoryInterface
{
    /**
     * Get all distributors.
     *
     * @param  array  $filters
     * @return Collection<int, Distributor>
     */
    public function getAll(array $filters = []): Collection
    {
        $query = Distributor::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code_customer', 'ilike', "%{$search}%")
                  ->orWhere('name', 'ilike', "%{$search}%")
                  ->orWhere('address', 'ilike', "%{$search}%")
                  ->orWhere('mail_address', 'ilike', "%{$search}%")
                  ->orWhere('contact_person', 'ilike', "%{$search}%")
                  ->orWhere('sub_group', 'ilike', "%{$search}%")
                  ->orWhere('depo', 'ilike', "%{$search}%");
            });
        }

        return $query->get();
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
