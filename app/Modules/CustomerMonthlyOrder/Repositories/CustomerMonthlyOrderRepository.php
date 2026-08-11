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

            $order->load(['details.item', 'details.warehouse', 'details.vat', 'details.ocr', 'details.ocr2', 'details.ocr3', 'salesEmployee', 'sapDiscount.details', 'attachments']);
            if ($order->attachments->isEmpty() && !empty($order->order_no)) {
                $so = \App\Models\SalesOrder::where('order_no', $order->order_no)->with('attachments')->first();
                if ($so && $so->attachments->isNotEmpty()) {
                    $order->setRelation('attachments', $so->attachments);
                }
            }
            return $order;
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

            $order->load(['details.item', 'details.warehouse', 'details.vat', 'details.ocr', 'details.ocr2', 'details.ocr3', 'salesEmployee', 'sapDiscount.details', 'attachments']);
            if ($order->attachments->isEmpty() && !empty($order->order_no)) {
                $so = \App\Models\SalesOrder::where('order_no', $order->order_no)->with('attachments')->first();
                if ($so && $so->attachments->isNotEmpty()) {
                    $order->setRelation('attachments', $so->attachments);
                }
            }
            return $order;
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

    /**
     * Get report grouped by depo.
     */
    public function getReportByDepo(array $filters = []): array
    {
        $query = DB::table('customer_monthly_orders as c')
            ->join('distributors as d', 'c.distributor_id', '=', 'd.id')
            ->select([
                'd.depo',
                DB::raw('COUNT(c.id) as total_orders'),
                DB::raw('COALESCE(SUM(c.doc_total), 0) as total_amount')
            ]);

        $this->applyReportFilters($query, $filters);

        return $query->groupBy('d.depo')
            ->orderBy('total_amount', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'depo' => $item->depo ?: 'TANPA DEPO',
                    'total_orders' => (int)$item->total_orders,
                    'total_amount' => (float)$item->total_amount,
                ];
            })
            ->toArray();
    }

    /**
     * Get report grouped by year / month.
     */
    public function getReportByYear(array $filters = []): array
    {
        $driver = DB::getDriverName();

        if (!empty($filters['year'])) {
            $monthExpr = $driver === 'sqlite' ? "strftime('%m', c.doc_date)" : "EXTRACT(MONTH FROM c.doc_date)";
            
            $query = DB::table('customer_monthly_orders as c')
                ->select([
                    DB::raw("{$monthExpr} as month"),
                    DB::raw('COUNT(c.id) as total_orders'),
                    DB::raw('COALESCE(SUM(c.doc_total), 0) as total_amount')
                ]);

            $this->applyReportFilters($query, $filters);

            return $query->groupBy(DB::raw($monthExpr))
                ->orderBy('month', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'month' => (int)$item->month,
                        'total_orders' => (int)$item->total_orders,
                        'total_amount' => (float)$item->total_amount,
                    ];
                })
                ->toArray();
        } else {
            $yearExpr = $driver === 'sqlite' ? "strftime('%Y', c.doc_date)" : "EXTRACT(YEAR FROM c.doc_date)";

            $query = DB::table('customer_monthly_orders as c')
                ->select([
                    DB::raw("{$yearExpr} as year"),
                    DB::raw('COUNT(c.id) as total_orders'),
                    DB::raw('COALESCE(SUM(c.doc_total), 0) as total_amount')
                ]);

            $this->applyReportFilters($query, $filters);

            return $query->groupBy(DB::raw($yearExpr))
                ->orderBy('year', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'year' => (int)$item->year,
                        'total_orders' => (int)$item->total_orders,
                        'total_amount' => (float)$item->total_amount,
                    ];
                })
                ->toArray();
        }
    }

    /**
     * Apply report filters helper.
     */
    private function applyReportFilters($query, array $filters): void
    {
        if (!empty($filters['distributor_id'])) {
            if (is_array($filters['distributor_id'])) {
                $query->whereIn('c.distributor_id', $filters['distributor_id']);
            } else {
                $query->where('c.distributor_id', $filters['distributor_id']);
            }
        }

        if (!empty($filters['status'])) {
            $query->where('c.status', $filters['status']);
        }

        if (!empty($filters['card_code'])) {
            $cardCodes = array_map('trim', explode(',', $filters['card_code']));
            if (count($cardCodes) > 1) {
                $query->whereIn('c.card_code', $cardCodes);
            } else {
                $query->where('c.card_code', $cardCodes[0]);
            }
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('c.doc_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('c.doc_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['year'])) {
            $driver = DB::getDriverName();
            if ($driver === 'sqlite') {
                $query->whereRaw("CAST(strftime('%Y', c.doc_date) as integer) = ?", [$filters['year']]);
            } else {
                $query->whereRaw("EXTRACT(YEAR FROM c.doc_date) = ?", [$filters['year']]);
            }
        }
    }
}
