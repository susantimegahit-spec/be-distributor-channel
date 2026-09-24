<?php

namespace App\Modules\Ekspedisi\Services;

use App\Models\Item;
use App\Models\Picklist;
use App\Models\PicklistItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\SalesOrderLogisticLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PicklistService
{
    /**
     * Hardcoded destination warehouse code for Picklist Inventory Transfer.
     */
    public const DEFAULT_IT_TO_WHS_CODE = 'VPGMN01';

    /**
     * Get paginated list of picklists with filters.
     *
     * @param array $filters
     * @return array
     */
    public function getPicklists(array $filters = []): array
    {
        $query = Picklist::with([
            'items.salesOrder:id,order_no,sap_doc_num,customer_name,card_code,distributor_id',
            'items.salesOrder.distributor:id,name,code_customer,depo',
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
                            ->orWhere('sap_doc_num', 'ilike', "%{$search}%")
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
            'items.salesOrder:id,order_no,sap_doc_num,customer_name,card_code,address,address2,distributor_id',
            'items.salesOrder.distributor:id,name,code_customer,depo',
            'items.item:id,item_code,item_name,per_kg',
            'creator:id,name,username',
            'updater:id,name,username',
        ])->find($id);

        if (!$picklist) {
            throw new \Exception("Picklist with ID #{$id} not found.", 404);
        }

        foreach ($picklist->items as $item) {
            if ($item->salesOrder && !empty($item->salesOrder->sap_doc_num)) {
                $item->salesOrder->order_no = (string) $item->salesOrder->sap_doc_num;
            }
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
        $picklistTable = (new Picklist)->getTable();
        $picklistItemTable = (new PicklistItem)->getTable();
        $ekspedisiConn = (new Picklist)->getConnectionName();

        $pickedQuantities = DB::connection($ekspedisiConn)->table($picklistItemTable)
            ->join($picklistTable, "{$picklistItemTable}.picklist_id", '=', "{$picklistTable}.id")
            ->whereIn("{$picklistTable}.status", [Picklist::STATUS_OPEN, Picklist::STATUS_COMPLETED])
            ->groupBy("{$picklistItemTable}.sales_order_detail_id")
            ->select("{$picklistItemTable}.sales_order_detail_id", DB::raw("SUM({$picklistItemTable}.pick_qty) as total_picked"))
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
            $picklistTable = (new Picklist)->getTable();
            $picklistItemTable = (new PicklistItem)->getTable();
            $ekspedisiConn = (new Picklist)->getConnectionName();

            $alreadyPickedMap = DB::connection($ekspedisiConn)->table($picklistItemTable)
                ->join($picklistTable, "{$picklistItemTable}.picklist_id", '=', "{$picklistTable}.id")
                ->whereIn("{$picklistTable}.status", [Picklist::STATUS_OPEN, Picklist::STATUS_COMPLETED])
                ->whereIn("{$picklistItemTable}.sales_order_detail_id", $detailIds)
                ->groupBy("{$picklistItemTable}.sales_order_detail_id")
                ->select("{$picklistItemTable}.sales_order_detail_id", DB::raw("SUM({$picklistItemTable}.pick_qty) as total_picked"))
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

            $rawBinAllocations = $itemInput['bin_allocations'] ?? $itemInput['binAllocations'] ?? null;
            $normalizedBinAllocations = null;
            if (is_array($rawBinAllocations) && !empty($rawBinAllocations)) {
                $normalizedBinAllocations = [];
                foreach ($rawBinAllocations as $bin) {
                    $absEntry = $bin['AbsEntry'] ?? $bin['abs_entry'] ?? $bin['value'] ?? $bin['id'] ?? null;
                    $binQty   = floatval($bin['Quantity'] ?? $bin['quantity'] ?? $bin['qty'] ?? 0);
                    if ($absEntry !== null && is_numeric($absEntry) && $binQty > 0) {
                        $normalizedBinAllocations[] = [
                            'AbsEntry' => (int) $absEntry,
                            'Quantity' => $binQty,
                            'code'     => $bin['code'] ?? $bin['bin_code'] ?? null,
                            'name'     => $bin['name'] ?? $bin['description'] ?? null,
                        ];
                    }
                }
                if (empty($normalizedBinAllocations)) {
                    $normalizedBinAllocations = null;
                }
            }

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
                'bin_allocations'       => $normalizedBinAllocations,
            ];
        }

        $weightLimit = !empty($payload['total_weight_limit']) ? (float) $payload['total_weight_limit'] : null;
        if ($weightLimit !== null && $weightLimit > 0 && $totalAggregatedWeight > ($weightLimit + 0.001)) {
            $formattedTotal = number_format($totalAggregatedWeight, 2);
            $formattedLimit = number_format($weightLimit, 2);
            throw new \Exception("Total item weight ({$formattedTotal} kg) exceeds the vehicle weight limit ({$formattedLimit} kg). Reduce the pick quantities.", 400);
        }

        // 1. Call SAP Inventory Transfer (/api/addIT) first (fail-fast rule)
        // Hardcoded destination warehouse to VPGMN01. If IT fails, picklist creation aborts immediately.
        $itResult = $this->executeInventoryTransferToSapForPicklist($payload, $processedItems, $details, $userId);

        // 2. Database transaction on ekspedisi connection to persist picklist and its items
        $ekspedisiConn = (new Picklist)->getConnection();
        return $ekspedisiConn->transaction(function () use (
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
            $salesOrderIds,
            $orders,
            $itResult,
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
                'it_doc_entry'       => (string) $itResult['doc_entry'],
                'it_doc_num'         => (string) $itResult['doc_num'],
                'it_status'          => 'SUCCESS',
                'to_whs_code'        => self::DEFAULT_IT_TO_WHS_CODE,
                'series'             => !empty($payload['series']) ? (int) $payload['series'] : (!empty($payload['Series']) ? (int) $payload['Series'] : null),
                'series_name'        => !empty($payload['series_name']) ? trim((string) $payload['series_name']) : (!empty($payload['SeriesName']) ? trim((string) $payload['SeriesName']) : null),
                'seal_number'        => !empty($payload['seal_number']) ? trim((string) $payload['seal_number']) : (!empty($payload['noseal']) ? trim((string) $payload['noseal']) : (!empty($payload['Noseal']) ? trim((string) $payload['Noseal']) : null)),
                'created_by'         => $userId,
                'updated_by'         => $userId,
            ]);

            foreach ($processedItems as $item) {
                $item['picklist_id'] = $picklist->id;
                PicklistItem::create($item);
            }

            // Also update Sales Orders and create logistic logs on main connection
            $user = $userId ? User::with('role')->find($userId) : null;
            $userName = $user?->name ?? 'System / Logistic';
            $roleName = $user?->role?->name ?? 'LOGISTIC';

            foreach ($salesOrderIds as $soId) {
                $so = $orders->get($soId);
                if ($so) {
                    $so->update([
                        'to_whs_code'      => self::DEFAULT_IT_TO_WHS_CODE,
                        'nopol'            => $licensePlate ?: $so->nopol,
                        'nama_supir'       => $driverName ?: $so->nama_supir,
                        'sap_it_doc_entry' => (string) $itResult['doc_entry'],
                        'sap_it_doc_num'   => (string) $itResult['doc_num'],
                        'sap_it_status'    => 'SUCCESS',
                    ]);

                    SalesOrderLogisticLog::create([
                        'sales_order_id'   => $so->id,
                        'action'           => 'LOGISTIC_INVENTORY_TRANSFER',
                        'from_status'      => $so->logistic_status,
                        'to_status'        => $so->logistic_status,
                        'notes'            => "Inventory Transfer (IT) processed to SAP via Picklist {$picklistNo} (DocNum: {$itResult['doc_num']}, Destination: " . self::DEFAULT_IT_TO_WHS_CODE . ").",
                        'user_id'          => $userId,
                        'user_name'        => $userName,
                        'role_name'        => $roleName,
                        'sap_it_doc_entry' => (string) $itResult['doc_entry'],
                        'sap_it_doc_num'   => (string) $itResult['doc_num'],
                    ]);
                }
            }

            return $picklist->load([
                'items.salesOrder:id,order_no,sap_doc_num,customer_name,card_code,distributor_id',
                'items.salesOrder.distributor:id,name,code_customer,depo',
                'creator:id,name,username',
            ]);
        });
    }

    /**
     * Execute Inventory Transfer (IT) to SAP B1 API (/api/addIT) for picklist.
     * Hardcoded destination warehouse to VPGMN01.
     * If source warehouse has BIN allocations, lines will include BinActivfrom = 'Y' and Lines_BinFROM.
     * If source warehouse has no BIN, BinActivfrom = 'N'. Destination BinActivto is always 'N'.
     *
     * @param array $payload
     * @param array $processedItems
     * @param \Illuminate\Support\Collection $details
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function executeInventoryTransferToSapForPicklist(
        array $payload,
        array $processedItems,
        $details,
        ?int $userId = null
    ): array {
        $lines = [];
        $headerFiller = null;

        foreach ($processedItems as $item) {
            $itemCode = trim((string) $item['item_code']);
            $qty = floatval($item['pick_qty']);
            if (empty($itemCode) || $qty <= 0) {
                continue;
            }

            $lineWhsCode = trim((string) ($item['whs_code'] ?: 'FG01'));
            if (!$headerFiller) {
                $headerFiller = $lineWhsCode;
            }

            $detailId = $item['sales_order_detail_id'] ?? null;
            $detail = $detailId && $details ? $details->get($detailId) : null;

            $uomEntry = is_numeric($detail?->uom_entry) ? (int) $detail->uom_entry : 0;
            $ocrCode  = (string) ($detail?->ocr_code ?? '');
            $ocrCode2 = (string) ($detail?->ocr_code2 ?? '');
            $ocrCode3 = (string) ($detail?->ocr_code3 ?? '');

            // BIN allocation
            $rawBinAllocations = $item['bin_allocations'] ?? [];
            $linesBinFrom = [];

            if (is_array($rawBinAllocations) && !empty($rawBinAllocations)) {
                foreach ($rawBinAllocations as $bin) {
                    $absEntry = $bin['AbsEntry'] ?? $bin['abs_entry'] ?? $bin['value'] ?? $bin['id'] ?? null;
                    $binQty   = floatval($bin['Quantity'] ?? $bin['quantity'] ?? $bin['qty'] ?? 0);

                    if ($absEntry !== null && is_numeric($absEntry) && $binQty > 0) {
                        $linesBinFrom[] = [
                            'AbsEntry' => (int) $absEntry,
                            'Quantity' => $binQty,
                        ];
                    }
                }
            }

            $lineData = [
                'ItemCode'     => $itemCode,
                'Quantity'     => $qty,
                'UomEntry'     => $uomEntry,
                'Filler'       => $lineWhsCode,
                'ToWhsCode'    => self::DEFAULT_IT_TO_WHS_CODE,
                'UseBaseUn'    => 'Y',
                'OcrCode'      => $ocrCode,
                'OcrCode2'     => $ocrCode2,
                'OcrCode3'     => $ocrCode3,
                'BinActivfrom' => !empty($linesBinFrom) ? 'Y' : 'N',
                'BinActivto'   => 'N',
            ];

            if (!empty($linesBinFrom)) {
                $lineData['Lines_BinFROM'] = $linesBinFrom;
            }

            $lines[] = $lineData;
        }

        if (empty($lines)) {
            throw new \Exception("Cannot perform Inventory Transfer: Picklist has no valid items with quantity > 0.", 400);
        }

        $postingDate  = $payload['posting_date'] ?? now()->format('Y-m-d');
        $dueDate      = $payload['due_date'] ?? $postingDate;
        $licensePlate = trim((string) ($payload['license_plate'] ?? ''));
        $driverName   = trim((string) ($payload['driver_name'] ?? ''));

        $itPayload = [
            'DocDate'    => $postingDate,
            'DocDueDate' => $dueDate,
            'Filler'     => $headerFiller ?: 'FG01',
            'ToWhsCode'  => self::DEFAULT_IT_TO_WHS_CODE,
            'Comments'   => "Picklist IT to " . self::DEFAULT_IT_TO_WHS_CODE . ($licensePlate ? " - Nopol: {$licensePlate}" : '') . (!empty($payload['comments']) ? " - {$payload['comments']}" : ''),
            'BeratBruto' => (string) ($payload['berat_bruto'] ?? $payload['total_weight'] ?? ''),
            'BeratTara'  => (string) ($payload['berat_tara'] ?? ''),
            'Nopol'      => $licensePlate,
            'NamaSupir'  => $driverName,
            'AddonId'    => 2,
            'UserId'     => (int) ($userId ?: 1),
            'Lines'      => $lines,
        ];

        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/addIT", $itPayload);
        } catch (\Throwable $e) {
            Log::error("Failed to connect to SAP /api/addIT for Picklist: " . $e->getMessage(), ['payload' => $itPayload]);
            throw new \Exception("Failed to connect to SAP API for Inventory Transfer: " . $e->getMessage(), 400);
        }

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            Log::error("SAP /api/addIT returned HTTP {$status} for Picklist: {$body}");
            throw new \Exception("Failed to process Inventory Transfer to SAP (HTTP {$status}): " . substr($body, 0, 250), 400);
        }

        $result = $response->json();
        if (isset($result['ErrorCode']) && (int) $result['ErrorCode'] !== 0) {
            $errMsg = $result['Message'] ?? 'Unknown SAP error during Inventory Transfer.';
            Log::error("SAP /api/addIT returned ErrorCode {$result['ErrorCode']} for Picklist: {$errMsg}");
            throw new \Exception("SAP Inventory Transfer Error [{$result['ErrorCode']}]: {$errMsg}", 400);
        }

        $docEntry = null;
        $docNum = null;

        if (isset($result['Result'])) {
            if (is_array($result['Result'])) {
                $docEntry = $result['Result']['DocEntry'] ?? $result['Result'][0]['DocEntry'] ?? null;
                $docNum = $result['Result']['DocNum'] ?? $result['Result'][0]['DocNum'] ?? null;
            } elseif (is_numeric($result['Result'])) {
                $docEntry = (string) $result['Result'];
            }
        }

        if (!$docNum && !empty($result['Message']) && preg_match('/DocNum:\s*(\d+)/i', $result['Message'], $m)) {
            $docNum = $m[1];
        }
        if (!$docEntry && !empty($result['Message']) && preg_match('/DocEntry:\s*(\d+)/i', $result['Message'], $m)) {
            $docEntry = $m[1];
        }

        $docNum = $docNum ? (string) $docNum : ($docEntry ? (string) $docEntry : 'PROCESSED');
        $docEntry = $docEntry ? (string) $docEntry : $docNum;

        return [
            'doc_entry' => $docEntry,
            'doc_num'   => $docNum,
            'response'  => $result,
        ];
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
     * Add Delivery Order (DO) in SAP B1 (/api/AddDO) for a picklist.
     *
     * @param int $picklistId
     * @param array $payload
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function addDeliveryOrder(int $picklistId, array $payload = [], ?int $userId = null): array
    {
        $picklist = Picklist::with([
            'items.salesOrder.details',
            'items.salesOrder.distributor',
            'expedition',
        ])->find($picklistId);

        if (!$picklist) {
            throw new \Exception("Picklist with ID #{$picklistId} not found.", 404);
        }

        if ($picklist->status === Picklist::STATUS_CANCELLED) {
            throw new \Exception("Cannot generate Delivery Order for a CANCELLED picklist.", 400);
        }

        if ($picklist->items->isEmpty()) {
            throw new \Exception("Picklist #{$picklistId} has no item lines.", 400);
        }

        // Filter by sales_order_id if specified in payload
        $filterSoId = !empty($payload['sales_order_id']) ? (int) $payload['sales_order_id'] : null;

        // Group picklist items by sales_order_id
        $groupedItems = $picklist->items->groupBy('sales_order_id');
        if ($filterSoId) {
            if (!$groupedItems->has($filterSoId)) {
                throw new \Exception("Sales Order #{$filterSoId} is not part of picklist #{$picklistId}.", 400);
            }
            $groupedItems = $groupedItems->only([$filterSoId]);
        }

        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        $user = $userId ? User::with('role')->find($userId) : null;
        $userName = $user?->name ?? 'System / Logistic';
        $roleName = $user?->role?->name ?? 'LOGISTIC';

        $batchPayload = [];
        $orderContexts = [];

        foreach ($groupedItems as $soId => $items) {
            $firstItem = $items->first();
            $so = $firstItem?->salesOrder;
            if (!$so) {
                $so = SalesOrder::with(['details', 'distributor'])->find($soId);
            }
            if (!$so) {
                continue;
            }

            $soDetails = $so->details ? $so->details->sortBy('id')->values() : collect();

            // Build Lines for this SO
            $lines = [];
            $customLines = $payload['Lines'] ?? $payload['lines'] ?? null;

            if (is_array($customLines) && !empty($customLines)) {
                foreach ($customLines as $cLine) {
                    $qty = floatval($cLine['Quantity'] ?? $cLine['quantity'] ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }

                    $baseEntry = isset($cLine['BaseEntry']) ? (int) $cLine['BaseEntry'] : ($so->sap_doc_entry ?: 1);
                    $baseLine  = isset($cLine['BaseLine']) ? (int) $cLine['BaseLine'] : 0;
                    $itemCode  = trim((string) ($cLine['ItemCode'] ?? $cLine['item_code'] ?? ''));

                    $matchedDetail = null;
                    if (!empty($itemCode)) {
                        $matchedDetail = $soDetails->firstWhere('item_code', $itemCode);
                    }
                    if (!$matchedDetail && isset($soDetails[$baseLine])) {
                        $matchedDetail = $soDetails[$baseLine];
                    }

                    $lineObj = [
                        'BaseEntry' => $baseEntry,
                        'BaseLine'  => $baseLine,
                        'Quantity'  => $qty,
                    ];

                    $whsCode = $cLine['WhsCode'] ?? $cLine['whs_code'] ?? ($matchedDetail?->whs_code ?: null);
                    if (!empty($whsCode)) {
                        $lineObj['WhsCode'] = (string) $whsCode;
                    }

                    $ocrCode  = $cLine['OcrCode'] ?? $cLine['ocr_code'] ?? ($matchedDetail?->ocr_code ?: null);
                    $ocrCode2 = $cLine['OcrCode2'] ?? $cLine['ocr_code2'] ?? ($matchedDetail?->ocr_code2 ?: null);
                    $ocrCode3 = $cLine['OcrCode3'] ?? $cLine['ocr_code3'] ?? ($matchedDetail?->ocr_code3 ?: null);

                    if (!empty($ocrCode)) {
                        $lineObj['OcrCode'] = (string) $ocrCode;
                    }
                    if (!empty($ocrCode2)) {
                        $lineObj['OcrCode2'] = (string) $ocrCode2;
                    }
                    if (!empty($ocrCode3)) {
                        $lineObj['OcrCode3'] = (string) $ocrCode3;
                    }

                    $lines[] = $lineObj;
                }
            } else {
                foreach ($items as $item) {
                    $itemCode = trim((string) $item->item_code);
                    $qty = floatval($item->pick_qty);
                    if (empty($itemCode) || $qty <= 0) {
                        continue;
                    }

                    $detail = null;
                    $baseLine = 0;
                    if ($item->sales_order_detail_id) {
                        $detail = $soDetails->firstWhere('id', $item->sales_order_detail_id);
                        if ($detail) {
                            $idx = $soDetails->search(fn($d) => $d->id === $detail->id);
                            $baseLine = $idx !== false ? $idx : 0;
                        }
                    }
                    if (!$detail) {
                        $detail = $soDetails->firstWhere('item_code', $itemCode);
                        if ($detail) {
                            $idx = $soDetails->search(fn($d) => $d->id === $detail->id);
                            $baseLine = $idx !== false ? $idx : 0;
                        }
                    }

                    $baseEntry = (int) ($payload['BaseEntry'] ?? $payload['base_entry'] ?? ($so->sap_doc_entry ?: 1));

                    $lineObj = [
                        'BaseEntry' => $baseEntry,
                        'BaseLine'  => (int) $baseLine,
                        'Quantity'  => $qty,
                    ];

                    $whsCode = $item->whs_code ?: ($detail?->whs_code ?: null);
                    if (!empty($whsCode)) {
                        $lineObj['WhsCode'] = (string) $whsCode;
                    }

                    $ocrCode  = $detail?->ocr_code;
                    $ocrCode2 = $detail?->ocr_code2;
                    $ocrCode3 = $detail?->ocr_code3;

                    if (!empty($ocrCode)) {
                        $lineObj['OcrCode'] = (string) $ocrCode;
                    }
                    if (!empty($ocrCode2)) {
                        $lineObj['OcrCode2'] = (string) $ocrCode2;
                    }
                    if (!empty($ocrCode3)) {
                        $lineObj['OcrCode3'] = (string) $ocrCode3;
                    }

                    $lines[] = $lineObj;
                }
            }

            if (empty($lines)) {
                throw new \Exception("No valid lines with pick quantity > 0 for Sales Order #{$so->order_no}.", 400);
            }

            // Header mapping for this SO
            $postingDate = $picklist->posting_date ? Carbon::parse($picklist->posting_date) : now();
            $dueDate = $picklist->due_date ? Carbon::parse($picklist->due_date) : $postingDate;

            $docDateFormatted = !empty($payload['DocDate'])
                ? Carbon::parse($payload['DocDate'])->format('Y-m-d\TH:i:s')
                : $postingDate->format('Y-m-d\TH:i:s');
            $docDueDateFormatted = !empty($payload['DocDueDate'])
                ? Carbon::parse($payload['DocDueDate'])->format('Y-m-d\TH:i:s')
                : $dueDate->format('Y-m-d\TH:i:s');
            $taxDateFormatted = !empty($payload['TaxDate'])
                ? Carbon::parse($payload['TaxDate'])->format('Y-m-d\TH:i:s')
                : $postingDate->format('Y-m-d\TH:i:s');

            $series = isset($payload['Series'])
                ? (int) $payload['Series']
                : (isset($payload['series']) ? (int) $payload['series'] : (!empty($picklist->series) ? (int) $picklist->series : (is_numeric($so->series) ? (int) $so->series : 75)));

            $numAtCard = (string) ($payload['NumAtCard'] ?? $payload['num_at_card'] ?? $so->po_number ?: ($so->order_no ?? ''));
            $comments  = (string) ($payload['Comments'] ?? $payload['comments'] ?? $picklist->comments ?: ($so->comments ?? ''));
            $noPol     = (string) ($payload['NoPol'] ?? $payload['nopol'] ?? $picklist->license_plate ?: ($so->nopol ?? ''));
            $kodeEksp  = (string) ($payload['KodeEkspedisi'] ?? $payload['kode_ekspedisi'] ?? ($picklist->expedition?->code ?? ($picklist->shipping_type === Picklist::SHIPPING_TYPE_INTERNAL ? 'INTERNAL' : '')));
            $namaEksp  = (string) ($payload['NamaEkspedisi'] ?? $payload['nama_ekspedisi'] ?? ($picklist->expedition_name ?? ($picklist->shipping_type === Picklist::SHIPPING_TYPE_INTERNAL ? 'Armada Sendiri' : '')));
            $sopir     = (string) ($payload['Sopir'] ?? $payload['sopir'] ?? $picklist->driver_name ?: ($so->nama_supir ?? ''));
            $checker   = (string) ($payload['NamaChecker'] ?? $payload['nama_checker'] ?? $picklist->checker_name ?? '');
            $noseal    = (string) ($payload['Noseal'] ?? $payload['noseal'] ?? $picklist->seal_number ?? '');

            $headerObj = [
                'Series'        => $series,
                'DocDate'       => $docDateFormatted,
                'DocDueDate'    => $docDueDateFormatted,
                'TaxDate'       => $taxDateFormatted,
                'NumAtCard'     => $numAtCard,
                'Comments'      => $comments,
                'NoPol'         => $noPol,
                'KodeEkspedisi' => $kodeEksp,
                'NamaEkspedisi' => $namaEksp,
                'Sopir'         => $sopir,
                'NamaChecker'   => $checker,
                'AddonId'       => 2,
                'UserId'        => (string) ($userId ?: ($payload['UserId'] ?? $payload['user_id'] ?? '1')),
                'Lines'         => $lines,
            ];

            if (!empty($noseal)) {
                $headerObj['Noseal'] = $noseal;
            }

            if (!empty($payload['CardCode']) || !empty($payload['card_code'])) {
                $headerObj['CardCode'] = (string) ($payload['CardCode'] ?? $payload['card_code']);
            }

            $batchPayload[] = $headerObj;
            $orderContexts[] = [
                'so'    => $so,
                'items' => $items,
            ];
        }

        if (empty($batchPayload)) {
            throw new \Exception("No valid sales orders to generate Delivery Order.", 400);
        }

        try {
            $response = Http::timeout(60)->post("{$sapUrl}/api/AddDO", $batchPayload);
        } catch (\Throwable $e) {
            Log::error("Failed to connect to SAP /api/AddDO for Picklist #{$picklistId}: " . $e->getMessage(), ['payload' => $batchPayload]);
            throw new \Exception("Failed to connect to SAP API for Delivery Order: " . $e->getMessage(), 400);
        }

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            Log::error("SAP /api/AddDO returned HTTP {$status} for Picklist #{$picklistId}: {$body}");
            throw new \Exception("Failed to process Delivery Order in SAP (HTTP {$status}): " . substr($body, 0, 250), 400);
        }

        $result = $response->json();
        if (isset($result['ErrorCode']) && (int) $result['ErrorCode'] !== 0) {
            $errMsg = $result['Message'] ?? 'Unknown SAP error during Delivery Order.';
            Log::error("SAP /api/AddDO returned ErrorCode {$result['ErrorCode']} for Picklist #{$picklistId}: {$errMsg}");
            throw new \Exception("SAP Delivery Order Error [{$result['ErrorCode']}]: {$errMsg}", 400);
        }

        // Parse result for each document
        $rawResult = $result['Result'] ?? $result['result'] ?? null;
        $msg = $result['Message'] ?? $result['message'] ?? '';

        $allDocNumsFromMsg = [];
        $allDocEntriesFromMsg = [];
        if (!empty($msg)) {
            if (preg_match_all('/DocNum:\s*([A-Za-z0-9_-]+)/i', $msg, $mNums)) {
                $allDocNumsFromMsg = $mNums[1];
            }
            if (preg_match_all('/DocEntry:\s*(\d+)/i', $msg, $mEntries)) {
                $allDocEntriesFromMsg = $mEntries[1];
            }
        }

        $resultsPerOrder = [];
        $latestDocNum = null;
        $latestDocEntry = null;

        foreach ($orderContexts as $index => $ctx) {
            $so = $ctx['so'];
            $docEntry = null;
            $docNum = null;

            if (is_array($rawResult)) {
                if (isset($rawResult[$index])) {
                    $itemRes = $rawResult[$index];
                    if (is_array($itemRes)) {
                        $docEntry = $itemRes['DocEntry'] ?? $itemRes['doc_entry'] ?? null;
                        $docNum   = $itemRes['DocNum'] ?? $itemRes['doc_num'] ?? null;
                    } elseif (is_numeric($itemRes) || is_string($itemRes)) {
                        $docEntry = (string) $itemRes;
                    }
                } elseif ($index === 0 && isset($rawResult['DocEntry'])) {
                    $docEntry = $rawResult['DocEntry'] ?? $rawResult['doc_entry'] ?? null;
                    $docNum   = $rawResult['DocNum'] ?? $rawResult['doc_num'] ?? null;
                }
            } elseif (is_numeric($rawResult) || is_string($rawResult)) {
                $docEntry = (string) $rawResult;
            }

            if (!$docNum && isset($allDocNumsFromMsg[$index])) {
                $docNum = $allDocNumsFromMsg[$index];
            }
            if (!$docEntry && isset($allDocEntriesFromMsg[$index])) {
                $docEntry = $allDocEntriesFromMsg[$index];
            }

            $docNum   = $docNum ? (string) $docNum : ($docEntry ? (string) $docEntry : 'PROCESSED');
            $docEntry = $docEntry ? (string) $docEntry : $docNum;

            $latestDocNum = $docNum;
            $latestDocEntry = $docEntry;

            // 1. Update Sales Order
            $so->update([
                'delivery_order_no' => $docNum,
                'sap_do_doc_entry'  => (string) $docEntry,
                'sap_do_doc_num'    => (string) $docNum,
                'sap_do_status'     => 'SUCCESS',
                'sap_last_doc_type' => 'DO',
                'sap_last_doc_num'  => (string) $docNum,
                'delivery_date'     => now(),
            ]);

            // 2. Create Sales Order Logistic Log
            SalesOrderLogisticLog::create([
                'sales_order_id'   => $so->id,
                'action'           => 'LOGISTIC_DELIVERY_ORDER',
                'from_status'      => $so->logistic_status,
                'to_status'        => $so->logistic_status,
                'notes'            => "Delivery Order (DO) processed to SAP via Picklist {$picklist->picklist_no} (DocNum: {$docNum}, DocEntry: {$docEntry}).",
                'user_id'          => $userId,
                'user_name'        => $userName,
                'role_name'        => $roleName,
                'sap_it_doc_entry' => (string) $docEntry,
                'sap_it_doc_num'   => (string) $docNum,
            ]);

            // 3. Update picklist_items delivery_order_no
            PicklistItem::where('picklist_id', $picklist->id)
                ->where('sales_order_id', $so->id)
                ->update(['delivery_order_no' => $docNum]);

            $resultsPerOrder[] = [
                'sales_order_id'    => $so->id,
                'order_no'          => $so->order_no,
                'delivery_order_no' => $docNum,
                'doc_entry'         => $docEntry,
                'doc_num'           => $docNum,
                'response'          => $result,
            ];
        }

        // 4. Update picklist delivery_order_no and DO fields
        $picklistDocNums = array_filter(array_map(fn($r) => $r['delivery_order_no'], $resultsPerOrder));
        $combinedDocNum = !empty($picklistDocNums) ? implode(', ', array_unique($picklistDocNums)) : $latestDocNum;

        $picklist->update([
            'delivery_order_no' => $combinedDocNum,
            'do_doc_entry'      => (string) $latestDocEntry,
            'do_doc_num'        => (string) $latestDocNum,
            'do_status'         => 'SUCCESS',
            'updated_by'        => $userId,
        ]);

        return [
            'picklist_id'       => $picklist->id,
            'picklist_no'       => $picklist->picklist_no,
            'delivery_order_no' => $combinedDocNum,
            'doc_entry'         => (string) $latestDocEntry,
            'doc_num'           => (string) $latestDocNum,
            'results'           => $resultsPerOrder,
            'picklist'          => $picklist->fresh(['items.salesOrder', 'creator', 'updater']),
        ];
    }

    /**
     * Direct Add Delivery Order (DO) to SAP B1 with fallback/lookup to local SO and Picklist.
     *
     * @param array $payload
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function directAddDeliveryOrder(array $payload, ?int $userId = null): array
    {
        // Support either single document payload or array of documents
        $isBatch = isset($payload[0]) && is_array($payload[0]);
        $batchPayload = $isBatch ? $payload : [$payload];

        foreach ($batchPayload as &$header) {
            $header['AddonId'] = 2;
            $header['UserId']  = (string) ($userId ?: ($header['UserId'] ?? $header['user_id'] ?? '1'));
        }
        unset($header);

        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        try {
            $response = Http::timeout(60)->post("{$sapUrl}/api/AddDO", $batchPayload);
        } catch (\Throwable $e) {
            Log::error("Failed to connect to SAP /api/AddDO: " . $e->getMessage(), ['payload' => $batchPayload]);
            throw new \Exception("Failed to connect to SAP API for Delivery Order: " . $e->getMessage(), 400);
        }

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            Log::error("SAP /api/AddDO returned HTTP {$status}: {$body}");
            throw new \Exception("Failed to process Delivery Order in SAP (HTTP {$status}): " . substr($body, 0, 250), 400);
        }

        $result = $response->json();
        if (isset($result['ErrorCode']) && (int) $result['ErrorCode'] !== 0) {
            $errMsg = $result['Message'] ?? 'Unknown SAP error during Delivery Order.';
            Log::error("SAP /api/AddDO returned ErrorCode {$result['ErrorCode']}: {$errMsg}");
            throw new \Exception("SAP Delivery Order Error [{$result['ErrorCode']}]: {$errMsg}", 400);
        }

        $rawResult = $result['Result'] ?? $result['result'] ?? null;
        $msg = $result['Message'] ?? $result['message'] ?? '';

        $allDocNumsFromMsg = [];
        $allDocEntriesFromMsg = [];
        if (!empty($msg)) {
            if (preg_match_all('/DocNum:\s*([A-Za-z0-9_-]+)/i', $msg, $mNums)) {
                $allDocNumsFromMsg = $mNums[1];
            }
            if (preg_match_all('/DocEntry:\s*(\d+)/i', $msg, $mEntries)) {
                $allDocEntriesFromMsg = $mEntries[1];
            }
        }

        $processedDocs = [];
        $latestDocNum = null;
        $latestDocEntry = null;
        $firstSo = null;
        $firstPicklist = null;

        foreach ($batchPayload as $index => $docPayload) {
            $docEntry = null;
            $docNum = null;

            if (is_array($rawResult)) {
                if (isset($rawResult[$index])) {
                    $itemRes = $rawResult[$index];
                    if (is_array($itemRes)) {
                        $docEntry = $itemRes['DocEntry'] ?? $itemRes['doc_entry'] ?? null;
                        $docNum   = $itemRes['DocNum'] ?? $itemRes['doc_num'] ?? null;
                    } elseif (is_numeric($itemRes) || is_string($itemRes)) {
                        $docEntry = (string) $itemRes;
                    }
                } elseif ($index === 0 && isset($rawResult['DocEntry'])) {
                    $docEntry = $rawResult['DocEntry'] ?? $rawResult['doc_entry'] ?? null;
                    $docNum   = $rawResult['DocNum'] ?? $rawResult['doc_num'] ?? null;
                }
            } elseif (is_numeric($rawResult) || is_string($rawResult)) {
                $docEntry = (string) $rawResult;
            }

            if (!$docNum && isset($allDocNumsFromMsg[$index])) {
                $docNum = $allDocNumsFromMsg[$index];
            }
            if (!$docEntry && isset($allDocEntriesFromMsg[$index])) {
                $docEntry = $allDocEntriesFromMsg[$index];
            }

            $docNum   = $docNum ? (string) $docNum : ($docEntry ? (string) $docEntry : 'PROCESSED');
            $docEntry = $docEntry ? (string) $docEntry : $docNum;

            $latestDocNum = $docNum;
            $latestDocEntry = $docEntry;

            // Find associated Sales Order if possible
            $so = null;
            if (!empty($docPayload['sales_order_id'])) {
                $so = SalesOrder::find($docPayload['sales_order_id']);
            }
            if (!$so && !empty($docPayload['Lines'][0]['BaseEntry'])) {
                $baseEntry = $docPayload['Lines'][0]['BaseEntry'];
                $so = SalesOrder::where('sap_doc_entry', is_numeric($baseEntry) ? (int)$baseEntry : 0)->first();
            }
            if (!$so && !empty($docPayload['NumAtCard'])) {
                $so = SalesOrder::where('po_number', $docPayload['NumAtCard'])
                    ->orWhere('order_no', $docPayload['NumAtCard'])
                    ->first();
            }

            $picklist = null;
            if (!empty($docPayload['picklist_id'])) {
                $picklist = Picklist::find($docPayload['picklist_id']);
            }

            if ($so) {
                if (!$firstSo) {
                    $firstSo = $so;
                }
                $so->update([
                    'delivery_order_no' => $docNum,
                    'sap_do_doc_entry'  => (string) $docEntry,
                    'sap_do_doc_num'    => (string) $docNum,
                    'sap_do_status'     => 'SUCCESS',
                    'sap_last_doc_type' => 'DO',
                    'sap_last_doc_num'  => (string) $docNum,
                    'delivery_date'     => now(),
                ]);

                if (!$picklist) {
                    $pItem = PicklistItem::where('sales_order_id', $so->id)->orderBy('id', 'desc')->first();
                    if ($pItem) {
                        $picklist = Picklist::find($pItem->picklist_id);
                    }
                }
            }

            if ($picklist) {
                if (!$firstPicklist) {
                    $firstPicklist = $picklist;
                }
                $picklist->update([
                    'delivery_order_no' => $docNum,
                    'do_doc_entry'      => (string) $docEntry,
                    'do_doc_num'        => (string) $docNum,
                    'do_status'         => 'SUCCESS',
                    'updated_by'        => $userId,
                ]);

                if ($so) {
                    PicklistItem::where('picklist_id', $picklist->id)
                        ->where('sales_order_id', $so->id)
                        ->update(['delivery_order_no' => $docNum]);
                }
            }

            $processedDocs[] = [
                'doc_entry'       => (string) $docEntry,
                'doc_num'         => (string) $docNum,
                'sales_order_id'  => $so?->id,
                'order_no'        => $so?->order_no,
                'picklist_id'     => $picklist?->id,
                'picklist_no'     => $picklist?->picklist_no,
            ];
        }

        return [
            'delivery_order_no' => (string) $latestDocNum,
            'doc_entry'         => (string) $latestDocEntry,
            'doc_num'           => (string) $latestDocNum,
            'sales_order_id'    => $firstSo?->id,
            'picklist_id'       => $firstPicklist?->id,
            'documents'         => $processedDocs,
            'response'          => $result,
        ];
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
