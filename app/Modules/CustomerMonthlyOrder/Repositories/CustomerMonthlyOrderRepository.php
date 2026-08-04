<?php

namespace App\Modules\CustomerMonthlyOrder\Repositories;

use App\Models\CustomerMonthlyOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CustomerMonthlyOrderRepository implements CustomerMonthlyOrderRepositoryInterface
{
    /**
     * Get all customer monthly orders with filters.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        $query = CustomerMonthlyOrder::query()
            ->with(['details.item', 'details.warehouse', 'details.vat', 'details.ocr', 'details.ocr2', 'details.ocr3', 'salesEmployee', 'sapDiscount.details', 'attachments', 'distributor']);

        if (!empty($filters['distributor_id'])) {
            if (is_array($filters['distributor_id'])) {
                $query->whereIn('distributor_id', $filters['distributor_id']);
            } else {
                $query->where('distributor_id', $filters['distributor_id']);
            }
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['card_code'])) {
            $cardCodes = array_map('trim', explode(',', $filters['card_code']));
            if (count($cardCodes) > 1) {
                $query->whereIn('card_code', $cardCodes);
            } else {
                $query->where('card_code', $cardCodes[0]);
            }
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('doc_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('doc_date', '<=', $filters['end_date']);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        foreach ($orders as $order) {
            if ($order->attachments->isEmpty() && !empty($order->order_no)) {
                $so = \App\Models\SalesOrder::where('order_no', $order->order_no)->with('attachments')->first();
                if ($so && $so->attachments->isNotEmpty()) {
                    $order->setRelation('attachments', $so->attachments);
                }
            }
        }

        return $orders;
    }

    /**
     * Get customer monthly order by ID.
     *
     * @param  int  $id
     * @return CustomerMonthlyOrder|null
     */
    public function getById(int $id): ?CustomerMonthlyOrder
    {
        $order = CustomerMonthlyOrder::query()
            ->with(['details.item', 'details.warehouse', 'details.vat', 'details.ocr', 'details.ocr2', 'details.ocr3', 'salesEmployee', 'sapDiscount.details', 'attachments', 'distributor'])
            ->find($id);

        if ($order && $order->attachments->isEmpty() && !empty($order->order_no)) {
            $so = \App\Models\SalesOrder::where('order_no', $order->order_no)->with('attachments')->first();
            if ($so && $so->attachments->isNotEmpty()) {
                $order->setRelation('attachments', $so->attachments);
            }
        }

        return $order;
    }

    /**
     * Create a new customer monthly order.
     *
     * @param  array  $data
     * @return CustomerMonthlyOrder
     */
    public function create(array $data): CustomerMonthlyOrder
    {
        return DB::transaction(function () use ($data) {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $order = CustomerMonthlyOrder::create($data);

            foreach ($lines as $line) {
                $order->details()->create($line);
            }

            return $order->load(['details.item', 'details.warehouse', 'details.vat', 'details.ocr', 'details.ocr2', 'details.ocr3', 'salesEmployee', 'sapDiscount.details', 'attachments']);
        });
    }

    /**
     * Update an existing customer monthly order.
     *
     * @param  CustomerMonthlyOrder  $order
     * @param  array  $data
     * @return CustomerMonthlyOrder
     */
    public function update(CustomerMonthlyOrder $order, array $data): CustomerMonthlyOrder
    {
        return DB::transaction(function () use ($order, $data) {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $order->update($data);

            $order->details()->delete();
            foreach ($lines as $line) {
                $order->details()->create($line);
            }

            return $order->load(['details.item', 'details.warehouse', 'details.vat', 'details.ocr', 'details.ocr2', 'details.ocr3', 'salesEmployee', 'sapDiscount.details', 'attachments']);
        });
    }

    /**
     * Delete a customer monthly order.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $order = CustomerMonthlyOrder::find($id);
            if ($order) {
                return (bool) $order->delete();
            }
            return false;
        });
    }
}
