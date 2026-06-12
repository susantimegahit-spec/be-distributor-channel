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
            $operator = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $operator) {
                $q->where('code_customer', $operator, "%{$search}%")
                  ->orWhere('name', $operator, "%{$search}%")
                  ->orWhere('address', $operator, "%{$search}%")
                  ->orWhere('mail_address', $operator, "%{$search}%")
                  ->orWhere('contact_person', $operator, "%{$search}%")
                  ->orWhere('sub_group', $operator, "%{$search}%")
                  ->orWhere('depo', $operator, "%{$search}%");
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
