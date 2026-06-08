<?php

namespace App\Modules\SalesOrder\Repositories;

use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SalesOrderRepository implements SalesOrderRepositoryInterface
{
    /**
     * Get all sales orders.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        $query = SalesOrder::query()->with('details');

        if (!empty($filters['distributor_id'])) {
            $query->where('distributor_id', $filters['distributor_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Find a sales order by ID.
     *
     * @param  int  $id
     * @return SalesOrder|null
     */
    public function getById(int $id): ?SalesOrder
    {
        return SalesOrder::with('details')->find($id);
    }

    /**
     * Create a new sales order with its detail lines.
     *
     * @param  array  $data
     * @return SalesOrder
     */
    public function create(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data) {
            $lines = $data['lines'];
            unset($data['lines']);

            $salesOrder = SalesOrder::create($data);

            foreach ($lines as $line) {
                $salesOrder->details()->create($line);
            }

            return $salesOrder->load('details');
        });
    }

    /**
     * Update an existing sales order and its detail lines.
     *
     * @param  SalesOrder  $salesOrder
     * @param  array  $data
     * @return SalesOrder
     */
    public function update(SalesOrder $salesOrder, array $data): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder, $data) {
            $lines = $data['lines'];
            unset($data['lines']);

            $salesOrder->update($data);

            // Recreate lines (simplest way for draft updates)
            $salesOrder->details()->delete();
            foreach ($lines as $line) {
                $salesOrder->details()->create($line);
            }

            return $salesOrder->load('details');
        });
    }

    /**
     * Delete a sales order.
     *
     * @param  SalesOrder  $salesOrder
     * @return bool
     */
    public function delete(SalesOrder $salesOrder): bool
    {
        return DB::transaction(function () use ($salesOrder) {
            // cascading delete is handled by database foreign key cascade onDelete,
            // but we can also trigger it explicitly just in case.
            return $salesOrder->delete();
        });
    }
}
