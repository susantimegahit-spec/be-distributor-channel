<?php

namespace App\Modules\SalesOrder\Repositories;

use App\Models\SalesOrder;
use App\Models\CustomerMonthlyOrder;
use App\Models\CustomerMonthlyOrderDetail;
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
        $query = SalesOrder::query()->with(['details', 'sapDiscount.details', 'attachments', 'approval', 'distributor']);

        if (!empty($filters['distributor_id'])) {
            if (is_array($filters['distributor_id'])) {
                $query->whereIn('distributor_id', $filters['distributor_id']);
            } else {
                $query->where('distributor_id', $filters['distributor_id']);
            }
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', '!=', 'DRAFT');
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
        return SalesOrder::with([
            'details.item',
            'details.warehouse',
            'details.vat',
            'details.ocr',
            'details.ocr2',
            'details.ocr3',
            'salesEmployee',
            'sapDiscount.details',
            'attachments',
            'approval',
            'approvalHistories.user',
            'approvalHistories.approvalBefore',
            'approvalHistories.approvalAfter',
            'distributor'
        ])->find($id);
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

            // Duplicate to customer_monthly_orders if DRAFT as CMO
            if (strtoupper($salesOrder->status) === 'DRAFT') {
                $cmoData = $salesOrder->getAttributes();
                unset($cmoData['id']);
                unset($cmoData['use_balance']);
                unset($cmoData['approval_id']);
                unset($cmoData['sales_pic_id']);

                $cmo = CustomerMonthlyOrder::create($cmoData);

                foreach ($lines as $line) {
                    $line['customer_monthly_order_id'] = $cmo->id;
                    CustomerMonthlyOrderDetail::create($line);
                }
            }

            return $salesOrder->load(['details.item', 'details.warehouse', 'details.vat', 'details.ocr', 'details.ocr2', 'details.ocr3', 'salesEmployee', 'sapDiscount.details', 'attachments']);
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

            return $salesOrder->load(['details.item', 'details.warehouse', 'details.vat', 'details.ocr', 'details.ocr2', 'details.ocr3', 'salesEmployee', 'sapDiscount.details', 'attachments']);
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

    /**
     * Get dashboard summary statistics.
     *
     * @param  int|null  $distributorId
     * @return array
     */
    public function getDashboardSummary(int|array|null $distributorId = null): array
    {
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        // 1. Base Query
        $baseQuery = DB::table('sales_orders');
        if ($distributorId !== null) {
            if (is_array($distributorId)) {
                $baseQuery->whereIn('distributor_id', $distributorId);
            } else {
                $baseQuery->where('distributor_id', $distributorId);
            }
        }

        // 2. Status Counts (overall count)
        $statusCountsQuery = clone $baseQuery;
        $statusCountsRaw = $statusCountsQuery
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        // Ensure all possible statuses are present in the count
        $allStatuses = [
            'DRAFT' => 0,
            'CREATED' => 0,
            'WAITING_APPROVAL' => 0,
            'APPROVED' => 0,
            'DELIVERY' => 0,
            'ARRIVED' => 0,
            'REJECTED' => 0,
            'FAILED' => 0
        ];
        $statusCounts = array_merge($allStatuses, $statusCountsRaw);

        // 3. Current Month Stats (excluding DRAFT, REJECTED, FAILED)
        $monthlyQuery = clone $baseQuery;
        $monthlyQuery->whereBetween('doc_date', [$startOfMonth, $endOfMonth])
            ->whereNotIn('status', ['DRAFT', 'REJECTED', 'FAILED']);

        $totalRevenue = (float) $monthlyQuery->sum('doc_total');
        $totalOrders = (int) $monthlyQuery->count();

        // Get details volume
        $orderIdsQuery = clone $baseQuery;
        $orderIds = $orderIdsQuery->whereBetween('doc_date', [$startOfMonth, $endOfMonth])
            ->whereNotIn('status', ['DRAFT', 'REJECTED', 'FAILED'])
            ->pluck('id')
            ->toArray();

        $totalVolume = 0.0;
        if (!empty($orderIds)) {
            $totalVolume = (float) DB::table('sales_order_details')
                ->whereIn('sales_order_id', $orderIds)
                ->sum('quantity');
        }

        // 4. Daily Sales Trend (last 30 days)
        $thirtyDaysAgo = now()->subDays(29)->startOfDay()->toDateString();
        $todayEnd = now()->endOfDay()->toDateString();

        $trendQuery = clone $baseQuery;
        $trendData = $trendQuery->whereBetween('doc_date', [$thirtyDaysAgo, $todayEnd])
            ->whereNotIn('status', ['DRAFT', 'REJECTED', 'FAILED'])
            ->select('doc_date', DB::raw('count(*) as count'), DB::raw('sum(doc_total) as total'))
            ->groupBy('doc_date')
            ->orderBy('doc_date', 'asc')
            ->get()
            ->map(function ($item) {
                $dateStr = $item->doc_date;
                if ($dateStr instanceof \DateTimeInterface) {
                    $dateStr = $dateStr->format('Y-m-d');
                }
                return [
                    'date' => is_string($dateStr) ? substr($dateStr, 0, 10) : $dateStr,
                    'count' => (int) $item->count,
                    'total' => (float) $item->total,
                ];
            })
            ->toArray();

        // 5. Top 5 Products this month
        $productsQuery = DB::table('sales_order_details')
            ->join('sales_orders', 'sales_order_details.sales_order_id', '=', 'sales_orders.id')
            ->join('items', 'sales_order_details.item_code', '=', 'items.item_code')
            ->whereBetween('sales_orders.doc_date', [$startOfMonth, $endOfMonth])
            ->whereNotIn('sales_orders.status', ['DRAFT', 'REJECTED', 'FAILED']);

        if ($distributorId !== null) {
            $productsQuery->where('sales_orders.distributor_id', $distributorId);
        }

        $topProducts = $productsQuery
            ->select('sales_order_details.item_code', 'items.item_name', DB::raw('sum(sales_order_details.quantity) as total_qty'))
            ->groupBy('sales_order_details.item_code', 'items.item_name')
            ->orderBy('total_qty', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'total_qty' => (float) $item->total_qty
                ];
            })
            ->toArray();

        $result = [
            'sales_summary' => [
                'total_revenue_this_month' => $totalRevenue,
                'total_volume_kg_this_month' => $totalVolume,
                'total_orders_this_month' => $totalOrders,
            ],
            'order_statuses' => $statusCounts,
            'daily_sales_trend' => $trendData,
            'top_products' => $topProducts,
        ];

        // 6. Top 5 Distributors (Admin only)
        if ($distributorId === null) {
            $distributorsQuery = DB::table('sales_orders')
                ->join('distributors', 'sales_orders.distributor_id', '=', 'distributors.id')
                ->whereBetween('sales_orders.doc_date', [$startOfMonth, $endOfMonth])
                ->whereNotIn('sales_orders.status', ['DRAFT', 'REJECTED', 'FAILED']);

            $topDistributors = $distributorsQuery
                ->select('sales_orders.distributor_id', 'distributors.name', DB::raw('sum(sales_orders.doc_total) as total_spent'))
                ->groupBy('sales_orders.distributor_id', 'distributors.name')
                ->orderBy('total_spent', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'distributor_id' => $item->distributor_id,
                        'name' => $item->name,
                        'total_spent' => (float) $item->total_spent
                    ];
                })
                ->toArray();

            $result['top_distributors'] = $topDistributors;
        }

        return $result;
    }
}
