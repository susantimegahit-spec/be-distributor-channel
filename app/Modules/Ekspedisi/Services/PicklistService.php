<?php

namespace App\Modules\Ekspedisi\Services;

use App\Models\Item;
use App\Models\Picklist;
use App\Models\PicklistItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PicklistService
{
    /**
     * Get paginated list of picklists with filters.
     *
     * @param array $filters
     * @return array
     */
    public function getPicklists(array $filters = []): array
    {
        $query = Picklist::with([
            'items.salesOrder:id,order_no,customer_name,card_code',
            'creator:id,name,username',
        ]);

        // Filter by shipping type
        if (!empty($filters['shipping_type'])) {
            $query->where('shipping_type', strtolower(trim((string) $filters['shipping_type'])));
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', strtoupper(trim((string) $filters['status'])));
        }

        // Filter by date range (posting_date)
        if (!empty($filters['date_from'])) {
            $query->whereDate('posting_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('posting_date', '<=', $filters['date_to']);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('picklist_no', 'ilike', "%{$search}%")
                    ->orWhere('license_plate', 'ilike', "%{$search}%")
                    ->orWhere('driver_name', 'ilike', "%{$search}%")
                    ->orWhere('checker_name', 'ilike', "%{$search}%")
                    ->orWhere('comments', 'ilike', "%{$search}%")
                    ->orWhereHas('items.salesOrder', function (Builder $sq) use ($search) {
                        $sq->where('order_no', 'ilike', "%{$search}%")
                            ->orWhere('customer_name', 'ilike', "%{$search}%");
                    });
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min(100, $perPage));

        $paginator = $query->orderBy('id', 'desc')->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ];
    }

    /**
     * Get detail of a single picklist.
     *
     * @param int $id
     * @return Picklist
     * @throws \Exception
     */
    public function getPicklistDetail(int $id): Picklist
    {
        $picklist = Picklist::with([
            'items.salesOrder:id,order_no,customer_name,card_code,address,address2,depo',
            'items.item:id,item_code,item_name,per_kg',
            'creator:id,name,username',
            'updater:id,name,username',
        ])->find($id);

        if (!$picklist) {
            throw new \Exception("Picklist with ID #{$id} not found.", 404);
        }

        return $picklist;
    }

    /**
     * Get list of Sales Orders ready for picklist generation.
     * Only orders with status 'ORDER_APPROVED' and logistic_status in ('APPROVED', 'RESCHEDULE_APPROVED').
     *
     * @param array $filters
     * @return array
     */
    public function getAvailableOrdersForPicklist(array $filters = []): array
    {
        $query = SalesOrder::query()
            ->where('status', 'ORDER_APPROVED')
            ->whereIn('logistic_status', ['APPROVED', 'RESCHEDULE_APPROVED'])
            ->with([
                'details.item:id,item_code,item_name,per_kg',
                'details.warehouse:id,whs_code,whs_name',
                'distributor:id,name,code_customer,depo',
            ]);

        // Search filter
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('order_no', 'ilike', "%{$search}%")
                    ->orWhere('customer_name', 'ilike', "%{$search}%")
                    ->orWhere('card_code', 'ilike', "%{$search}%")
                    ->orWhere('sap_doc_num', 'ilike', "%{$search}%");
            });
        }

        $orders = $query->orderBy('id', 'desc')->get();

        // Calculate picked quantities per detail line from non-cancelled picklists
        $pickedQuantities = DB::table('picklist_items')
            ->join('picklists', 'picklist_items.picklist_id', '=', 'picklists.id')
            ->whereIn('picklists.status', [Picklist::STATUS_OPEN, Picklist::STATUS_COMPLETED])
            ->groupBy('picklist_items.sales_order_detail_id')
            ->select('picklist_items.sales_order_detail_id', DB::raw('SUM(picklist_items.pick_qty) as total_picked'))
            ->pluck('total_picked', 'sales_order_detail_id')
            ->all();

        $result = [];

        foreach ($orders as $order) {
            $items = [];
            $totalRemainingWeight = 0;

            foreach ($order->details as $detail) {
                $orderedQty = (float) $detail->quantity;
                $alreadyPicked = (float) ($pickedQuantities[$detail->id] ?? 0);
                $remainingQty = max(0.0, $orderedQty - $alreadyPicked);

                // If remaining quantity to pick is > 0, include in selectable items
                if ($remainingQty > 0) {
                    $unitWeight = $this->resolveUnitWeight($detail->item_code, $detail->item?->per_kg, $detail->item_name);
                    $lineWeight = round($remainingQty * $unitWeight, 4);
                    $totalRemainingWeight += $lineWeight;

                    $items[] = [
                        'sales_order_id'        => $order->id,
                        'sales_order_detail_id' => $detail->id,
                        'item_code'             => $detail->item_code,
                        'item_name'             => $detail->item_name ?: ($detail->item?->item_name ?? ''),
                        'whs_code'              => $detail->whs_code,
                        'whs_name'              => $detail->whs_name,
                        'unit_msr'              => $detail->unit_msr,
                        'ordered_qty'           => $orderedQty,
                        'already_picked_qty'    => $alreadyPicked,
                        'remaining_pick_qty'    => $remainingQty,
                        'unit_weight'           => $unitWeight,
                        'total_weight'          => $lineWeight,
                    ];
                }
            }

            // Only include orders that have items remaining to be picked
            if (!empty($items)) {
                $result[] = [
                    'id'               => $order->id,
                    'order_no'         => $order->order_no,
                    'sap_doc_num'      => $order->sap_doc_num,
                    'customer_name'    => $order->customer_name,
                    'card_code'        => $order->card_code,
                    'depo'             => $order->depo ?: ($order->distributor?->depo ?? ''),
                    'address'          => $order->address ?: $order->address2,
                    'logistic_status'  => $order->logistic_status,
                    'doc_due_date'     => $order->doc_due_date?->format('Y-m-d'),
                    'eta_date'         => $order->eta_date?->format('Y-m-d'),
                    'total_weight'     => round($totalRemainingWeight, 4),
                    'items'            => $items,
                ];
            }
        }

        return $result;
    }

    /**
     * Create a new Picklist.
     *
     * @param array $payload
     * @param int|null $userId
     * @return Picklist
     * @throws \Exception
     */
    public function createPicklist(array $payload, ?int $userId = null): Picklist
    {
        $shippingType = strtolower(trim((string) ($payload['shipping_type'] ?? '')));
        if (!in_array($shippingType, [Picklist::SHIPPING_TYPE_INTERNAL, Picklist::SHIPPING_TYPE_EXTERNAL, Picklist::SHIPPING_TYPE_PICKUP], true)) {
            throw new \Exception("Invalid shipping type '{$shippingType}'. Must be one of: internal, external, pickup.", 422);
        }

        $postingDate = $payload['posting_date'] ?? now()->format('Y-m-d');
        $dueDate     = $payload['due_date'] ?? $postingDate;

        if (Carbon::parse($dueDate)->lt(Carbon::parse($postingDate))) {
            throw new \Exception("Due date cannot be earlier than posting date.", 422);
        }

        $itemsPayload = $payload['items'] ?? [];
        if (!is_array($itemsPayload) || empty($itemsPayload)) {
            throw new \Exception("Picklist must contain at least one item line.", 422);
        }

        // Validate shipping type constraints
        $licensePlate = null;
        $driverName   = null;
        $checkerName  = null;

        if ($shippingType === Picklist::SHIPPING_TYPE_INTERNAL) {
            $licensePlate = trim((string) ($payload['license_plate'] ?? ''));
            if (empty($licensePlate)) {
                throw new \Exception("License plate number is required for internal fleet shipping.", 422);
            }
            $driverName  = !empty($payload['driver_name']) ? trim((string) $payload['driver_name']) : null;
            $checkerName = !empty($payload['checker_name']) ? trim((string) $payload['checker_name']) : null;
        }

        // Extract distinct Sales Orders from items
        $salesOrderIds = array_values(array_unique(array_filter(array_map(function ($row) {
            return !empty($row['sales_order_id']) ? (int) $row['sales_order_id'] : null;
        }, $itemsPayload))));

        if (empty($salesOrderIds)) {
            throw new \Exception("Each picklist item must specify a valid sales_order_id.", 422);
        }

        // External & Pickup shipping allow strictly ONE Sales Order
        if (in_array($shippingType, [Picklist::SHIPPING_TYPE_EXTERNAL, Picklist::SHIPPING_TYPE_PICKUP], true)) {
            if (count($salesOrderIds) > 1) {
                throw new \Exception("External and Pickup shipping allow only one Sales Order. Please select items from a single Sales Order.", 400);
            }
        }

        // Validate Sales Orders status
        $orders = SalesOrder::whereIn('id', $salesOrderIds)->get()->keyBy('id');
        foreach ($salesOrderIds as $soId) {
            $so = $orders->get($soId);
            if (!$so) {
                throw new \Exception("Sales Order #{$soId} does not exist.", 404);
            }
            if ($so->status !== 'ORDER_APPROVED') {
                throw new \Exception("Sales Order '{$so->order_no}' is not in ORDER_APPROVED status (current status: {$so->status}).", 400);
            }
            if (!in_array($so->logistic_status, ['APPROVED', 'RESCHEDULE_APPROVED'], true)) {
                throw new \Exception("Sales Order '{$so->order_no}' logistics schedule is not approved yet (current logistic status: {$so->logistic_status}).", 400);
            }
        }

        // Get currently picked quantities per detail line from non-cancelled picklists
        $detailIds = array_filter(array_map(fn($r) => !empty($r['sales_order_detail_id']) ? (int) $r['sales_order_detail_id'] : null, $itemsPayload));
        $alreadyPickedMap = [];
        if (!empty($detailIds)) {
            $alreadyPickedMap = DB::table('picklist_items')
                ->join('picklists', 'picklist_items.picklist_id', '=', 'picklists.id')
                ->whereIn('picklists.status', [Picklist::STATUS_OPEN, Picklist::STATUS_COMPLETED])
                ->whereIn('picklist_items.sales_order_detail_id', $detailIds)
                ->groupBy('picklist_items.sales_order_detail_id')
                ->select('picklist_items.sales_order_detail_id', DB::raw('SUM(picklist_items.pick_qty) as total_picked'))
                ->pluck('total_picked', 'sales_order_detail_id')
                ->all();
        }

        // Load details to check quantity limits
        $details = SalesOrderDetail::with('item')->whereIn('id', $detailIds)->get()->keyBy('id');

        $totalAggregatedWeight = 0.0;
        $processedItems = [];

        foreach ($itemsPayload as $index => $itemInput) {
            $soId       = (int) ($itemInput['sales_order_id'] ?? 0);
            $detailId   = !empty($itemInput['sales_order_detail_id']) ? (int) $itemInput['sales_order_detail_id'] : null;
            $pickQty    = (float) ($itemInput['pick_qty'] ?? 0);
            $itemCode   = trim((string) ($itemInput['item_code'] ?? ''));

            if ($pickQty <= 0) {
                throw new \Exception("Pick quantity for item line #" . ($index + 1) . " must be greater than 0.", 422);
            }

            $detail = $detailId ? $details->get($detailId) : null;
            $orderedQty = $detail ? (float) $detail->quantity : (float) ($itemInput['ordered_qty'] ?? $pickQty);
            $alreadyPicked = $detailId ? (float) ($alreadyPickedMap[$detailId] ?? 0) : 0.0;
            $remainingQty = max(0.0, $orderedQty - $alreadyPicked);

            if ($detailId && $pickQty > ($remainingQty + 0.0001)) {
                $name = $detail->item_name ?: $detail->item_code;
                throw new \Exception("Pick quantity ({$pickQty}) for item '{$name}' exceeds the remaining unpicked quantity ({$remainingQty}).", 400);
            }

            $itemName = $itemInput['item_name'] ?? ($detail?->item_name ?: ($detail?->item?->item_name ?? $itemCode));
            $whsCode  = $itemInput['whs_code'] ?? ($detail?->whs_code ?? null);
            $unitMsr  = $itemInput['unit_msr'] ?? ($detail?->unit_msr ?? null);

            $unitWeight = isset($itemInput['unit_weight']) && (float) $itemInput['unit_weight'] > 0
                ? (float) $itemInput['unit_weight']
                : $this->resolveUnitWeight($itemCode, $detail?->item?->per_kg, $itemName);

            $lineTotalWeight = round($pickQty * $unitWeight, 4);
            $totalAggregatedWeight += $lineTotalWeight;

            $processedItems[] = [
                'sales_order_id'        => $soId,
                'sales_order_detail_id' => $detailId,
                'item_code'             => $itemCode,
                'item_name'             => $itemName,
                'whs_code'              => $whsCode,
                'unit_msr'              => $unitMsr,
                'ordered_qty'           => $orderedQty,
                'pick_qty'              => $pickQty,
                'unit_weight'           => $unitWeight,
                'total_weight'          => $lineTotalWeight,
            ];
        }

        $weightLimit = !empty($payload['total_weight_limit']) ? (float) $payload['total_weight_limit'] : null;
        if ($weightLimit !== null && $weightLimit > 0 && $totalAggregatedWeight > ($weightLimit + 0.001)) {
            $formattedTotal = number_format($totalAggregatedWeight, 2);
            $formattedLimit = number_format($weightLimit, 2);
            throw new \Exception("Total item weight ({$formattedTotal} kg) exceeds the vehicle weight limit ({$formattedLimit} kg). Reduce the pick quantities.", 400);
        }

        // Database transaction to persist picklist and its items
        return DB::transaction(function () use (
            $shippingType,
            $postingDate,
            $dueDate,
            $payload,
            $licensePlate,
            $driverName,
            $checkerName,
            $weightLimit,
            $totalAggregatedWeight,
            $processedItems,
            $userId
        ) {
            $picklistNo = $this->generatePicklistNumber();

            $picklist = Picklist::create([
                'picklist_no'        => $picklistNo,
                'shipping_type'      => $shippingType,
                'status'             => Picklist::STATUS_OPEN,
                'posting_date'       => $postingDate,
                'due_date'           => $dueDate,
                'delivery_order_no'  => !empty($payload['delivery_order_no']) ? trim((string) $payload['delivery_order_no']) : null,
                'total_weight_limit' => $weightLimit,
                'total_weight'       => round($totalAggregatedWeight, 4),
                'comments'           => !empty($payload['comments']) ? trim((string) $payload['comments']) : null,
                'license_plate'      => $licensePlate,
                'driver_name'        => $driverName,
                'checker_name'       => $checkerName,
                'expedition_id'      => !empty($payload['expedition_id']) ? (int) $payload['expedition_id'] : null,
                'expedition_name'    => !empty($payload['expedition_name']) ? trim((string) $payload['expedition_name']) : null,
                'expedition_rate_id' => !empty($payload['expedition_rate_id']) ? (int) $payload['expedition_rate_id'] : null,
                'service_type'       => !empty($payload['service_type']) ? trim((string) $payload['service_type']) : null,
                'estimated_cost'     => isset($payload['estimated_cost']) ? (float) $payload['estimated_cost'] : null,
                'created_by'         => $userId,
                'updated_by'         => $userId,
            ]);

            foreach ($processedItems as $item) {
                $item['picklist_id'] = $picklist->id;
                PicklistItem::create($item);
            }

            return $picklist->load([
                'items.salesOrder:id,order_no,customer_name,card_code',
                'creator:id,name,username',
            ]);
        });
    }

    /**
     * Update status of picklist (COMPLETED or CANCELLED).
     *
     * @param int $id
     * @param string $newStatus
     * @param int|null $userId
     * @return Picklist
     * @throws \Exception
     */
    public function updateStatus(int $id, string $newStatus, ?int $userId = null): Picklist
    {
        $status = strtoupper(trim($newStatus));
        if (!in_array($status, [Picklist::STATUS_COMPLETED, Picklist::STATUS_CANCELLED], true)) {
            throw new \Exception("Invalid status '{$newStatus}'. Allowed transitions: COMPLETED, CANCELLED.", 422);
        }

        $picklist = Picklist::find($id);
        if (!$picklist) {
            throw new \Exception("Picklist with ID #{$id} not found.", 404);
        }

        if ($picklist->status !== Picklist::STATUS_OPEN) {
            throw new \Exception("Only OPEN picklists can have their status changed (current status: {$picklist->status}).", 400);
        }

        $picklist->update([
            'status'     => $status,
            'updated_by' => $userId,
        ]);

        return $picklist->fresh(['items.salesOrder', 'creator', 'updater']);
    }

    /**
     * Generate unique sequential picklist number (e.g. PKL-20260918-0001).
     *
     * @return string
     */
    protected function generatePicklistNumber(): string
    {
        $todayPrefix = 'PKL-' . date('Ymd') . '-';
        $latestRecord = Picklist::where('picklist_no', 'like', "{$todayPrefix}%")
            ->orderBy('id', 'desc')
            ->value('picklist_no');

        if ($latestRecord) {
            $lastSequence = (int) substr($latestRecord, strlen($todayPrefix));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $todayPrefix . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve unit weight in kg from per_kg or item name regex.
     *
     * @param string $itemCode
     * @param float|null $perKg
     * @param string|null $itemName
     * @return float
     */
    protected function resolveUnitWeight(string $itemCode, ?float $perKg = null, ?string $itemName = null): float
    {
        if ($perKg !== null && (float) $perKg > 0) {
            return (float) $perKg;
        }

        // Try lookup from Item master if perKg wasn't loaded
        $masterItem = Item::where('item_code', $itemCode)->first();
        if ($masterItem && (float) $masterItem->per_kg > 0) {
            return (float) $masterItem->per_kg;
        }

        // Regex parsing fallback on item name (e.g., '250gr', '1kg', '500 gram')
        $nameToParse = $itemName ?: ($masterItem?->item_name ?? '');
        if (!empty($nameToParse)) {
            if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*(kg|kilogram|g|gr|gram)\b/i', $nameToParse, $matches, PREG_SET_ORDER)) {
                $lastMatch = end($matches);
                $numericVal = (float) str_replace(',', '.', $lastMatch[1]);
                $unit = strtolower($lastMatch[2]);
                if (in_array($unit, ['g', 'gr', 'gram'], true)) {
                    return $numericVal / 1000.0;
                }
                return $numericVal;
            }
        }

        return 0.0;
    }
}
