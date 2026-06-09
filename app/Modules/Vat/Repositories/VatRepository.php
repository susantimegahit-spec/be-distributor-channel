<?php

namespace App\Modules\Vat\Repositories;

use App\Models\Vat;
use Illuminate\Database\Eloquent\Collection;

class VatRepository implements VatRepositoryInterface
{
    /**
     * Get all vats.
     *
     * @param  array  $filters
     * @return Collection<int, Vat>
     */
    public function getAll(array $filters = []): Collection
    {
        $query = Vat::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                  ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Create or update a vat by code.
     *
     * @param  array  $data
     * @return Vat
     */
    public function upsertByCode(array $data): Vat
    {
        return Vat::updateOrCreate(
            ['code' => $data['code']],
            $data
        );
    }
}
