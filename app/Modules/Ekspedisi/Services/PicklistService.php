<?php

namespace App\Modules\Ekspedisi\Services;

use App\Models\Item;
use App\Models\LogisticSetting;
use App\Models\Picklist;
use App\Models\PicklistItem;
use App\Models\PicklistItemLabelPhoto;
use App\Models\PicklistSignature;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\SalesOrderLogisticLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PicklistService
{
    /**
     * Hardcoded destination warehouse code for Picklist Inventory Transfer.
     */
    public const DEFAULT_IT_TO_WHS_CODE = 'VPGMN01';

    /**
     * Get the Inventory Transfer (IT) warehouse code for Delivery Order lines.
     * Fills the WhsCode of Delivery Order lines from the destination warehouse of IT.
     * Currently resolves from Picklist to_whs_code or DEFAULT_IT_TO_WHS_CODE ('VPGMN01').
     * In the future, this can be retrieved from a database master setting table.
     *
     * @param Picklist|null $picklist
     * @return string
     */
    public function getItWarehouseCode(?Picklist $picklist = null): string
    {
        if ($picklist && !empty($picklist->to_whs_code)) {
            return (string) $picklist->to_whs_code;
        }

        return LogisticSetting::getVirtualWarehouseCode();
    }

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

        // Filter by status (support both lowercase and uppercase)
        if (!empty($filters['status'])) {
            $st = trim((string) $filters['status']);
            $query->where(function (Builder $q) use ($st) {
                $q->where('status', strtolower($st))
                    ->orWhere('status', strtoupper($st));
            });
        }

        // Filter by is_checked status
        if (isset($filters['is_checked']) && $filters['is_checked'] !== '' && $filters['is_checked'] !== null) {
            $isCheckedVal = filter_var($filters['is_checked'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isCheckedVal !== null) {
                if ($isCheckedVal) {
                    $query->whereDoesntHave('items', function (Builder $iq) {
                        $iq->where('is_checked', false);
                    })->whereHas('items');
                } else {
                    $query->whereHas('items', function (Builder $iq) {
                        $iq->where('is_checked', false);
                    });
                }
            }
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
            'items.salesOrder:id,order_no,sap_doc_num,customer_name,card_code,address,address2,distributor_id,docentry,sap_doc_entry',
            'items.salesOrder.distributor:id,name,code_customer,depo',
            'items.salesOrderDetail:id,sales_order_id,item_code,line_num,baseline,whs_code,quantity',
            'items.item:id,item_code,item_name,per_kg',
            'items.labelPhotos',
            'creator:id,name,username',
            'updater:id,name,username',
            'signatures.creator:id,name',
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
                    'series'           => $order->series,
                    'series_name'      => $order->series_name,
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

        $itToWhsCode = $this->getItWarehouseCode();

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
            $userId,
            $itToWhsCode
        ) {
            $picklistNo = $this->generatePicklistNumber();

            $picklistData = [
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
                'expedition_id'      => (isset($payload['expedition_id']) && trim((string) $payload['expedition_id']) !== '') ? trim((string) $payload['expedition_id']) : null,
                'expedition_name'    => !empty($payload['expedition_name']) ? trim((string) $payload['expedition_name']) : null,
                'expedition_rate_id' => !empty($payload['expedition_rate_id']) ? (int) $payload['expedition_rate_id'] : null,
                'service_type'       => !empty($payload['service_type']) ? trim((string) $payload['service_type']) : null,
                'estimated_cost'     => isset($payload['estimated_cost']) ? (float) $payload['estimated_cost'] : null,
                'it_doc_entry'       => (string) $itResult['doc_entry'],
                'it_doc_num'         => (string) $itResult['doc_num'],
                'it_status'          => 'SUCCESS',
                'to_whs_code'        => $itToWhsCode,
                'series'             => (isset($payload['series']) && $payload['series'] !== '' && $payload['series'] !== null) ? (int) $payload['series'] : ((isset($payload['Series']) && $payload['Series'] !== '' && $payload['Series'] !== null) ? (int) $payload['Series'] : null),
                'series_name'        => !empty($payload['series_name']) ? trim((string) $payload['series_name']) : (!empty($payload['SeriesName']) ? trim((string) $payload['SeriesName']) : null),
                'seal_number'        => !empty($payload['seal_number']) ? trim((string) $payload['seal_number']) : (!empty($payload['noseal']) ? trim((string) $payload['noseal']) : (!empty($payload['Noseal']) ? trim((string) $payload['Noseal']) : null)),
                'created_by'         => $userId,
                'updated_by'         => $userId,
            ];

            try {
                $picklist = Picklist::create($picklistData);
            } catch (\Throwable $e) {
                // Handle unmigrated DB constraint in production: picklists_expedition_id_foreign
                if (str_contains($e->getMessage(), 'picklists_expedition_id_foreign') || $e->getCode() == '23503') {
                    try {
                        DB::connection('pgsql_ekspedisi')->statement('ALTER TABLE IF EXISTS ekspedisi.picklists DROP CONSTRAINT IF EXISTS picklists_expedition_id_foreign');
                        DB::connection('pgsql_ekspedisi')->statement('ALTER TABLE IF EXISTS ekspedisi.picklists ALTER COLUMN expedition_id TYPE VARCHAR(100)');
                        $picklist = Picklist::create($picklistData);
                    } catch (\Throwable $alterEx) {
                        // Fallback: If DDL could not be executed or table locked, set expedition_id to null for internal shipping
                        if (strtolower((string) $shippingType) === Picklist::SHIPPING_TYPE_INTERNAL) {
                            $picklistData['expedition_id'] = null;
                            $picklist = Picklist::create($picklistData);
                        } else {
                            throw $e;
                        }
                    }
                } else {
                    throw $e;
                }
            }

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
                        'to_whs_code'      => $itToWhsCode,
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
                        'notes'            => "Inventory Transfer (IT) processed to SAP via Picklist {$picklistNo} (DocNum: {$itResult['doc_num']}, Destination: {$itToWhsCode}).",
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
        $toWhsCode = $this->getItWarehouseCode();
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
                'ToWhsCode'    => $toWhsCode,
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
            'ToWhsCode'  => $toWhsCode,
            'Comments'   => "Picklist IT to {$toWhsCode}" . ($licensePlate ? " - Nopol: {$licensePlate}" : '') . (!empty($payload['comments']) ? " - {$payload['comments']}" : ''),
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

        $statusLower = strtolower(trim((string)$picklist->status));
        if (in_array($statusLower, [Picklist::STATUS_CANCELED, 'cancelled', 'canceled'], true)) {
            throw new \Exception("Cannot process a CANCELLED picklist.", 400);
        }

        // Support draft mode for multi-checker / multi-warehouse workflow
        $isDraft = (bool) ($payload['is_draft'] ?? $payload['draft'] ?? (($payload['action'] ?? '') === 'draft'));
        if ($isDraft) {
            return $this->saveDraftProgress($picklist, $payload, $userId, isSubmit: false);
        }

        // Under new logistics workflow, checker submission via add-do (is_draft=false) saves verification,
        // start_time, end_time, production_date, label_photos and advances status to weigh_out (if all items checked)
        // or inprogress (if pending items), and DOES NOT directly call SAP AddDO.
        $forceSapDo = (bool) ($payload['force_sap_do'] ?? $payload['create_do'] ?? false);
        if (!$forceSapDo) {
            return $this->saveDraftProgress($picklist, $payload, $userId, isSubmit: true);
        }

        if ($picklist->items->isEmpty()) {
            throw new \Exception("Picklist #{$picklistId} has no item lines.", 400);
        }

        // Filter by sales_order_id if specified in payload or in lines
        $filterSoId = !empty($payload['sales_order_id']) ? (int) $payload['sales_order_id'] : null;

        $customLines = $payload['Lines'] ?? $payload['lines'] ?? null;
        $lineSoIds = [];
        if (is_array($customLines)) {
            foreach ($customLines as $cLine) {
                $cSoId = $cLine['sales_order_id'] ?? $cLine['sales_order_Id'] ?? null;
                if (!empty($cSoId)) {
                    $lineSoIds[] = (int) $cSoId;
                }
            }
            $lineSoIds = array_unique($lineSoIds);
        }

        // Group picklist items by sales_order_id
        $groupedItems = $picklist->items->toBase()->groupBy('sales_order_id');
        if ($filterSoId) {
            if (!$groupedItems->has($filterSoId)) {
                throw new \Exception("Sales Order #{$filterSoId} is not part of picklist #{$picklistId}.", 400);
            }
            $groupedItems = $groupedItems->filter(fn($val, $k) => (int) $k === (int) $filterSoId);
        } elseif (!empty($lineSoIds)) {
            $filteredGroups = $groupedItems->filter(fn($val, $k) => in_array((int) $k, $lineSoIds, true));
            if ($filteredGroups->isNotEmpty()) {
                $groupedItems = $filteredGroups;
            }
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

            // Validasi BaseEntry dari Sales Order (resolving integer DocEntry instead of DocNum)
            $candidateBe = $payload['BaseEntry'] ?? $payload['base_entry'] ?? $payload['DocEntry'] ?? $payload['doc_entry'] ?? null;
            if (empty($candidateBe) && !empty($payload['Lines'][0])) {
                $candidateBe = $payload['Lines'][0]['BaseEntry'] ?? $payload['Lines'][0]['base_entry'] ?? null;
            }
            if (empty($candidateBe) && !empty($payload['lines'][0])) {
                $candidateBe = $payload['lines'][0]['BaseEntry'] ?? $payload['lines'][0]['base_entry'] ?? null;
            }

            $baseEntry = $this->resolveSalesOrderDocEntry($so, $candidateBe);
            if (empty($baseEntry)) {
                throw new \Exception("Sales Order #{$so->order_no} belum memiliki DocEntry (belum approved / terintegrasi ke SAP). Tidak bisa Add DO.", 400);
            }

            $soDetails = $so->details ? $so->details->sortBy('id')->values() : collect();

            // Build Lines for this SO
            $lines = [];
            $matchingCustomLines = null;
            if (is_array($customLines) && !empty($customLines)) {
                $matchingCustomLines = array_filter($customLines, function ($cLine) use ($soId, $lineSoIds) {
                    $cSoId = $cLine['sales_order_id'] ?? $cLine['sales_order_Id'] ?? null;
                    if (!empty($cSoId)) {
                        return (int) $cSoId === (int) $soId;
                    }
                    return empty($lineSoIds);
                });
            }

            if (is_array($matchingCustomLines) && !empty($matchingCustomLines)) {
                foreach ($matchingCustomLines as $lineIdx => $cLine) {
                    $qty = floatval($cLine['Quantity'] ?? $cLine['quantity'] ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }

                    $itemCode = trim((string) ($cLine['ItemCode'] ?? $cLine['item_code'] ?? ''));

                    $matchedDetail = null;
                    if (!empty($cLine['sales_order_detail_id'])) {
                        $matchedDetail = $soDetails->firstWhere('id', (int) $cLine['sales_order_detail_id']);
                    }
                    if (!$matchedDetail && !empty($itemCode)) {
                        $matchedDetail = $soDetails->first(function ($d) use ($itemCode) {
                            return strtolower(trim((string)$d->item_code)) === strtolower(trim($itemCode));
                        });
                    }
                    if (!$matchedDetail && isset($cLine['BaseLine'])) {
                        $matchedDetail = $soDetails->firstWhere('baseline', (int) $cLine['BaseLine']);
                    }
                    if (!$matchedDetail && isset($cLine['baseline'])) {
                        $matchedDetail = $soDetails->firstWhere('baseline', (int) $cLine['baseline']);
                    }
                    if (!$matchedDetail && isset($soDetails[$lineIdx])) {
                        $matchedDetail = $soDetails[$lineIdx];
                    }

                    // Resolve line BaseLine safely (fallback to line index if null)
                    $lineBaseLine = null;
                    if (isset($cLine['BaseLine']) && $cLine['BaseLine'] !== '' && $cLine['BaseLine'] !== null) {
                        $lineBaseLine = (int) $cLine['BaseLine'];
                    } elseif (isset($cLine['baseline']) && $cLine['baseline'] !== '' && $cLine['baseline'] !== null) {
                        $lineBaseLine = (int) $cLine['baseline'];
                    } elseif ($matchedDetail && $matchedDetail->baseline !== null) {
                        $lineBaseLine = (int) $matchedDetail->baseline;
                    } elseif ($matchedDetail && $matchedDetail->line_num !== null) {
                        $lineBaseLine = (int) $matchedDetail->line_num;
                    } else {
                        $dIndex = $matchedDetail ? $soDetails->search(fn($d) => $d->id === $matchedDetail->id) : false;
                        $lineBaseLine = ($dIndex !== false) ? (int) $dIndex : (int) $lineIdx;
                    }

                    // Auto-backfill to DB
                    if ($matchedDetail && $matchedDetail->baseline === null) {
                        try {
                            $matchedDetail->update(['baseline' => $lineBaseLine, 'line_num' => $lineBaseLine]);
                        } catch (\Throwable $e) {}
                    }

                    $lineBaseEntry = (isset($cLine['BaseEntry']) && $cLine['BaseEntry'] !== '' && $cLine['BaseEntry'] !== null)
                        ? (int) $cLine['BaseEntry']
                        : ((isset($cLine['base_entry']) && $cLine['base_entry'] !== '' && $cLine['base_entry'] !== null)
                            ? (int) $cLine['base_entry']
                            : (int) $baseEntry);

                    $lineObj = [
                        'BaseEntry' => $lineBaseEntry,
                        'BaseLine'  => $lineBaseLine,
                        'Quantity'  => $qty,
                        'WhsCode'   => $this->getItWarehouseCode($picklist),
                    ];

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
            } elseif (!empty($items)) {
                foreach ($items as $lineIdx => $item) {
                    $itemCode = trim((string) $item->item_code);
                    $qty = floatval($item->pick_qty);
                    if (empty($itemCode) || $qty <= 0) {
                        continue;
                    }

                    $detail = null;
                    if ($item->sales_order_detail_id) {
                        $detail = $soDetails->firstWhere('id', (int) $item->sales_order_detail_id);
                    }
                    if (!$detail) {
                        $detail = $soDetails->first(function ($d) use ($itemCode) {
                            return strtolower(trim((string)$d->item_code)) === strtolower(trim($itemCode));
                        });
                    }
                    if (!$detail && isset($soDetails[$lineIdx])) {
                        $detail = $soDetails[$lineIdx];
                    }

                    $lineBaseLine = null;
                    if ($detail && $detail->baseline !== null) {
                        $lineBaseLine = (int) $detail->baseline;
                    } elseif ($detail && $detail->line_num !== null) {
                        $lineBaseLine = (int) $detail->line_num;
                    } else {
                        $dIndex = $detail ? $soDetails->search(fn($d) => $d->id === $detail->id) : false;
                        $lineBaseLine = ($dIndex !== false) ? (int) $dIndex : (int) $lineIdx;
                    }

                    if ($detail && $detail->baseline === null) {
                        try {
                            $detail->update(['baseline' => $lineBaseLine, 'line_num' => $lineBaseLine]);
                        } catch (\Throwable $e) {}
                    }

                    $lineObj = [
                        'BaseEntry' => (int) $baseEntry,
                        'BaseLine'  => $lineBaseLine,
                        'Quantity'  => $qty,
                        'WhsCode'   => $this->getItWarehouseCode($picklist),
                    ];

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

            if (is_array($customLines) && empty($lines)) {
                continue;
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

            $series = (isset($payload['Series']) && $payload['Series'] !== '' && $payload['Series'] !== null)
                ? (int) $payload['Series']
                : ((isset($payload['series']) && $payload['series'] !== '' && $payload['series'] !== null)
                    ? (int) $payload['series']
                    : (!empty($picklist->series) ? (int) $picklist->series : 75));

            $numAtCard = (string) ($payload['NumAtCard'] ?? $payload['num_at_card'] ?? $so->po_number ?: ($so->order_no ?? ''));
            $comments  = (string) ($payload['Comments'] ?? $payload['comments'] ?? $picklist->comments ?: ($so->comments ?? ''));
            $noPol     = (string) ($payload['license_plate'] ?? $payload['NoPol'] ?? $payload['nopol'] ?? $picklist->license_plate ?: ($so->nopol ?? ''));
            $kodeEksp  = (string) ($payload['KodeEkspedisi'] ?? $payload['kode_ekspedisi'] ?? ($picklist->expedition_id ?: ($picklist->expedition?->code ?? ($picklist->shipping_type === Picklist::SHIPPING_TYPE_INTERNAL ? '01' : ''))));
            $namaEksp  = (string) ($payload['NamaEkspedisi'] ?? $payload['nama_ekspedisi'] ?? ($picklist->expedition_name ?? ($picklist->shipping_type === Picklist::SHIPPING_TYPE_INTERNAL ? 'Internal' : '')));
            $sopir     = (string) ($payload['driver_name'] ?? $payload['Sopir'] ?? $payload['sopir'] ?? $picklist->driver_name ?: ($so->nama_supir ?? ''));
            $checker   = (string) ($payload['checker_name'] ?? $payload['NamaChecker'] ?? $payload['nama_checker'] ?? $picklist->checker_name ?? '');
            $noseal    = (string) ($payload['Noseal'] ?? $payload['noseal'] ?? ($payload['seal_number'] ?? $picklist->seal_number ?? ''));

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
                'Container'     => (string) ($payload['Container'] ?? $payload['container'] ?? ''),
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

        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        $resultsPerOrder = [];
        $latestDocNum = null;
        $latestDocEntry = null;

        // Post each Delivery Order document as a single object to SAP B1 /api/AddDO
        foreach ($batchPayload as $index => $headerObj) {
            $ctx = $orderContexts[$index];
            $so = $ctx['so'];

            try {
                $response = Http::timeout(60)->post("{$sapUrl}/api/AddDO", $headerObj);
            } catch (\Throwable $e) {
                Log::error("Failed to connect to SAP /api/AddDO for Picklist #{$picklistId}: " . $e->getMessage(), ['payload' => $headerObj]);
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

            $rawResult = $result['Result'] ?? $result['result'] ?? null;
            $msg = $result['Message'] ?? $result['message'] ?? '';

            $docNum = null;
            $docEntry = null;

            if (is_array($rawResult)) {
                $docEntry = $rawResult['DocEntry'] ?? $rawResult['doc_entry'] ?? null;
                $docNum   = $rawResult['DocNum'] ?? $rawResult['doc_num'] ?? null;
            } elseif (is_numeric($rawResult) || is_string($rawResult)) {
                $docEntry = (string) $rawResult;
            }

            if (!empty($msg)) {
                if (!$docNum && preg_match('/DocNum(?:s)?:\s*([A-Za-z0-9_-]+)/i', $msg, $mNums)) {
                    $docNum = $mNums[1];
                }
                if (!$docEntry && preg_match('/DocEntr(?:y|ies):\s*([0-9]+(?:,\s*[0-9]+)*)/i', $msg, $mEntries)) {
                    $matchedEntries = explode(',', $mEntries[1]);
                    $docEntry = trim($matchedEntries[0]);
                }
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

            // 3. Update picklist_items delivery_order_no and is_checked
            $orderItems = PicklistItem::where('picklist_id', $picklist->id)
                ->where('sales_order_id', $so->id)
                ->get();

            $activeChecker = !empty($payload['checked_by_checker'])
                ? trim((string) $payload['checked_by_checker'])
                : (!empty($payload['checker_name'])
                    ? trim((string) $payload['checker_name'])
                    : ($picklist->checker_name ?: $userName));

            $activeCheckedAt = !empty($payload['checked_at'])
                ? \Carbon\Carbon::parse($payload['checked_at'])
                : now();

            foreach ($orderItems as $oItem) {
                $itemUpdate = [
                    'delivery_order_no' => $docNum,
                ];

                $matchingLine = null;
                if (!empty($customLines) && is_array($customLines)) {
                    foreach ($customLines as $cL) {
                        $cItemCode = $cL['ItemCode'] ?? $cL['item_code'] ?? null;
                        $cSoId = $cL['sales_order_id'] ?? $cL['sales_order_Id'] ?? null;
                        if ($cItemCode && strtolower(trim((string)$cItemCode)) === strtolower(trim((string)$oItem->item_code))) {
                            if (empty($cSoId) || (int) $cSoId === (int) $so->id) {
                                $matchingLine = $cL;
                                break;
                            }
                        }
                    }
                }

                if ($matchingLine) {
                    $itemUpdate['is_checked'] = isset($matchingLine['is_checked'])
                        ? (bool) $matchingLine['is_checked']
                        : (isset($matchingLine['isChecked']) ? (bool) $matchingLine['isChecked'] : true);

                    if (isset($matchingLine['checked_qty'])) {
                        $itemUpdate['checked_qty'] = (float) $matchingLine['checked_qty'];
                    } elseif (isset($matchingLine['quantity'])) {
                        $itemUpdate['checked_qty'] = (float) $matchingLine['quantity'];
                    } elseif (isset($matchingLine['Quantity'])) {
                        $itemUpdate['checked_qty'] = (float) $matchingLine['Quantity'];
                    }

                    if (!empty($matchingLine['checked_by_checker'])) {
                        $itemUpdate['checked_by_checker'] = trim((string) $matchingLine['checked_by_checker']);
                    } else {
                        $itemUpdate['checked_by_checker'] = $activeChecker;
                    }

                    if (!empty($matchingLine['checked_at'])) {
                        $itemUpdate['checked_at'] = \Carbon\Carbon::parse($matchingLine['checked_at']);
                    } else {
                        $itemUpdate['checked_at'] = $activeCheckedAt;
                    }
                } else {
                    $headerChecked = isset($payload['is_checked'])
                        ? (bool) $payload['is_checked']
                        : (isset($payload['isChecked']) ? (bool) $payload['isChecked'] : true);
                    $itemUpdate['is_checked'] = $headerChecked;
                    if ($headerChecked) {
                        $itemUpdate['checked_qty'] = (float) ($oItem->checked_qty ?: $oItem->pick_qty);
                        $itemUpdate['checked_by_checker'] = $activeChecker;
                        $itemUpdate['checked_at'] = $activeCheckedAt;
                    }
                }

                $oItem->update($itemUpdate);
            }

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

        $picklistUpdate = [
            'status'            => Picklist::STATUS_COMPLETED,
            'delivery_order_no' => $combinedDocNum,
            'do_doc_entry'      => (string) $latestDocEntry,
            'do_doc_num'        => (string) $latestDocNum,
            'do_status'         => 'SUCCESS',
            'updated_by'        => $userId,
        ];

        if (!empty($noPol)) {
            $picklistUpdate['license_plate'] = $noPol;
        }
        if (!empty($sopir)) {
            $picklistUpdate['driver_name'] = $sopir;
        }
        if (!empty($checker)) {
            $picklistUpdate['checker_name'] = $checker;
        }
        if (!empty($kodeEksp)) {
            $picklistUpdate['expedition_id'] = $kodeEksp;
        }
        if (!empty($namaEksp)) {
            $picklistUpdate['expedition_name'] = $namaEksp;
        }
        if (!empty($comments)) {
            $picklistUpdate['comments'] = $comments;
        }
        if (!empty($noseal)) {
            $picklistUpdate['seal_number'] = $noseal;
        }

        $picklist->update($picklistUpdate);

        // 5. Save inspection / handover signatures if provided
        $savedSignatures = $this->savePicklistSignatures($picklist, $payload, $userId);
        $freshPicklist = $picklist->fresh(['items.salesOrder', 'creator', 'updater', 'signatures']);

        return [
            'picklist_id'       => $picklist->id,
            'picklist_no'       => $picklist->picklist_no,
            'delivery_order_no' => $combinedDocNum,
            'doc_entry'         => (string) $latestDocEntry,
            'doc_num'           => (string) $latestDocNum,
            'is_checked'        => (bool) $freshPicklist->is_checked,
            'results'           => $resultsPerOrder,
            'signatures'        => $savedSignatures,
            'picklist'          => $freshPicklist,
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
        $rawDocs = $isBatch ? $payload : [$payload];
        $firstDoc = $rawDocs[0] ?? [];

        // Check for draft action / flag
        $isDraft = (bool) ($firstDoc['is_draft'] ?? $firstDoc['draft'] ?? (($firstDoc['action'] ?? '') === 'draft') ?? ($payload['is_draft'] ?? false));
        if ($isDraft) {
            $picklistId = $firstDoc['picklist_id'] ?? $firstDoc['picklistId'] ?? ($payload['picklist_id'] ?? null);
            $picklist = null;
            if ($picklistId) {
                $picklist = Picklist::find($picklistId);
            }
            if (!$picklist && !empty($firstDoc['order_no'])) {
                $so = SalesOrder::where('order_no', $firstDoc['order_no'])->first();
                if ($so) {
                    $pItem = PicklistItem::where('sales_order_id', $so->id)->first();
                    $picklist = $pItem?->picklist;
                }
            }
            if (!$picklist && !empty($firstDoc['sales_order_id'])) {
                $pItem = PicklistItem::where('sales_order_id', $firstDoc['sales_order_id'])->first();
                $picklist = $pItem?->picklist;
            }
            if ($picklist) {
                return $this->saveDraftProgress($picklist, $isBatch ? $firstDoc : $payload, $userId);
            }
            throw new \Exception("Picklist ID required for saving draft progress.", 400);
        }

        // Normalize rawDocs: if a doc has lines referencing multiple different sales_order_id,
        // expand/group them so each Sales Order gets its own SAP Delivery Order document in batch mode!
        $expandedDocs = [];
        foreach ($rawDocs as $doc) {
            $lKey = isset($doc['Lines']) ? 'Lines' : (isset($doc['lines']) ? 'lines' : 'Lines');
            $docLines = $doc[$lKey] ?? [];
            if (!is_array($docLines) || empty($docLines)) {
                $expandedDocs[] = $doc;
                continue;
            }

            // Group lines by sales_order_id
            $linesBySo = [];
            foreach ($docLines as $l) {
                $lSoId = $l['sales_order_id'] ?? $l['sales_order_Id'] ?? null;
                $key = $lSoId ? (string) $lSoId : 'default';
                $linesBySo[$key][] = $l;
            }

            if (count($linesBySo) > 1) {
                $isBatch = true;
                foreach ($linesBySo as $soKey => $groupedLines) {
                    $subDoc = $doc;
                    if ($soKey !== 'default') {
                        $subDoc['sales_order_id'] = (int) $soKey;
                    }
                    $subDoc[$lKey] = $groupedLines;
                    $expandedDocs[] = $subDoc;
                }
            } else {
                $expandedDocs[] = $doc;
            }
        }
        $rawDocs = $expandedDocs;

        $batchPayload = [];
        $metaDocs = [];

        foreach ($rawDocs as $header) {
            $linesKey = isset($header['Lines']) ? 'Lines' : (isset($header['lines']) ? 'lines' : 'Lines');
            $headerLines = $header[$linesKey] ?? [];

            if (empty($headerLines) || !is_array($headerLines)) {
                throw new \Exception("Payload DO tidak memiliki baris item (Lines).", 400);
            }

            // 1. Resolve Sales Order
            $so = null;
            if (!empty($header['sales_order_id'])) {
                $so = SalesOrder::with('details')->find($header['sales_order_id']);
                if (!$so) {
                    $so = SalesOrder::with('details')->where('sap_doc_entry', (int) $header['sales_order_id'])
                        ->orWhere('docentry', (int) $header['sales_order_id'])
                        ->first();
                }
            }
            if (!$so && !empty($headerLines[0])) {
                $firstSoId = $headerLines[0]['sales_order_id'] ?? $headerLines[0]['sales_order_Id'] ?? null;
                if ($firstSoId) {
                    $so = SalesOrder::with('details')->find($firstSoId);
                    if (!$so) {
                        $so = SalesOrder::with('details')->where('sap_doc_entry', (int) $firstSoId)
                            ->orWhere('docentry', (int) $firstSoId)
                            ->first();
                    }
                }
            }
            if (!$so && !empty($header['order_no'])) {
                $so = SalesOrder::with('details')->where('order_no', $header['order_no'])->first();
            }
            if (!$so && !empty($header['NumAtCard'])) {
                $numAtCard = trim((string) $header['NumAtCard']);
                $so = SalesOrder::with('details')->where('order_no', $numAtCard)
                    ->orWhere('po_number', $numAtCard)
                    ->first();
            }
            if (!$so && !empty($header['num_at_card'])) {
                $numAtCard = trim((string) $header['num_at_card']);
                $so = SalesOrder::with('details')->where('order_no', $numAtCard)
                    ->orWhere('po_number', $numAtCard)
                    ->first();
            }
            if (!$so && !empty($header['picklist_id'])) {
                $pItem = PicklistItem::where('picklist_id', $header['picklist_id'])->first();
                if ($pItem) {
                    $so = SalesOrder::with('details')->find($pItem->sales_order_id);
                }
            }

            if (!$so && !empty($headerLines[0])) {
                $firstBe = $headerLines[0]['BaseEntry'] ?? $headerLines[0]['base_entry'] ?? null;
                if ($firstBe) {
                    $so = SalesOrder::with('details')->where('docentry', (int) $firstBe)
                        ->orWhere('sap_doc_entry', (int) $firstBe)
                        ->first();
                }
            }

            // Resolve BaseEntry from candidate or SO (resolving integer DocEntry instead of DocNum)
            $candidateBe = $header['BaseEntry'] ?? $header['base_entry'] ?? $header['DocEntry'] ?? $header['doc_entry'] ?? null;
            if (empty($candidateBe) && !empty($headerLines[0])) {
                $candidateBe = $headerLines[0]['BaseEntry'] ?? $headerLines[0]['base_entry'] ?? null;
            }

            $baseEntry = $this->resolveSalesOrderDocEntry($so, $candidateBe);

            if (empty($baseEntry)) {
                $firstLineSoId = $headerLines[0]['sales_order_id'] ?? $headerLines[0]['sales_order_Id'] ?? null;
                $orderIdentifier = $so ? "#{$so->order_no}" : (!empty($header['sales_order_id']) ? "ID #{$header['sales_order_id']}" : (!empty($firstLineSoId) ? "ID #{$firstLineSoId}" : ''));
                throw new \Exception("Sales Order {$orderIdentifier} belum memiliki DocEntry / BaseEntry (belum approved / terintegrasi ke SAP). Tidak bisa Add DO.", 400);
            }

            $soDetails = $so && $so->details ? $so->details->sortBy('id')->values() : collect();
            $referencedSos = collect();
            if ($so) {
                $referencedSos->put($so->id, $so);
            }

            $picklist = null;
            if (!empty($header['picklist_id'])) {
                $picklist = Picklist::find($header['picklist_id']);
            }
            if (!$picklist && $so) {
                $pItem = PicklistItem::where('sales_order_id', $so->id)->orderBy('id', 'desc')->first();
                if ($pItem) {
                    $picklist = Picklist::find($pItem->picklist_id);
                }
            }
            $targetItWhsCode = $this->getItWarehouseCode($picklist);

            $cleanLines = [];
            foreach ($headerLines as $lineIdx => $line) {
                $itemCode = trim((string) ($line['ItemCode'] ?? $line['item_code'] ?? ''));
                $qty = floatval($line['Quantity'] ?? $line['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $lineSoId = $line['sales_order_id'] ?? $line['sales_order_Id'] ?? null;
                $lineSo = null;
                if ($lineSoId) {
                    $lineSo = ($so && (int) $so->id === (int) $lineSoId)
                        ? $so
                        : SalesOrder::with('details')->find($lineSoId);
                    if ($lineSo && !$referencedSos->has($lineSo->id)) {
                        $referencedSos->put($lineSo->id, $lineSo);
                    }
                }
                $currentSo = $lineSo ?: $so;
                $currentSoDetails = $currentSo && $currentSo->details ? $currentSo->details->sortBy('id')->values() : $soDetails;

                $detail = null;
                if (!empty($line['sales_order_detail_id'])) {
                    $detail = $currentSoDetails->firstWhere('id', (int) $line['sales_order_detail_id']);
                }
                if (!$detail && !empty($itemCode)) {
                    $detail = $currentSoDetails->first(function ($d) use ($itemCode) {
                        return strtolower(trim((string)$d->item_code)) === strtolower(trim($itemCode));
                    });
                }
                if (!$detail && isset($line['BaseLine'])) {
                    $detail = $currentSoDetails->firstWhere('baseline', (int) $line['BaseLine']);
                }
                if (!$detail && isset($line['baseline'])) {
                    $detail = $currentSoDetails->firstWhere('baseline', (int) $line['baseline']);
                }
                if (!$detail && isset($currentSoDetails[$lineIdx])) {
                    $detail = $currentSoDetails[$lineIdx];
                }

                // Resolve line BaseLine safely without throwing
                $lineBaseLine = null;
                if (isset($line['BaseLine']) && $line['BaseLine'] !== '' && $line['BaseLine'] !== null) {
                    $lineBaseLine = (int) $line['BaseLine'];
                } elseif (isset($line['baseline']) && $line['baseline'] !== '' && $line['baseline'] !== null) {
                    $lineBaseLine = (int) $line['baseline'];
                } elseif (isset($line['base_line']) && $line['base_line'] !== '' && $line['base_line'] !== null) {
                    $lineBaseLine = (int) $line['base_line'];
                } elseif ($detail && $detail->baseline !== null) {
                    $lineBaseLine = (int) $detail->baseline;
                } elseif ($detail && $detail->line_num !== null) {
                    $lineBaseLine = (int) $detail->line_num;
                } else {
                    $dIndex = $detail ? $currentSoDetails->search(fn($d) => $d->id === $detail->id) : false;
                    $lineBaseLine = ($dIndex !== false) ? (int) $dIndex : (int) $lineIdx;
                }

                if ($detail && $detail->baseline === null) {
                    try {
                        $detail->update(['baseline' => $lineBaseLine, 'line_num' => $lineBaseLine]);
                    } catch (\Throwable $e) {}
                }

                $lineBaseEntry = (isset($line['BaseEntry']) && $line['BaseEntry'] !== '' && $line['BaseEntry'] !== null)
                    ? (int) $line['BaseEntry']
                    : ((isset($line['base_entry']) && $line['base_entry'] !== '' && $line['base_entry'] !== null)
                        ? (int) $line['base_entry']
                        : (int) ($currentSo ? ($this->resolveSalesOrderDocEntry($currentSo) ?: ($currentSo->docentry ?: $currentSo->sap_doc_entry)) : $baseEntry));

                $finalItemCode = $itemCode ?: ($detail?->item_code ?: '');
                $cleanLine = [
                    'BaseEntry' => $lineBaseEntry,
                    'BaseLine'  => $lineBaseLine,
                    'ItemCode'  => $finalItemCode,
                    'Quantity'  => $qty,
                    'WhsCode'   => (string) $targetItWhsCode,
                ];

                $ocrCode  = $line['OcrCode'] ?? $line['ocr_code'] ?? ($detail?->ocr_code ?: null);
                $ocrCode2 = $line['OcrCode2'] ?? $line['ocr_code2'] ?? ($detail?->ocr_code2 ?: null);
                $ocrCode3 = $line['OcrCode3'] ?? $line['ocr_code3'] ?? ($detail?->ocr_code3 ?: null);

                if (!empty($ocrCode)) {
                    $cleanLine['OcrCode'] = (string) $ocrCode;
                }
                if (!empty($ocrCode2)) {
                    $cleanLine['OcrCode2'] = (string) $ocrCode2;
                }
                if (!empty($ocrCode3)) {
                    $cleanLine['OcrCode3'] = (string) $ocrCode3;
                }

                $cleanLines[] = $cleanLine;
            }

            if (empty($cleanLines)) {
                throw new \Exception("Tidak ada baris item valid (quantity > 0) untuk Delivery Order.", 400);
            }

            // Clean Header formatted for SAP B1
            $docDate = !empty($header['DocDate']) ? Carbon::parse($header['DocDate'])->format('Y-m-d\TH:i:s') : now()->format('Y-m-d\TH:i:s');
            $docDueDate = !empty($header['DocDueDate']) ? Carbon::parse($header['DocDueDate'])->format('Y-m-d\TH:i:s') : $docDate;
            $taxDate = !empty($header['TaxDate']) ? Carbon::parse($header['TaxDate'])->format('Y-m-d\TH:i:s') : $docDate;

            $numAtCard = (string) ($header['NumAtCard'] ?? $header['num_at_card'] ?? ($so?->po_number ?: ($so?->order_no ?? '')));
            $comments  = (string) ($header['Comments'] ?? $header['comments'] ?? ($picklist?->comments ?: ($so?->comments ?? '')));
            $noPol     = (string) ($header['license_plate'] ?? $header['NoPol'] ?? $header['nopol'] ?? ($picklist?->license_plate ?: ($so?->nopol ?? '')));
            $kodeEksp  = (string) ($header['KodeEkspedisi'] ?? $header['kode_ekspedisi'] ?? ($picklist?->expedition_id ?: ($picklist?->expedition?->code ?? ($so?->shipping_type === 'internal' ? '01' : ''))));
            $namaEksp  = (string) ($header['NamaEkspedisi'] ?? $header['nama_ekspedisi'] ?? ($picklist?->expedition_name ?? ($so?->shipping_type === 'internal' ? 'Internal' : '')));
            $sopir     = (string) ($header['driver_name'] ?? $header['Sopir'] ?? $header['sopir'] ?? ($picklist?->driver_name ?: ($so?->nama_supir ?? '')));
            $checker   = (string) ($header['checker_name'] ?? $header['NamaChecker'] ?? $header['nama_checker'] ?? ($picklist?->checker_name ?? ''));
            $noseal    = (string) ($header['Noseal'] ?? $header['noseal'] ?? ($header['seal_number'] ?? ($picklist?->seal_number ?? '')));

            $sapDoc = [
                'Series'        => (int) ($header['Series'] ?? $header['series'] ?? 75),
                'DocDate'       => $docDate,
                'DocDueDate'    => $docDueDate,
                'TaxDate'       => $taxDate,
                'NumAtCard'     => $numAtCard,
                'Comments'      => $comments,
                'NoPol'         => $noPol,
                'KodeEkspedisi' => $kodeEksp,
                'NamaEkspedisi' => $namaEksp,
                'Sopir'         => $sopir,
                'NamaChecker'   => $checker,
                'AddonId'       => 2,
                'UserId'        => (string) ($userId ?: ($header['UserId'] ?? $header['user_id'] ?? '1')),
                'Container'     => (string) ($header['Container'] ?? $header['container'] ?? ''),
                'Lines'         => $cleanLines,
            ];

            if (!empty($noseal)) {
                $sapDoc['Noseal'] = $noseal;
            }

            if (!empty($header['CardCode']) || !empty($header['card_code'])) {
                $sapDoc['CardCode'] = (string) ($header['CardCode'] ?? $header['card_code']);
            } elseif (!empty($so?->card_code)) {
                $sapDoc['CardCode'] = (string) $so->card_code;
            }

            $batchPayload[] = $sapDoc;
            $metaDocs[] = [
                'so'             => $so,
                'referenced_sos' => $referencedSos,
                'picklist_id'    => $header['picklist_id'] ?? null,
                'raw'            => $header,
            ];
        }

        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        $outboundPayload = $isBatch ? $batchPayload : ($batchPayload[0] ?? []);

        try {
            $response = Http::timeout(60)->post("{$sapUrl}/api/AddDO", $outboundPayload);
        } catch (\Throwable $e) {
            Log::error("Failed to connect to SAP /api/AddDO: " . $e->getMessage(), ['payload' => $outboundPayload]);
            throw new \Exception("Failed to connect to SAP API for Delivery Order: " . $e->getMessage(), 400);
        }

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            Log::error("SAP /api/AddDO returned HTTP {$status}: {$body}");
            throw new \Exception("Failed to process Delivery Order in SAP (HTTP {$status}): " . substr($body, 0, 250), 400);
        }

        $result = $response->json();
        $lastResult = $result;

        if (isset($result['ErrorCode']) && (int) $result['ErrorCode'] !== 0) {
            $errMsg = $result['Message'] ?? 'Unknown SAP error during Delivery Order.';
            Log::error("SAP /api/AddDO returned ErrorCode {$result['ErrorCode']}: {$errMsg}");
            throw new \Exception("SAP Delivery Order Error [{$result['ErrorCode']}]: {$errMsg}", 400);
        }

        $rawResult = $result['Result'] ?? $result['result'] ?? null;
        $msg = $result['Message'] ?? $result['message'] ?? '';

        $processedDocs = [];
        $latestDocNum = null;
        $latestDocEntry = null;
        $firstSo = null;
        $firstPicklist = null;

        foreach ($batchPayload as $index => $sapDoc) {
            $docEntry = null;
            $docNum = null;

            if (is_array($rawResult)) {
                if (isset($rawResult[$index]) && is_array($rawResult[$index])) {
                    $docEntry = $rawResult[$index]['DocEntry'] ?? $rawResult[$index]['doc_entry'] ?? null;
                    $docNum   = $rawResult[$index]['DocNum'] ?? $rawResult[$index]['doc_num'] ?? null;
                } elseif (isset($rawResult['DocEntry']) || isset($rawResult['doc_entry'])) {
                    $docEntry = $rawResult['DocEntry'] ?? $rawResult['doc_entry'] ?? null;
                    $docNum   = $rawResult['DocNum'] ?? $rawResult['doc_num'] ?? null;
                }
            } elseif (is_numeric($rawResult) || is_string($rawResult)) {
                $docEntry = (string) $rawResult;
            }

            if (!empty($msg)) {
                if (!$docNum && preg_match_all('/DocNum(?:s)?:\s*([A-Za-z0-9_-]+)/i', $msg, $mNums)) {
                    $docNum = $mNums[1][$index] ?? $mNums[1][0] ?? null;
                }
                if (!$docEntry && preg_match_all('/DocEntr(?:y|ies):\s*([0-9]+)/i', $msg, $mEntries)) {
                    $docEntry = $mEntries[1][$index] ?? $mEntries[1][0] ?? null;
                }
            }

            $docNum   = $docNum ? (string) $docNum : ($docEntry ? (string) $docEntry : 'PROCESSED');
            $docEntry = $docEntry ? (string) $docEntry : $docNum;

            $latestDocNum = $docNum;
            $latestDocEntry = $docEntry;

            $meta = $metaDocs[$index] ?? [];
            $so = $meta['so'] ?? null;
            $referencedSos = $meta['referenced_sos'] ?? collect();
            if ($so && !$referencedSos->has($so->id)) {
                $referencedSos->put($so->id, $so);
            }

            // Fallback finding SO if not yet found
            if ($referencedSos->isEmpty() && !empty($meta['raw']['sales_order_id'])) {
                $foundSo = SalesOrder::find($meta['raw']['sales_order_id']);
                if ($foundSo) {
                    $referencedSos->put($foundSo->id, $foundSo);
                }
            }
            if ($referencedSos->isEmpty() && !empty($sapDoc['Lines'][0]['BaseEntry'])) {
                $bEntry = $sapDoc['Lines'][0]['BaseEntry'];
                $foundSo = SalesOrder::where('sap_doc_entry', is_numeric($bEntry) ? (int)$bEntry : 0)
                    ->orWhere('docentry', is_numeric($bEntry) ? (int)$bEntry : 0)
                    ->first();
                if ($foundSo) {
                    $referencedSos->put($foundSo->id, $foundSo);
                }
            }

            $targetPicklistId = $meta['picklist_id']
                ?? ($meta['raw']['picklist_id'] ?? null)
                ?? ($meta['raw']['picklistId'] ?? null)
                ?? ($payload['picklist_id'] ?? null)
                ?? ($payload['picklistId'] ?? null)
                ?? (isset($payload['id']) && is_numeric($payload['id']) ? (int) $payload['id'] : null);

            $picklist = $targetPicklistId ? Picklist::find($targetPicklistId) : null;

            foreach ($referencedSos as $rSo) {
                if (!$firstSo) {
                    $firstSo = $rSo;
                }
                $rSo->update([
                    'delivery_order_no' => $docNum,
                    'sap_do_doc_entry'  => (string) $docEntry,
                    'sap_do_doc_num'    => (string) $docNum,
                    'sap_do_status'     => 'SUCCESS',
                    'sap_last_doc_type' => 'DO',
                    'sap_last_doc_num'  => (string) $docNum,
                    'delivery_date'     => now(),
                ]);

                if (!$picklist) {
                    $pItem = PicklistItem::where('sales_order_id', $rSo->id)->orderBy('id', 'desc')->first();
                    if ($pItem) {
                        $picklist = Picklist::find($pItem->picklist_id);
                    }
                }
            }

            if ($picklist) {
                if (!$firstPicklist) {
                    $firstPicklist = $picklist;
                }
                $picklistUpdate = [
                    'status'            => Picklist::STATUS_COMPLETED,
                    'delivery_order_no' => $docNum,
                    'do_doc_entry'      => (string) $docEntry,
                    'do_doc_num'        => (string) $docNum,
                    'do_status'         => 'SUCCESS',
                    'updated_by'        => $userId,
                ];

                if (!empty($noPol)) {
                    $picklistUpdate['license_plate'] = $noPol;
                }
                if (!empty($sopir)) {
                    $picklistUpdate['driver_name'] = $sopir;
                }
                if (!empty($checker)) {
                    $picklistUpdate['checker_name'] = $checker;
                }
                if (!empty($comments)) {
                    $picklistUpdate['comments'] = $comments;
                }
                if (!empty($noseal)) {
                    $picklistUpdate['seal_number'] = $noseal;
                }

                $picklist->update($picklistUpdate);

                // Merge payload, meta raw, and header to capture signatures in any location
                $sigPayload = array_merge(
                    is_array($payload) ? $payload : [],
                    $meta['raw'] ?? [],
                    $header ?? []
                );

                try {
                    $this->savePicklistSignatures($picklist, $sigPayload, $userId);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Failed to save signatures for Picklist #{$picklist->id}: " . $e->getMessage());
                }

                $activeChecker = !empty($header['checked_by_checker'])
                    ? trim((string) $header['checked_by_checker'])
                    : (!empty($header['checker_name'])
                        ? trim((string) $header['checker_name'])
                        : ($picklist->checker_name ?: $userName));

                $activeCheckedAt = !empty($header['checked_at'])
                    ? \Carbon\Carbon::parse($header['checked_at'])
                    : now();

                foreach ($referencedSos as $rSo) {
                    $orderItems = PicklistItem::where('picklist_id', $picklist->id)
                        ->where('sales_order_id', $rSo->id)
                        ->get();

                    foreach ($orderItems as $oItem) {
                        $itemUpdate = [
                            'delivery_order_no' => $docNum,
                        ];

                        $matchingLine = null;
                        if (!empty($headerLines) && is_array($headerLines)) {
                            foreach ($headerLines as $cL) {
                                $cItemCode = $cL['ItemCode'] ?? $cL['item_code'] ?? null;
                                $cSoId = $cL['sales_order_id'] ?? $cL['sales_order_Id'] ?? null;
                                if ($cItemCode && strtolower(trim((string)$cItemCode)) === strtolower(trim((string)$oItem->item_code))) {
                                    if (empty($cSoId) || (int) $cSoId === (int) $rSo->id) {
                                        $matchingLine = $cL;
                                        break;
                                    }
                                }
                            }
                        }

                        if ($matchingLine) {
                            $itemUpdate['is_checked'] = isset($matchingLine['is_checked'])
                                ? (bool) $matchingLine['is_checked']
                                : (isset($matchingLine['isChecked']) ? (bool) $matchingLine['isChecked'] : true);

                            if (isset($matchingLine['checked_qty'])) {
                                $itemUpdate['checked_qty'] = (float) $matchingLine['checked_qty'];
                            } elseif (isset($matchingLine['quantity'])) {
                                $itemUpdate['checked_qty'] = (float) $matchingLine['quantity'];
                            } elseif (isset($matchingLine['Quantity'])) {
                                $itemUpdate['checked_qty'] = (float) $matchingLine['Quantity'];
                            }

                            if (!empty($matchingLine['checked_by_checker'])) {
                                $itemUpdate['checked_by_checker'] = trim((string) $matchingLine['checked_by_checker']);
                            } else {
                                $itemUpdate['checked_by_checker'] = $activeChecker;
                            }

                            if (!empty($matchingLine['checked_at'])) {
                                $itemUpdate['checked_at'] = \Carbon\Carbon::parse($matchingLine['checked_at']);
                            } else {
                                $itemUpdate['checked_at'] = $activeCheckedAt;
                            }
                        } else {
                            $headerChecked = isset($header['is_checked'])
                                ? (bool) $header['is_checked']
                                : (isset($header['isChecked']) ? (bool) $header['isChecked'] : true);
                            $itemUpdate['is_checked'] = $headerChecked;
                            if ($headerChecked) {
                                $itemUpdate['checked_qty'] = (float) ($oItem->checked_qty ?: $oItem->pick_qty);
                                $itemUpdate['checked_by_checker'] = $activeChecker;
                                $itemUpdate['checked_at'] = $activeCheckedAt;
                            }
                        }

                        $oItem->update($itemUpdate);
                    }
                }
            }

            $primarySo = $so ?: $referencedSos->first();

            $processedDocs[] = [
                'doc_entry'       => (string) $docEntry,
                'doc_num'         => (string) $docNum,
                'sales_order_id'  => $primarySo?->id,
                'order_no'        => $primarySo?->order_no,
                'picklist_id'     => $picklist?->id,
                'picklist_no'     => $picklist?->picklist_no,
            ];
        }

        $freshFirstPicklist = $firstPicklist?->fresh(['items', 'signatures']);

        return [
            'delivery_order_no' => (string) $latestDocNum,
            'doc_entry'         => (string) $latestDocEntry,
            'doc_num'           => (string) $latestDocNum,
            'is_checked'        => $freshFirstPicklist ? (bool) $freshFirstPicklist->is_checked : true,
            'sales_order_id'    => $firstSo?->id,
            'picklist_id'       => $firstPicklist?->id,
            'documents'         => $processedDocs,
            'signatures'        => $freshFirstPicklist?->signatures ?? [],
            'response'          => $lastResult,
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

    /**
     * Save inspection / handover signatures for a picklist (multi-checker & driver).
     *
     * @param Picklist $picklist
     * @param array $payload
     * @param int|null $userId
     * @return array
     */
    public function savePicklistSignatures(Picklist $picklist, array $payload, ?int $userId = null): array
    {
        $rawSignatures = [];

        // 0. Unwrap nested payload if wrapped in 'data'
        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = array_merge($payload['data'], $payload);
        }

        // 1. Check if 'signatures' (or aliases: tanda_tangan, signature_list, signs) is provided
        $sigArray = $payload['signatures'] ?? $payload['tanda_tangan'] ?? $payload['signs'] ?? $payload['signature_list'] ?? null;

        if (!empty($sigArray)) {
            if (is_array($sigArray)) {
                $isAssoc = array_keys($sigArray) !== range(0, count($sigArray) - 1);
                if ($isAssoc) {
                    // Object like {"checker": "...", "driver": "..."} or {"checker_signature": "..."}
                    foreach ($sigArray as $key => $val) {
                        $sKey = strtolower(trim((string) $key));
                        $sType = (str_contains($sKey, 'driver') || str_contains($sKey, 'sopir') || str_contains($sKey, 'supir'))
                            ? PicklistSignature::TYPE_DRIVER
                            : PicklistSignature::TYPE_CHECKER;

                        $rawSignatures[] = [
                            'signer_type'       => $sType,
                            'signer_role_title' => $sType === PicklistSignature::TYPE_DRIVER ? 'Supir' : 'Checker',
                            'signer_name'       => $payload["{$sType}_name"] ?? $picklist->{"{$sType}_name"} ?? ucfirst($sType),
                            'signature'         => is_array($val) ? ($val['signature'] ?? $val['signature_data'] ?? $val['image'] ?? $val['data'] ?? null) : $val,
                            'notes'             => is_array($val) ? ($val['notes'] ?? null) : null,
                        ];
                    }
                } else {
                    // Numeric list of items
                    foreach ($sigArray as $item) {
                        if (is_string($item) || $item instanceof \Illuminate\Http\UploadedFile) {
                            $rawSignatures[] = [
                                'signer_type'       => PicklistSignature::TYPE_CHECKER,
                                'signer_role_title' => 'Checker',
                                'signer_name'       => $picklist->checker_name ?? 'Checker',
                                'signature'         => $item,
                            ];
                        } elseif (is_array($item)) {
                            $rawSignatures[] = $item;
                        }
                    }
                }
            }
        }

        // 2. Extract direct checker keys at root
        $checkerSig = $payload['checker_signature'] ?? $payload['checkerSignature'] ?? $payload['signature_checker']
            ?? $payload['signatureChecker'] ?? $payload['ttd_checker'] ?? $payload['ttdChecker'] ?? $payload['checker_sign']
            ?? $payload['checkerSign'] ?? $payload['sign_checker'] ?? $payload['tanda_tangan_checker']
            ?? $payload['checker_image'] ?? null;

        if (!empty($checkerSig)) {
            $rawSignatures[] = [
                'signer_type'       => PicklistSignature::TYPE_CHECKER,
                'signer_role_title' => $payload['checker_role_title'] ?? 'Checker',
                'signer_name'       => $payload['checker_name'] ?? $payload['NamaChecker'] ?? $payload['nama_checker'] ?? $picklist->checker_name ?? 'Checker',
                'signature'         => $checkerSig,
                'notes'             => $payload['checker_notes'] ?? null,
            ];
        }

        // 3. Extract direct driver keys at root
        $driverSig = $payload['driver_signature'] ?? $payload['driverSignature'] ?? $payload['signature_driver']
            ?? $payload['signatureDriver'] ?? $payload['signature_sopir'] ?? $payload['signatureSopir']
            ?? $payload['ttd_driver'] ?? $payload['ttdDriver'] ?? $payload['ttd_sopir'] ?? $payload['ttdSopir']
            ?? $payload['driver_sign'] ?? $payload['driverSign'] ?? $payload['tanda_tangan_driver']
            ?? $payload['tanda_tangan_sopir'] ?? $payload['driver_image'] ?? null;

        if (!empty($driverSig)) {
            $rawSignatures[] = [
                'signer_type'       => PicklistSignature::TYPE_DRIVER,
                'signer_role_title' => $payload['driver_role_title'] ?? 'Supir',
                'signer_name'       => $payload['driver_name'] ?? $payload['Sopir'] ?? $payload['sopir'] ?? $payload['nama_supir'] ?? $picklist->driver_name ?? 'Supir',
                'signature'         => $driverSig,
                'notes'             => $payload['driver_notes'] ?? null,
            ];
        }

        // 4. Extract single generic signature if neither checker nor driver keys were found
        $genericSig = $payload['signature'] ?? $payload['ttd'] ?? $payload['sign'] ?? $payload['tanda_tangan']
            ?? $payload['signature_data'] ?? $payload['signature_file'] ?? $payload['signature_image'] ?? null;

        if (!empty($genericSig) && empty($checkerSig) && empty($driverSig) && empty($sigArray)) {
            $isDriver = !empty($payload['signer_type']) && in_array(strtolower(trim((string)$payload['signer_type'])), ['driver', 'sopir', 'supir']);
            $sType = $isDriver ? PicklistSignature::TYPE_DRIVER : PicklistSignature::TYPE_CHECKER;
            $rawSignatures[] = [
                'signer_type'       => $sType,
                'signer_role_title' => $payload['signer_role_title'] ?? ($isDriver ? 'Supir' : 'Checker'),
                'signer_name'       => $payload['signer_name'] ?? $payload['name'] ?? ($isDriver ? ($payload['Sopir'] ?? $payload['sopir'] ?? $picklist->driver_name) : ($payload['NamaChecker'] ?? $payload['nama_checker'] ?? $picklist->checker_name)) ?? ucfirst($sType),
                'signature'         => $genericSig,
                'notes'             => $payload['notes'] ?? null,
            ];
        }

        if (empty($rawSignatures)) {
            return [];
        }

        $saved = [];
        $order = 1;

        foreach ($rawSignatures as $item) {
            $signatureData = $item['signature']
                ?? $item['signature_data']
                ?? $item['signature_file']
                ?? $item['signature_path']
                ?? $item['signature_url']
                ?? $item['image']
                ?? $item['data']
                ?? $item['file']
                ?? $item['ttd']
                ?? $item['sign']
                ?? $item['base64']
                ?? $item['uri']
                ?? $item['value']
                ?? null;

            if (empty($signatureData)) {
                continue;
            }

            $rawType    = strtolower(trim((string) ($item['signer_type'] ?? $item['type'] ?? $item['role'] ?? PicklistSignature::TYPE_CHECKER)));
            $signerType = in_array($rawType, ['driver', 'sopir', 'supir', 'pengemudi']) ? PicklistSignature::TYPE_DRIVER : (in_array($rawType, ['supervisor', 'spv']) ? PicklistSignature::TYPE_SUPERVISOR : PicklistSignature::TYPE_CHECKER);

            $roleTitle  = !empty($item['signer_role_title'])
                ? trim((string) $item['signer_role_title'])
                : (!empty($item['role_title'])
                    ? trim((string) $item['role_title'])
                    : (!empty($item['title'])
                        ? trim((string) $item['title'])
                        : ($signerType === PicklistSignature::TYPE_DRIVER ? 'Supir' : 'Checker')));

            $signerName = !empty($item['signer_name'])
                ? trim((string) $item['signer_name'])
                : (!empty($item['name'])
                    ? trim((string) $item['name'])
                    : (!empty($item['nama'])
                        ? trim((string) $item['nama'])
                        : ($signerType === PicklistSignature::TYPE_DRIVER
                            ? ($payload['Sopir'] ?? $payload['sopir'] ?? $picklist->driver_name ?? 'Supir')
                            : ($payload['NamaChecker'] ?? $payload['nama_checker'] ?? $picklist->checker_name ?? 'Checker'))));

            $notes    = !empty($item['notes']) ? trim((string) $item['notes']) : (!empty($item['catatan']) ? trim((string) $item['catatan']) : null);
            $signedAt = !empty($item['signed_at']) ? Carbon::parse($item['signed_at']) : now();

            $storedPath = $this->storeSignatureImage($picklist->id, $signerType, $order, $signatureData);

            if ($storedPath) {
                $sig = PicklistSignature::updateOrCreate(
                    [
                        'picklist_id' => $picklist->id,
                        'signer_type' => $signerType,
                        'signer_name' => $signerName,
                    ],
                    [
                        'signer_role_title' => $roleTitle,
                        'signature_path'    => $storedPath,
                        'signed_at'         => $signedAt,
                        'sort_order'        => (int) ($item['sort_order'] ?? $order),
                        'notes'             => $notes,
                        'created_by'        => $userId,
                    ]
                );

                $saved[] = $sig;
                $order++;
            }
        }

        return $saved;
    }

    /**
     * Store signature image (Base64 data URI, raw base64, or UploadedFile) to public disk and S3.
     *
     * @param int $picklistId
     * @param string $signerType
     * @param int $index
     * @param mixed $signatureData
     * @return string|null
     */
    protected function storeSignatureImage(int $picklistId, string $signerType, int $index, mixed $signatureData): ?string
    {
        $dir = 'signatures/picklists';
        $timestamp = time() . '_' . substr(md5(uniqid()), 0, 6);
        $s3Bucket = config('filesystems.disks.s3.bucket');
        $preferS3 = !empty($s3Bucket) && env('SIGNATURE_STORAGE_DISK', 's3') === 's3';

        // Case A: UploadedFile instance
        if ($signatureData instanceof \Illuminate\Http\UploadedFile) {
            $ext = $signatureData->getClientOriginalExtension() ?: 'png';
            $filename = "{$dir}/pkl_{$picklistId}_{$signerType}_{$index}_{$timestamp}.{$ext}";

            try {
                if (!Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir);
                }
                Storage::disk('public')->putFileAs($dir, $signatureData, basename($filename));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to store signature file locally: " . $e->getMessage());
            }

            if ($preferS3) {
                try {
                    Storage::disk('s3')->putFileAs($dir, $signatureData, basename($filename), 'public');
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Failed to store signature #{$picklistId} to S3: " . $e->getMessage());
                }
            }

            return $filename;
        }

        // Case B: String (Data URL or base64)
        if (is_string($signatureData)) {
            $signatureStr = trim($signatureData);
            if (empty($signatureStr)) {
                return null;
            }

            // Check if already a path / URL
            if (str_starts_with($signatureStr, 'signatures/picklists/') || str_starts_with($signatureStr, 'http://') || str_starts_with($signatureStr, 'https://')) {
                return $signatureStr;
            }

            // Normalize URL-encoded Data URIs
            if (str_starts_with($signatureStr, 'data%3A') || str_contains($signatureStr, '%2C')) {
                $signatureStr = urldecode($signatureStr);
            }

            $ext = 'png';
            $binary = false;

            if (preg_match('/^data:image\/([a-zA-Z0-9\+\-\.]+);base64,(.+)$/is', $signatureStr, $matches)) {
                $rawExt = strtolower($matches[1]);
                $ext = match ($rawExt) {
                    'jpeg' => 'jpg',
                    'svg+xml' => 'svg',
                    default => $rawExt,
                };
                $cleanB64 = str_replace([' ', "\r", "\n", "\t"], ['+', '', '', ''], trim($matches[2]));
                $binary = base64_decode($cleanB64);
            } elseif (preg_match('/^data:image\/svg\+xml;utf8,(.+)$/is', $signatureStr, $matches)) {
                $ext = 'svg';
                $binary = urldecode($matches[1]);
            } elseif (str_starts_with($signatureStr, '<svg')) {
                $ext = 'svg';
                $binary = $signatureStr;
            } else {
                $cleanB64 = str_replace([' ', "\r", "\n", "\t"], ['+', '', '', ''], $signatureStr);
                $binary = base64_decode($cleanB64);
            }

            if ($binary === false || empty($binary)) {
                return null;
            }

            $filename = "{$dir}/pkl_{$picklistId}_{$signerType}_{$index}_{$timestamp}.{$ext}";

            // Always store to local public storage first so preview is guaranteed to find it
            try {
                if (!Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir);
                }
                Storage::disk('public')->put($filename, $binary);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to store signature locally: " . $e->getMessage());
            }

            // Sync to S3 if configured
            if ($preferS3) {
                try {
                    Storage::disk('s3')->put($filename, $binary, 'public');
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Failed to store signature #{$picklistId} to S3: " . $e->getMessage());
                }
            }

            return $filename;
        }

        return null;
    }

    /**
     * Resolve valid SAP DocEntry for a given SalesOrder.
     * Prevents using DocNum (e.g. 260910011) as DocEntry which causes SAP error:
     * "Query Header ORDR BaseEntry: 260910011 | Pesan: Sales Order DocEntry 260910011 tidak ditemukan"
     *
     * @param SalesOrder|null $so
     * @param mixed $candidateBaseEntry
     * @return int|null
     */
    public function resolveSalesOrderDocEntry(?SalesOrder $so, mixed $candidateBaseEntry = null): ?int
    {
        if (!$so && empty($candidateBaseEntry)) {
            return null;
        }

        $sapDocNum = $so?->sap_doc_num ? (string) $so->sap_doc_num : null;
        $existingDocEntry = $so?->docentry ?: $so?->sap_doc_entry;

        // Check if candidateBaseEntry or existingDocEntry is erroneously set to sap_doc_num
        $isDocNum = false;
        if (!empty($sapDocNum)) {
            if ((string) $candidateBaseEntry === $sapDocNum) {
                $isDocNum = true;
            }
            if ((string) $existingDocEntry === $sapDocNum) {
                $isDocNum = true;
            }
        }

        // 1. If we have a valid existingDocEntry that is distinct from DocNum, use it
        if (!empty($existingDocEntry) && !$isDocNum) {
            return (int) $existingDocEntry;
        }

        // 2. If candidateBaseEntry is provided and distinct from DocNum, use it
        if (!empty($candidateBaseEntry) && !$isDocNum) {
            if ($so && empty($so->docentry)) {
                try {
                    $so->update(['docentry' => (int) $candidateBaseEntry, 'sap_doc_entry' => (string) $candidateBaseEntry]);
                } catch (\Throwable $e) {}
            }
            return (int) $candidateBaseEntry;
        }

        // 3. If docentry is missing or equals DocNum, try to lookup real DocEntry from SAP GetDataSO
        if ($so) {
            try {
                $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');
                $cardCode = $so->card_code ?? $so->distributor?->code_customer;

                $response = null;
                if (!empty($cardCode)) {
                    $response = \Illuminate\Support\Facades\Http::timeout(15)->post("{$sapUrl}/api/GetDataSO", [
                        'CustomQuery' => "'{$cardCode}'",
                    ]);
                }

                if (!$response || !$response->successful()) {
                    if (!empty($sapDocNum)) {
                        $response = \Illuminate\Support\Facades\Http::timeout(15)->post("{$sapUrl}/api/GetDataSO", [
                            'CustomQuery' => "'{$sapDocNum}'",
                        ]);
                    }
                }

                if ($response && $response->successful()) {
                    $body = $response->json();
                    $items = $body['Result'] ?? $body['result'] ?? [];
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            $itemDocNum = (string) ($item['sap_doc_num'] ?? $item['DocNum'] ?? $item['doc_num'] ?? '');
                            $itemPoNum  = (string) ($item['num_at_card'] ?? $item['NumAtCard'] ?? '');
                            $itemDocEntry = $item['sap_doc_entry'] ?? $item['DocEntry'] ?? $item['doc_entry'] ?? null;

                            $matches = false;
                            if (!empty($sapDocNum) && $itemDocNum === $sapDocNum) {
                                $matches = true;
                            } elseif (!empty($so->order_no) && ($itemPoNum === (string) $so->order_no || $itemDocNum === (string) $so->order_no)) {
                                $matches = true;
                            } elseif (!empty($so->po_number) && $itemPoNum === (string) $so->po_number) {
                                $matches = true;
                            }

                            if ($matches && !empty($itemDocEntry) && (string) $itemDocEntry !== (string) $sapDocNum) {
                                $realDocEntry = (int) $itemDocEntry;
                                $so->update([
                                    'docentry'      => $realDocEntry,
                                    'sap_doc_entry' => (string) $realDocEntry,
                                ]);
                                return $realDocEntry;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to resolve SAP DocEntry for SO #{$so->order_no}: " . $e->getMessage());
            }

            // Fallback if SAP lookup fails or unavailable
            if (!empty($so->docentry)) {
                return (int) $so->docentry;
            }
            if (!empty($candidateBaseEntry)) {
                return (int) $candidateBaseEntry;
            }
            if (!empty($so->sap_doc_num)) {
                return (int) $so->sap_doc_num;
            }
        }

        return !empty($candidateBaseEntry) ? (int) $candidateBaseEntry : null;
    }

    /**
     * Save draft checklist progress and signatures for multi-checker / multi-warehouse workflow.
     * Keeps picklist in IN_PROGRESS state without sending delivery order to SAP B1.
     *
     * @param int|Picklist $picklistOrId
     * @param array $payload
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function saveDraftProgress(int|Picklist $picklistOrId, array $payload = [], ?int $userId = null, bool $isSubmit = false): array
    {
        $picklist = is_numeric($picklistOrId)
            ? Picklist::with(['items.salesOrder', 'items.labelPhotos', 'signatures'])->find($picklistOrId)
            : $picklistOrId;

        if (!$picklist) {
            throw new \Exception("Picklist not found.", 404);
        }

        $statusLower = strtolower(trim((string)$picklist->status));
        if (in_array($statusLower, [Picklist::STATUS_CANCELED, 'cancelled', 'canceled'], true)) {
            throw new \Exception("Cannot save draft progress for a CANCELLED picklist.", 400);
        }

        // If this is a submit action, ensure weigh-in has been performed
        if ($isSubmit) {
            if ($statusLower === Picklist::STATUS_OPEN && (empty($picklist->weigh_in_weight) || (float) $picklist->weigh_in_weight <= 0)) {
                throw new \Exception("Truk belum melakukan timbang masuk (weigh_in). Harap lakukan proses timbang masuk terlebih dahulu.", 422);
            }
        }

        // 1. Merge checker names (e.g. ROCHIM + NANANG -> ROCHIM/NANANG) or set edited value
        $incomingChecker = $payload['checker_name'] ?? $payload['NamaChecker'] ?? $payload['nama_checker'] ?? null;
        if (!empty($incomingChecker)) {
            $overwriteChecker = (bool) ($payload['overwrite_checker'] ?? $payload['replace_checker'] ?? false);
            if ($overwriteChecker || str_contains((string) $incomingChecker, '/')) {
                $picklist->checker_name = trim((string) $incomingChecker);
            } else {
                $existingCheckers = array_filter(array_map('trim', explode('/', (string) $picklist->checker_name)));
                $newCheckers = array_filter(array_map('trim', explode('/', (string) $incomingChecker)));
                $merged = array_unique(array_merge($existingCheckers, $newCheckers));
                $picklist->checker_name = implode('/', $merged);
            }
        }

        $incomingDriver = $payload['driver_name'] ?? $payload['Sopir'] ?? $payload['sopir'] ?? null;
        if (!empty($incomingDriver)) {
            $picklist->driver_name = trim((string) $incomingDriver);
        }
        $incomingNoPol = $payload['license_plate'] ?? $payload['NoPol'] ?? $payload['nopol'] ?? null;
        if (!empty($incomingNoPol)) {
            $picklist->license_plate = trim((string) $incomingNoPol);
        }
        $incomingComments = $payload['Comments'] ?? $payload['comments'] ?? null;
        if ($incomingComments !== null) {
            $picklist->comments = $incomingComments;
        }
        $incomingNoseal = $payload['Noseal'] ?? $payload['noseal'] ?? ($payload['seal_number'] ?? null);
        if ($incomingNoseal !== null) {
            $picklist->seal_number = $incomingNoseal;
        }

        // Advance status from OPEN or WEIGH_IN to IN_PROGRESS
        if (in_array($statusLower, [Picklist::STATUS_OPEN, Picklist::STATUS_WEIGH_IN, 'open', 'weigh_in'], true)) {
            $picklist->status = Picklist::STATUS_IN_PROGRESS;
        }
        $picklist->updated_by = $userId;
        $picklist->save();

        // 2. Save signatures if provided (uses updateOrCreate to accumulate multiple checkers)
        $savedSignatures = $this->savePicklistSignatures($picklist, $payload, $userId);

        // 3. Process item checklists
        $lines = $payload['items'] ?? $payload['Lines'] ?? $payload['lines'] ?? [];
        $activeCheckerName = $incomingChecker ?: ($picklist->checker_name ?: 'Checker');
        $updatedItemIds = [];

        if (is_array($lines) && !empty($lines)) {
            $picklistItems = $picklist->items;

            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }

                $targetItem = null;
                // 1. Match by picklist_item id
                if (!empty($line['picklist_item_id'])) {
                    $targetItem = $picklistItems->firstWhere('id', (int) $line['picklist_item_id']);
                }
                if (!$targetItem && !empty($line['id'])) {
                    $targetItem = $picklistItems->firstWhere('id', (int) $line['id']);
                }
                // 2. Match by sales_order_detail_id or line['id'] as sales_order_detail_id
                if (!$targetItem && !empty($line['sales_order_detail_id'])) {
                    $targetItem = $picklistItems->firstWhere('sales_order_detail_id', (int) $line['sales_order_detail_id']);
                }
                if (!$targetItem && !empty($line['id'])) {
                    $targetItem = $picklistItems->firstWhere('sales_order_detail_id', (int) $line['id']);
                }
                // 3. Match by BaseEntry and BaseLine (standard SAP DO Lines)
                if (!$targetItem && (isset($line['BaseEntry']) || isset($line['base_entry']))) {
                    $be = (int) ($line['BaseEntry'] ?? $line['base_entry']);
                    $bl = isset($line['BaseLine']) ? (int) $line['BaseLine'] : (isset($line['baseline']) ? (int) $line['baseline'] : null);

                    $targetItem = $picklistItems->first(function ($pItem) use ($be, $bl) {
                        $so = $pItem->salesOrder;
                        $soMatches = $so && ((int)$so->docentry === $be || (int)$so->sap_doc_entry === $be || (int)$so->sap_doc_num === $be || (int)$so->id === $be);
                        if (!$soMatches) {
                            return false;
                        }
                        if ($bl === null) {
                            return true;
                        }
                        $soDetail = $pItem->salesOrderDetail;
                        return $soDetail && ((int)$soDetail->baseline === $bl || (int)$soDetail->line_num === $bl);
                    });
                }
                // 4. Match by sales_order_id and BaseLine
                if (!$targetItem && !empty($line['sales_order_id']) && (isset($line['BaseLine']) || isset($line['baseline']))) {
                    $soId = (int) $line['sales_order_id'];
                    $bl = (int) ($line['BaseLine'] ?? $line['baseline']);
                    $targetItem = $picklistItems->first(function ($pItem) use ($soId, $bl) {
                        if ((int) $pItem->sales_order_id !== $soId) {
                            return false;
                        }
                        $soDetail = $pItem->salesOrderDetail;
                        return $soDetail && ((int)$soDetail->baseline === $bl || (int)$soDetail->line_num === $bl);
                    });
                }
                // 5. Match by sales_order_id + item_code
                if (!$targetItem && !empty($line['sales_order_id']) && !empty($line['ItemCode'] ?? $line['item_code'])) {
                    $itemCode = trim((string) ($line['ItemCode'] ?? $line['item_code']));
                    $soId = (int) $line['sales_order_id'];
                    $targetItem = $picklistItems->first(function ($pItem) use ($soId, $itemCode) {
                        return (int) $pItem->sales_order_id === $soId && strtolower(trim((string) $pItem->item_code)) === strtolower($itemCode);
                    });
                }
                // 6. Match by item_code
                if (!$targetItem && !empty($line['ItemCode'] ?? $line['item_code'])) {
                    $itemCode = trim((string) ($line['ItemCode'] ?? $line['item_code']));
                    $targetItem = $picklistItems->first(function ($pItem) use ($itemCode) {
                        return strtolower(trim((string) $pItem->item_code)) === strtolower($itemCode);
                    });
                }

                if ($targetItem) {
                    $checkedQty = null;
                    if (isset($line['checked_qty'])) {
                        $checkedQty = (float) $line['checked_qty'];
                    } elseif (isset($line['checkedQty'])) {
                        $checkedQty = (float) $line['checkedQty'];
                    } elseif (isset($line['Quantity'])) {
                        $checkedQty = (float) $line['Quantity'];
                    } elseif (isset($line['quantity'])) {
                        $checkedQty = (float) $line['quantity'];
                    } else {
                        $checkedQty = (float) $targetItem->pick_qty;
                    }

                    $isChecked = null;
                    if (isset($line['is_checked'])) {
                        $isChecked = filter_var($line['is_checked'], FILTER_VALIDATE_BOOLEAN);
                    } elseif (isset($line['isChecked'])) {
                        $isChecked = filter_var($line['isChecked'], FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $isChecked = $checkedQty > 0;
                    }

                    $checkerForThisLine = !empty($line['checked_by_checker'])
                        ? trim((string) $line['checked_by_checker'])
                        : $activeCheckerName;

                    $itemUpdateData = [
                        'checked_qty'        => $checkedQty,
                        'is_checked'         => $isChecked,
                        'checked_by_checker' => $checkerForThisLine,
                        'checked_at'         => now(),
                    ];

                    // KPI fields
                    $startTime = $line['start_time'] ?? $line['startTime'] ?? $line['jam_mulai'] ?? null;
                    if (!empty($startTime)) {
                        $itemUpdateData['start_time'] = Carbon::parse($startTime);
                    }
                    $endTime = $line['end_time'] ?? $line['endTime'] ?? $line['jam_selesai'] ?? null;
                    if (!empty($endTime)) {
                        $itemUpdateData['end_time'] = Carbon::parse($endTime);
                    }
                    $prodDate = $line['production_date'] ?? $line['productionDate'] ?? $line['tanggal_produksi'] ?? $line['tgl_produksi'] ?? null;
                    if (!empty($prodDate)) {
                        $itemUpdateData['production_date'] = Carbon::parse($prodDate)->format('Y-m-d');
                    }

                    $targetItem->update($itemUpdateData);

                    // Save label photos for this line if provided
                    $linePhotos = $line['label_photos'] ?? $line['labelPhotos'] ?? $line['photos'] ?? $line['gambar_label'] ?? $line['foto_label'] ?? null;
                    if (!empty($linePhotos)) {
                        $this->savePicklistItemLabelPhotos($picklist->id, $targetItem->id, $linePhotos, $userId);
                    }

                    $updatedItemIds[] = $targetItem->id;
                }
            }
        }

        // Support root-level photos indexed by picklist_item_id
        $rootPhotos = $payload['label_photos'] ?? $payload['labelPhotos'] ?? $payload['item_photos'] ?? null;
        if (is_array($rootPhotos) && !empty($rootPhotos)) {
            foreach ($rootPhotos as $rItem) {
                if (is_array($rItem) && !empty($rItem['picklist_item_id'])) {
                    $pPhotos = $rItem['photos'] ?? $rItem['images'] ?? $rItem['photo'] ?? $rItem['image'] ?? null;
                    if (!empty($pPhotos)) {
                        $this->savePicklistItemLabelPhotos($picklist->id, (int)$rItem['picklist_item_id'], $pPhotos, $userId);
                    }
                }
            }
        }

        $freshPicklist = $picklist->fresh(['items.salesOrder', 'items.labelPhotos', 'creator', 'updater', 'signatures']);
        $totalItems = $freshPicklist->items->count();
        $checkedItemsCount = $freshPicklist->items->where('is_checked', true)->count();
        $isFullyChecked = $totalItems > 0 && $checkedItemsCount >= $totalItems;

        $msg = 'Draft picklist progress successfully saved.';

        if ($isSubmit) {
            if ($isFullyChecked) {
                $freshPicklist->status = Picklist::STATUS_WEIGH_OUT;
                $freshPicklist->save();
                $msg = 'Pemeriksaan selesai. Seluruh item telah diverifikasi checker. Status picklist berubah menjadi weigh_out.';
            } else {
                $freshPicklist->status = Picklist::STATUS_IN_PROGRESS;
                $freshPicklist->save();
                $msg = 'Pemeriksaan sebagian item berhasil disimpan. Menunggu checker lainnya untuk menyelesaikan sisa item.';
            }
        }

        return [
            'picklist_id'       => $freshPicklist->id,
            'picklist_no'       => $freshPicklist->picklist_no,
            'status'            => $freshPicklist->status,
            'checker_name'      => $freshPicklist->checker_name,
            'driver_name'       => $freshPicklist->driver_name,
            'total_items'       => $totalItems,
            'checked_items'     => $checkedItemsCount,
            'is_fully_checked'  => $isFullyChecked,
            'updated_items'     => count($updatedItemIds),
            'signatures'        => $savedSignatures,
            'message'           => $msg,
            'picklist'          => $freshPicklist,
        ];
    }

    /**
     * Submit Tare Weighing (Timbang Masuk / Kosong) for a picklist.
     * Transitions status from open to weigh_in.
     *
     * @param int $picklistId
     * @param array $payload
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function submitWeighIn(int $picklistId, array $payload = [], ?int $userId = null): array
    {
        $picklist = Picklist::with(['items'])->find($picklistId);
        if (!$picklist) {
            throw new \Exception("Picklist with ID #{$picklistId} not found.", 404);
        }

        $statusLower = strtolower(trim((string)$picklist->status));
        if (in_array($statusLower, [Picklist::STATUS_CANCELED, 'cancelled', 'canceled'], true)) {
            throw new \Exception("Cannot process weigh-in for a CANCELLED picklist.", 400);
        }

        if (in_array($statusLower, [Picklist::STATUS_IN_PROGRESS, Picklist::STATUS_WEIGH_OUT, Picklist::STATUS_COMPLETE, 'complete', 'completed'], true)) {
            throw new \Exception("Picklist is already {$picklist->status}. Cannot re-execute weigh-in.", 400);
        }

        $weight = (float) ($payload['weight'] ?? $payload['weigh_in_weight'] ?? 0);
        if ($weight <= 0) {
            throw new \Exception("Berat timbang masuk (weight) harus lebih besar dari 0 KG.", 422);
        }

        $user = $userId ? User::find($userId) : null;
        $operator = !empty($payload['operator_name'])
            ? trim((string) $payload['operator_name'])
            : (!empty($payload['weigh_in_by']) ? trim((string) $payload['weigh_in_by']) : ($user?->name ?? 'Petugas Timbangan Masuk'));

        $weighedAt = !empty($payload['weighed_at'])
            ? Carbon::parse($payload['weighed_at'])
            : (!empty($payload['weigh_in_at']) ? Carbon::parse($payload['weigh_in_at']) : now());

        $ticketNo = !empty($payload['ticket_no'])
            ? trim((string) $payload['ticket_no'])
            : (!empty($payload['weigh_in_ticket_no']) ? trim((string) $payload['weigh_in_ticket_no']) : null);

        $notes = !empty($payload['notes'])
            ? trim((string) $payload['notes'])
            : (!empty($payload['weigh_in_notes']) ? trim((string) $payload['weigh_in_notes']) : null);

        $updateData = [
            'status'             => Picklist::STATUS_WEIGH_IN,
            'weigh_in_weight'    => $weight,
            'weigh_in_ticket_no' => $ticketNo,
            'weigh_in_by'        => $operator,
            'weigh_in_at'        => $weighedAt,
            'weigh_in_notes'     => $notes,
            'updated_by'         => $userId,
        ];

        if (!empty($payload['license_plate'])) {
            $updateData['license_plate'] = trim((string) $payload['license_plate']);
        }
        if (!empty($payload['driver_name'])) {
            $updateData['driver_name'] = trim((string) $payload['driver_name']);
        }

        $picklist->update($updateData);

        return [
            'id'                 => $picklist->id,
            'picklist_no'        => $picklist->picklist_no,
            'status'             => $picklist->status,
            'weigh_in_weight'    => (float) $picklist->weigh_in_weight,
            'weigh_in_ticket_no' => $picklist->weigh_in_ticket_no,
            'weigh_in_by'        => $picklist->weigh_in_by,
            'weigh_in_at'        => $picklist->weigh_in_at?->toDateTimeString(),
            'weigh_in_notes'     => $picklist->weigh_in_notes,
            'license_plate'      => $picklist->license_plate,
            'driver_name'        => $picklist->driver_name,
            'picklist'           => $picklist->fresh(['items']),
        ];
    }

    /**
     * Submit Gross Weighing (Timbang Keluar / Isi) for a picklist.
     * Calculates net weight, validates overload against vehicle limit,
     * and triggers SAP AddDO if weight is valid.
     *
     * @param int $picklistId
     * @param array $payload
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function submitWeighOut(int $picklistId, array $payload = [], ?int $userId = null): array
    {
        $picklist = Picklist::with(['items.salesOrder', 'expedition'])->find($picklistId);
        if (!$picklist) {
            throw new \Exception("Picklist with ID #{$picklistId} not found.", 404);
        }

        $statusLower = strtolower(trim((string)$picklist->status));
        if (in_array($statusLower, [Picklist::STATUS_CANCELED, 'cancelled', 'canceled'], true)) {
            throw new \Exception("Cannot process weigh-out for a CANCELLED picklist.", 400);
        }

        if ($statusLower === Picklist::STATUS_OPEN) {
            throw new \Exception("Truk belum melakukan timbang masuk (weigh_in) dan pemeriksaan checker.", 400);
        }

        if ($statusLower === Picklist::STATUS_WEIGH_IN || $statusLower === Picklist::STATUS_IN_PROGRESS) {
            throw new \Exception("Pemeriksaan oleh checker gudang belum selesai. Status saat ini: {$picklist->status}.", 400);
        }

        if (empty($picklist->weigh_in_weight) || (float) $picklist->weigh_in_weight <= 0) {
            throw new \Exception("Data timbang masuk (weigh_in_weight) belum tercatat untuk picklist ini.", 400);
        }

        $grossWeight = (float) ($payload['weight'] ?? $payload['weigh_out_weight'] ?? 0);
        if ($grossWeight <= 0) {
            throw new \Exception("Berat timbang keluar (weight) harus lebih besar dari 0 KG.", 422);
        }

        $tareWeight = (float) $picklist->weigh_in_weight;
        $netWeight = round($grossWeight - $tareWeight, 4);

        if ($netWeight <= 0) {
            throw new \Exception("Berat timbang keluar ({$grossWeight} KG) harus lebih besar dari berat timbang masuk ({$tareWeight} KG).", 422);
        }

        $user = $userId ? User::find($userId) : null;
        $operator = !empty($payload['operator_name'])
            ? trim((string) $payload['operator_name'])
            : (!empty($payload['weigh_out_by']) ? trim((string) $payload['weigh_out_by']) : ($user?->name ?? 'Petugas Timbangan Keluar'));

        $weighedAt = !empty($payload['weighed_at'])
            ? Carbon::parse($payload['weighed_at'])
            : (!empty($payload['weigh_out_at']) ? Carbon::parse($payload['weigh_out_at']) : now());

        $ticketNo = !empty($payload['ticket_no'])
            ? trim((string) $payload['ticket_no'])
            : (!empty($payload['weigh_out_ticket_no']) ? trim((string) $payload['weigh_out_ticket_no']) : null);

        $notes = !empty($payload['notes'])
            ? trim((string) $payload['notes'])
            : (!empty($payload['weigh_out_notes']) ? trim((string) $payload['weigh_out_notes']) : null);

        $manifestWeight = (float) $picklist->total_weight;
        $weightLimit = (float) $picklist->total_weight_limit;

        // Check overload: if weight limit exists and net weight exceeds limit
        $isOverload = false;
        $overloadDiff = 0.0;

        if ($weightLimit > 0 && $netWeight > $weightLimit) {
            $isOverload = true;
            $overloadDiff = round($netWeight - $weightLimit, 2);
        }

        $checkStatus = $isOverload ? 'overload' : 'sesuai';

        $picklist->update([
            'weigh_out_weight'     => $grossWeight,
            'weigh_out_net_weight' => $netWeight,
            'weigh_out_ticket_no'  => $ticketNo,
            'weigh_out_by'         => $operator,
            'weigh_out_at'         => $weighedAt,
            'weigh_out_notes'      => $notes,
            'weight_check_status'  => $checkStatus,
            'updated_by'           => $userId,
        ]);

        $weightSummary = [
            'gross_weight'        => $grossWeight,
            'tare_weight'         => $tareWeight,
            'net_weight'          => $netWeight,
            'manifest_weight'     => $manifestWeight,
            'weight_difference'   => round($netWeight - $manifestWeight, 2),
            'weight_limit'        => $weightLimit,
            'weight_check_status' => $checkStatus,
        ];

        if ($isOverload) {
            return [
                'success'         => false,
                'is_overload'     => true,
                'message'         => "Peringatan: Terjadi kelebihan muatan (Overload) sebesar " . number_format($overloadDiff, 2) . " KG melebihi kapasitas armada. Delivery Order belum dapat dibuat.",
                'picklist_id'     => $picklist->id,
                'picklist_no'     => $picklist->picklist_no,
                'status'          => $picklist->status,
                'weight_summary'  => $weightSummary,
                'delivery_order'  => null,
                'action_required' => 'Harap kurangi muatan di gudang atau lakukan penyesuaian muatan dengan Checker terlebih dahulu.',
                'picklist'        => $picklist->fresh(['items']),
            ];
        }

        // Muatan sesuai -> Trigger AddDO to SAP B1
        $doResult = $this->addDeliveryOrder($picklistId, array_merge($payload, ['force_sap_do' => true]), $userId);

        $freshPicklist = $picklist->fresh(['items.salesOrder', 'signatures']);

        return [
            'success'             => true,
            'is_overload'         => false,
            'message'             => "Timbang keluar berhasil dan muatan sesuai. Delivery Order berhasil dibuat di SAP B1.",
            'picklist_id'         => $freshPicklist->id,
            'picklist_no'         => $freshPicklist->picklist_no,
            'status'              => $freshPicklist->status,
            'weight_summary'      => $weightSummary,
            'delivery_order'      => [
                'do_doc_num'        => $freshPicklist->do_doc_num,
                'do_doc_entry'      => $freshPicklist->do_doc_entry,
                'delivery_order_no' => $freshPicklist->delivery_order_no,
                'do_status'         => $freshPicklist->do_status,
            ],
            'weigh_out_ticket_no' => $freshPicklist->weigh_out_ticket_no,
            'weigh_out_by'        => $freshPicklist->weigh_out_by,
            'weigh_out_at'        => $freshPicklist->weigh_out_at?->toDateTimeString(),
            'sap_results'         => $doResult['results'] ?? [],
            'picklist'            => $freshPicklist,
        ];
    }

    /**
     * Save label photos for a specific picklist item.
     *
     * @param int $picklistId
     * @param int $itemId
     * @param mixed $photos
     * @param int|null $userId
     * @return array
     */
    public function savePicklistItemLabelPhotos(int $picklistId, int $itemId, mixed $photos, ?int $userId = null): array
    {
        if (empty($photos)) {
            return [];
        }
        if (!is_array($photos)) {
            $photos = [$photos];
        }

        $saved = [];
        $index = 1;

        foreach ($photos as $photoItem) {
            $photoData = is_array($photoItem) ? ($photoItem['photo'] ?? $photoItem['image'] ?? $photoItem['file'] ?? $photoItem['data'] ?? null) : $photoItem;
            $caption = is_array($photoItem) ? ($photoItem['caption'] ?? $photoItem['notes'] ?? null) : null;

            if (empty($photoData)) {
                continue;
            }

            $storedPath = $this->storeLabelPhotoImage($picklistId, $itemId, $index, $photoData);
            if ($storedPath) {
                $fileName = basename($storedPath);
                $fileSize = null;
                $mimeType = 'image/jpeg';

                if (str_ends_with(strtolower($fileName), '.png')) {
                    $mimeType = 'image/png';
                } elseif (str_ends_with(strtolower($fileName), '.webp')) {
                    $mimeType = 'image/webp';
                }

                try {
                    if (Storage::disk('public')->exists($storedPath)) {
                        $fileSize = Storage::disk('public')->size($storedPath);
                    }
                } catch (\Throwable $e) {}

                $photoModel = PicklistItemLabelPhoto::create([
                    'picklist_id'      => $picklistId,
                    'picklist_item_id' => $itemId,
                    'photo_path'       => $storedPath,
                    'file_name'        => $fileName,
                    'file_size'        => $fileSize,
                    'mime_type'        => $mimeType,
                    'caption'          => $caption,
                    'created_by'       => $userId,
                ]);

                $saved[] = $photoModel;
                $index++;
            }
        }

        return $saved;
    }

    /**
     * Store label photo image to public storage and sync to S3.
     *
     * @param int $picklistId
     * @param int $itemId
     * @param int $index
     * @param mixed $photoData
     * @return string|null
     */
    protected function storeLabelPhotoImage(int $picklistId, int $itemId, int $index, mixed $photoData): ?string
    {
        $dir = "label_photos/picklists/{$picklistId}/item_{$itemId}";
        $timestamp = time() . '_' . substr(md5(uniqid()), 0, 6);
        $s3Bucket = config('filesystems.disks.s3.bucket');
        $preferS3 = !empty($s3Bucket) && env('SIGNATURE_STORAGE_DISK', 's3') === 's3';

        if ($photoData instanceof \Illuminate\Http\UploadedFile) {
            $ext = $photoData->getClientOriginalExtension() ?: 'jpg';
            $filename = "{$dir}/lbl_{$picklistId}_{$itemId}_{$index}_{$timestamp}.{$ext}";

            try {
                if (!Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir);
                }
                Storage::disk('public')->putFileAs($dir, $photoData, basename($filename));
            } catch (\Throwable $e) {
                Log::warning("Failed to store label photo locally: " . $e->getMessage());
            }

            if ($preferS3) {
                try {
                    Storage::disk('s3')->putFileAs($dir, $photoData, basename($filename), 'public');
                } catch (\Throwable $e) {
                    Log::warning("Failed to store label photo to S3: " . $e->getMessage());
                }
            }

            return $filename;
        }

        if (is_string($photoData)) {
            $photoStr = trim($photoData);
            if (empty($photoStr)) {
                return null;
            }

            if (str_starts_with($photoStr, 'label_photos/') || str_starts_with($photoStr, 'http://') || str_starts_with($photoStr, 'https://')) {
                return $photoStr;
            }

            if (str_starts_with($photoStr, 'data%3A') || str_contains($photoStr, '%2C')) {
                $photoStr = urldecode($photoStr);
            }

            $ext = 'jpg';
            $binary = false;

            if (preg_match('/^data:image\/([a-zA-Z0-9\+\-\.]+);base64,(.+)$/is', $photoStr, $matches)) {
                $rawExt = strtolower($matches[1]);
                $ext = match ($rawExt) {
                    'jpeg' => 'jpg',
                    'svg+xml' => 'svg',
                    default => $rawExt,
                };
                $cleanB64 = str_replace([' ', "\r", "\n", "\t"], ['+', '', '', ''], trim($matches[2]));
                $binary = base64_decode($cleanB64);
            } else {
                $cleanB64 = str_replace([' ', "\r", "\n", "\t"], ['+', '', '', ''], $photoStr);
                $binary = base64_decode($cleanB64);
            }

            if ($binary === false || empty($binary)) {
                return null;
            }

            $filename = "{$dir}/lbl_{$picklistId}_{$itemId}_{$index}_{$timestamp}.{$ext}";

            try {
                if (!Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir);
                }
                Storage::disk('public')->put($filename, $binary);
            } catch (\Throwable $e) {
                Log::warning("Failed to store label photo locally: " . $e->getMessage());
            }

            if ($preferS3) {
                try {
                    Storage::disk('s3')->put($filename, $binary, 'public');
                } catch (\Throwable $e) {
                    Log::warning("Failed to store label photo to S3: " . $e->getMessage());
                }
            }

            return $filename;
        }

        return null;
    }

    /**
     * Preview label photo directly (allows standard <img> rendering).
     *
     * @param int $picklistId
     * @param int $itemId
     * @param int $photoId
     * @return \Illuminate\Http\Response
     */
    public function previewLabelPhoto(int $picklistId, int $itemId, int $photoId)
    {
        $photo = PicklistItemLabelPhoto::where('picklist_id', $picklistId)
            ->where('picklist_item_id', $itemId)
            ->where('id', $photoId)
            ->first();

        if (!$photo || empty($photo->photo_path)) {
            abort(404, 'Label photo not found.');
        }

        $path = $photo->photo_path;

        if (str_starts_with($path, 'data:image/')) {
            $parts = explode(',', $path, 2);
            $mime = 'image/jpeg';
            if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);/i', $parts[0], $m)) {
                $mime = $m[1];
            }
            $binary = base64_decode($parts[1] ?? '');
            return response($binary, 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        if (config('filesystems.disks.s3.bucket') && Storage::disk('s3')->exists($path)) {
            $mime = Storage::disk('s3')->mimeType($path) ?: ($photo->mime_type ?: 'image/jpeg');
            return response(Storage::disk('s3')->get($path), 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        if (Storage::disk('public')->exists($path)) {
            $mime = Storage::disk('public')->mimeType($path) ?: ($photo->mime_type ?: 'image/jpeg');
            return response(Storage::disk('public')->get($path), 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $localPath = storage_path('app/public/' . $path);
        if (file_exists($localPath)) {
            $mime = mime_content_type($localPath) ?: ($photo->mime_type ?: 'image/jpeg');
            return response(file_get_contents($localPath), 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        abort(404, 'Label photo file not found on disk.');
    }
}
