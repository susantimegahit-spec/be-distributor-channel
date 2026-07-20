<?php

namespace App\Modules\SalesDashboard\Repositories;

use App\Models\SalesDashboardData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SalesDashboardRepository implements SalesDashboardRepositoryInterface
{
    /**
     * Create or update a dashboard data record.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return SalesDashboardData
     */
    public function updateOrCreateRecord(array $attributes, array $values): SalesDashboardData
    {
        return SalesDashboardData::updateOrCreate($attributes, $values);
    }

    /**
     * Get paginated raw records with optional filters.
     *
     * @param  array  $filters
     * @param  int  $perPage
     * @return LengthAwarePaginator
     */
    /**
     * Get raw records with optional filters.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getRawData(array $filters = []): Collection
    {
        $query = SalesDashboardData::query();

        if (!empty($filters['customer_code'])) {
            $custCodes = array_map('trim', explode(',', $filters['customer_code']));
            if (count($custCodes) > 1) {
                $query->whereIn('customer_code', $custCodes);
            } else {
                $query->where('customer_code', $custCodes[0]);
            }
        }

        if (!empty($filters['brand'])) {
            $query->where('brand', $filters['brand']);
        }

        if (!empty($filters['month'])) {
            $query->where('month', (int) $filters['month']);
        }

        if (!empty($filters['year'])) {
            $query->where('year', (int) $filters['year']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('customer_code', 'like', $search)
                  ->orWhere('customer_name', 'like', $search)
                  ->orWhere('brand', 'like', $search)
                  ->orWhere('depo', 'like', $search);
            });
        }

        return $query->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('customer_code', 'asc')
            ->orderBy('brand', 'asc')
            ->get();
    }

    /**
     * Find a record by ID.
     *
     * @param  int  $id
     * @return SalesDashboardData|null
     */
    public function findById(int $id): ?SalesDashboardData
    {
        return SalesDashboardData::find($id);
    }

    /**
     * Delete a record by ID.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $record = $this->findById($id);
        if ($record) {
            return $record->delete();
        }
        return false;
    }

    /**
     * Bulk update target_amount or cmo_amount to 0 for a specific month, year, and optionally customer.
     * If all amounts (target, cmo, so, do) become 0, the record will be deleted.
     *
     * @param  string  $type  'target'|'cmo'
     * @param  int  $month
     * @param  int  $year
     * @param  string|null  $customerCode
     * @return int  Number of records updated or deleted
     */
    public function bulkResetAmount(string $type, int $month, int $year, ?string $customerCode = null): int
    {
        return DB::transaction(function () use ($type, $month, $year, $customerCode) {
            $query = SalesDashboardData::where('month', $month)
                ->where('year', $year);

            if ($customerCode !== null) {
                $query->where('customer_code', $customerCode);
            }

            $records = $query->get();
            $count = 0;

            foreach ($records as $record) {
                if ($type === 'target') {
                    $record->target_amount = 0.00;
                } elseif ($type === 'cmo') {
                    $record->cmo_amount = 0.00;
                }

                if (
                    (float) $record->target_amount === 0.00 &&
                    (float) $record->cmo_amount === 0.00 &&
                    (float) $record->so_amount === 0.00 &&
                    (float) $record->do_amount === 0.00
                ) {
                    $record->delete();
                } else {
                    $record->save();
                }
                $count++;
            }

            return $count;
        });
    }

    /**
     * Get list of records for comparison dashboard.
     *
     * @param  int  $month
     * @param  int  $year
     * @param  array  $filters
     * @return Collection
     */
    public function getComparisonData(int $month, int $year, array $filters = []): Collection
    {
        $query = SalesDashboardData::where('month', $month)
            ->where('year', $year);

        if (!empty($filters['customer_code'])) {
            $query->where('customer_code', $filters['customer_code']);
        }

        if (!empty($filters['item_code'])) {
            $query->where('item_code', $filters['item_code']);
        }

        return $query->orderBy('customer_code', 'asc')
            ->orderBy('item_code', 'asc')
            ->get();
    }

    /**
     * Update a record by ID.
     *
     * @param  int  $id
     * @param  array  $data
     * @return SalesDashboardData|null
     */
    public function update(int $id, array $data): ?SalesDashboardData
    {
        $record = $this->findById($id);
        if ($record) {
            $record->update($data);
            return $record->fresh();
        }
        return null;
    }
}
