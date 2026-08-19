<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Repositories\ProductionRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

class ProductionService
{
    protected ProductionRepositoryInterface $productionRepository;
    protected AuditLogService $auditLogService;

    /**
     * ProductionService constructor.
     *
     * @param  ProductionRepositoryInterface  $productionRepository
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(
        ProductionRepositoryInterface $productionRepository,
        AuditLogService $auditLogService
    ) {
        $this->productionRepository = $productionRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get all production resources.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAllResources(array $filters = []): Collection
    {
        return $this->productionRepository->getAllResources($filters);
    }

    /**
     * Get all production items.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAllItems(array $filters = []): Collection
    {
        return $this->productionRepository->getAllItems($filters);
    }

    /**
     * Sync production resources from SAP.
     *
     * @param  int|null  $userId
     * @return array
     */
    public function syncResourcesFromSap(?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(15)->post("{$sapUrl}/api/GetResource");

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk sinkronisasi resource.');
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP GetResource mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        $sapData = $body['Result'] ?? [];
        $synced = [];

        foreach ($sapData as $res) {
            $synced[] = $this->productionRepository->upsertResource([
                'res_code' => $res['ResCode'],
                'res_name' => $res['ResName'],
                'unit_of_msr' => $res['UnitOfMsr'] ?? null,
                'is_active' => true,
            ]);
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'SYNC_PRODUCTION_RESOURCES',
                'Synchronized ' . count($synced) . ' production resources from SAP.'
            );
        }

        return $synced;
    }

    /**
     * Sync production items from SAP.
     *
     * @param  int|null  $userId
     * @return array
     */
    public function syncItemsFromSap(?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(15)->post("{$sapUrl}/api/ListItemProd");

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk sinkronisasi production item.');
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP ListItemProd mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        $sapData = $body['Result'] ?? [];
        $synced = [];

        foreach ($sapData as $item) {
            $synced[] = $this->productionRepository->upsertItem([
                'item_code' => $item['ItemCode'],
                'item_name' => $item['ItemName'],
                'i_uom_entry' => isset($item['IUoMEntry']) ? (int)$item['IUoMEntry'] : null,
                'invntry_uom' => $item['InvntryUom'] ?? null,
                'is_active' => true,
            ]);
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'SYNC_PRODUCTION_ITEMS',
                'Synchronized ' . count($synced) . ' production items from SAP.'
            );
        }

        return $synced;
    }

    /**
     * Get all production BOMs.
     */
    public function getAllBoms(array $filters = []): Collection
    {
        return $this->productionRepository->getAllBoms($filters);
    }

    /**
     * Get production BOM by ID.
     */
    public function getBomById(int $id): ?\App\Models\ProductionBom
    {
        return $this->productionRepository->getBomById($id);
    }

    /**
     * Create a new production BOM.
     */
    public function createBom(array $data, ?int $userId = null): \App\Models\ProductionBom
    {
        $bom = $this->productionRepository->createBom($data);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'CREATE_PRODUCTION_BOM',
                "Created production BOM with code {$bom->code}."
            );
        }

        return $bom;
    }

    /**
     * Update an existing production BOM.
     */
    public function updateBom(int $id, array $data, ?int $userId = null): \App\Models\ProductionBom
    {
        $bom = $this->productionRepository->getBomById($id);
        if (!$bom) {
            throw new \Exception('Bill of Material tidak ditemukan.');
        }

        $updatedBom = $this->productionRepository->updateBom($bom, $data);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'UPDATE_PRODUCTION_BOM',
                "Updated production BOM with code {$updatedBom->code}."
            );
        }

        return $updatedBom;
    }

    /**
     * Delete a production BOM.
     */
    public function deleteBom(int $id, ?int $userId = null): bool
    {
        $bom = $this->productionRepository->getBomById($id);
        if (!$bom) {
            throw new \Exception('Bill of Material tidak ditemukan.');
        }

        $code = $bom->code;
        $deleted = $this->productionRepository->deleteBom($bom);

        if ($deleted && $userId) {
            $this->auditLogService->log(
                $userId,
                'DELETE_PRODUCTION_BOM',
                "Deleted production BOM with code {$code}."
            );
        }

        return $deleted;
    }

    /**
     * Get all production orders.
     */
    public function getAllOrders(array $filters = []): Collection
    {
        return $this->productionRepository->getAllOrders($filters);
    }

    /**
     * Get production order by ID.
     */
    public function getOrderById(int $id): ?\App\Models\ProductionOrder
    {
        return $this->productionRepository->getOrderById($id);
    }

    /**
     * Create a new production order.
     */
    public function createOrder(array $data, ?int $userId = null): \App\Models\ProductionOrder
    {
        $order = $this->productionRepository->createOrder($data);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'CREATE_PRODUCTION_ORDER',
                "Created production order with number {$order->prod_order_no}."
            );
        }

        return $order;
    }

    /**
     * Update an existing production order.
     */
    public function updateOrder(int $id, array $data, ?int $userId = null): \App\Models\ProductionOrder
    {
        $order = $this->productionRepository->getOrderById($id);
        if (!$order) {
            throw new \Exception('Production Order tidak ditemukan.');
        }

        $oldStatus = strtoupper(trim((string) $order->status));
        $newStatus = isset($data['status']) ? strtoupper(trim((string) $data['status'])) : $oldStatus;

        $updatedOrder = $this->productionRepository->updateOrder($order, $data);

        // If status changed to RELEASED and not yet synced to SAP, push to SAP
        if ($newStatus === 'RELEASED' && empty($updatedOrder->doc_entry)) {
            try {
                $sapUrl = config('services.sap.url');
                $mapShift = function ($shiftValue) {
                    $val = trim((string) $shiftValue);
                    $upper = strtoupper($val);
                    if ($upper === 'ALL' || $upper === 'X') return 'X';
                    if ($upper === 'SHIFT 1' || $upper === 'SHIFT1' || $upper === '1' || $upper === 'A') return 'A';
                    if ($upper === 'SHIFT 2' || $upper === 'SHIFT2' || $upper === '2' || $upper === 'B') return 'B';
                    if ($upper === 'SHIFT 3' || $upper === 'SHIFT3' || $upper === '3' || $upper === 'C') return 'C';
                    return $val !== '' ? $val : 'X';
                };

                $lines = [];
                foreach ($updatedOrder->details as $item) {
                    $itemType = 'I';
                    if ($item->type === 'Resource' || $item->type === '290' || $item->type === 'R') $itemType = 'R';
                    elseif ($item->type === 'Text' || $item->type === 'T') $itemType = 'T';

                    $lines[] = [
                        'ItemType'    => $itemType,
                        'ItemCode'    => (string) $item->item_code,
                        'BaseQty'     => floatval($item->base_qty),
                        'WhsCode'     => (string) $item->warehouse,
                        'IssueMethod' => (string) ($item->issue_mthd ?: 'M'),
                        'OcrCode'     => (string) $item->ocr_code,
                        'OcrCode2'    => (string) $item->ocr_code2,
                        'OcrCode3'    => (string) $item->ocr_code3,
                    ];
                }

                $sapPayload = [
                    'ItemCode'    => (string) $updatedOrder->item_code,
                    'Series'      => is_numeric($updatedOrder->series) ? (int) $updatedOrder->series : 15,
                    'PlannedQty'  => floatval($updatedOrder->planned_qty),
                    'PostingDate' => $updatedOrder->post_date ? date('Y-m-d\TH:i:s', strtotime($updatedOrder->post_date)) : date('Y-m-d\TH:i:s'),
                    'DueDate'     => $updatedOrder->due_date ? date('Y-m-d\TH:i:s', strtotime($updatedOrder->due_date)) : date('Y-m-d\TH:i:s'),
                    'WhsCode'     => (string) $updatedOrder->warehouse,
                    'Remarks'     => (string) $updatedOrder->comments,
                    'Shift'       => $mapShift($updatedOrder->u_shift),
                    'Unit'        => (string) $updatedOrder->u_unit,
                    'Bomid'       => (string) $updatedOrder->production_bom_id,
                    'UserId'      => (string) ($userId ? (string)$userId : '1'),
                    'AddonId'     => '2',
                    'Lines'       => $lines,
                ];

                $response = Http::timeout(30)->post("{$sapUrl}/api/addpdo", $sapPayload);
                if ($response->successful()) {
                    $body = $response->json();
                    if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                        $updatedOrder->update([
                            'doc_entry'     => $body['DocEntry'] ?? $body['doc_entry'] ?? null,
                            'doc_num'       => $body['DocNum'] ?? $body['doc_num'] ?? null,
                            'sap_status'    => 'SYNCED',
                            'integrated_at' => now(),
                        ]);
                    }
                }
            } catch (\Exception $ex) {
                $updatedOrder->update([
                    'sap_status' => 'FAILED',
                    'sap_error'  => $ex->getMessage(),
                ]);
            }
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'UPDATE_PRODUCTION_ORDER',
                "Updated production order with number {$updatedOrder->prod_order_no}."
            );
        }

        return $updatedOrder->fresh([
            'parentItem',
            'details.item',
            'details.resource',
            'details.warehouseModel',
            'details.ocr',
            'details.ocr2',
            'details.ocr3',
            'ocr',
            'ocr2',
            'ocr3',
            'warehouseModel'
        ]);
    }

    /**
     * Delete a production order.
     */
    public function deleteOrder(int $id, ?int $userId = null): bool
    {
        $order = $this->productionRepository->getOrderById($id);
        if (!$order) {
            throw new \Exception('Production Order tidak ditemukan.');
        }

        $orderNo = $order->prod_order_no;
        $deleted = $this->productionRepository->deleteOrder($order);

        if ($deleted && $userId) {
            $this->auditLogService->log(
                $userId,
                'DELETE_PRODUCTION_ORDER',
                "Deleted production order with number {$orderNo}."
            );
        }

        return $deleted;
    }

    /**
     * Post/Add Production Order (PDO) to SAP via API endpoint /api/addpdo or Save Locally
     *
     * @param array $data
     * @param int|null $userId
     * @return array
     */
    public function addPdoSap(array $data, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        $mapShift = function ($shiftValue) {
            $val = trim((string) $shiftValue);
            $upper = strtoupper($val);
            if ($upper === 'ALL' || $upper === 'X') {
                return 'X';
            }
            if ($upper === 'SHIFT 1' || $upper === 'SHIFT1' || $upper === '1' || $upper === 'A') {
                return 'A';
            }
            if ($upper === 'SHIFT 2' || $upper === 'SHIFT2' || $upper === '2' || $upper === 'B') {
                return 'B';
            }
            if ($upper === 'SHIFT 3' || $upper === 'SHIFT3' || $upper === '3' || $upper === 'C') {
                return 'C';
            }
            return $val !== '' ? $val : 'X';
        };

        // Determine status sent by FE (default to PLANNED)
        $status = strtoupper(trim((string) ($data['status'] ?? $data['Status'] ?? 'PLANNED')));
        if (empty($status)) {
            $status = 'PLANNED';
        }

        // Format Lines & Details for both SAP payload and Local DB
        $lines = [];
        $localDetails = [];
        $rawLines = $data['Lines'] ?? $data['lines'] ?? $data['details'] ?? [];

        foreach ($rawLines as $detail) {
            $type = $detail['type'] ?? $detail['ItemType'] ?? 'Item';
            if ($type === 'Item' || $type === '4' || $type === 4 || $type === 'I') {
                $itemType = 'I';
                $localType = 'Item';
            } elseif ($type === 'Resource' || $type === '290' || $type === 290 || $type === 'R') {
                $itemType = 'R';
                $localType = 'Resource';
            } elseif ($type === 'Text' || $type === 'T') {
                $itemType = 'T';
                $localType = 'Text';
            } else {
                $itemType = 'I';
                $localType = 'Item';
            }

            $compCode = (string) ($detail['item_code'] ?? $detail['ItemCode'] ?? $detail['code'] ?? '');
            if (is_array($detail['item'] ?? null)) {
                $compCode = $detail['item']['value'] ?? $compCode;
            } elseif (is_string($detail['item'] ?? null)) {
                $compCode = $detail['item'];
            }

            $baseQty = floatval($detail['base_qty'] ?? $detail['BaseQty'] ?? $detail['quantity'] ?? 1.0);
            $pQty = floatval($detail['planned_qty'] ?? $detail['PlannedQty'] ?? ($baseQty * (floatval($data['planned_qty'] ?? $data['PlannedQty'] ?? 1) > 0 ? floatval($data['planned_qty'] ?? $data['PlannedQty'] ?? 1) : 1)));
            $whsCode = (string) ($detail['warehouse'] ?? $detail['whs_code'] ?? $detail['WhsCode'] ?? '');
            $issueMethod = (string) ($detail['issue_mthd'] ?? $detail['issueMethod'] ?? $detail['IssueMethod'] ?? 'M');
            $ocrCode = (string) ($detail['ocr_code'] ?? $detail['OcrCode'] ?? '');
            $ocrCode2 = (string) ($detail['ocr_code2'] ?? $detail['OcrCode2'] ?? '');
            $ocrCode3 = (string) ($detail['ocr_code3'] ?? $detail['OcrCode3'] ?? '');
            $comments = (string) ($detail['comments'] ?? $detail['Remarks'] ?? '');

            $lines[] = [
                'ItemType'    => $itemType,
                'ItemCode'    => $compCode,
                'BaseQty'     => $baseQty,
                'WhsCode'     => $whsCode,
                'IssueMethod' => $issueMethod,
                'OcrCode'     => $ocrCode,
                'OcrCode2'    => $ocrCode2,
                'OcrCode3'    => $ocrCode3,
            ];

            $localDetails[] = [
                'type'          => $localType,
                'item_code'     => $compCode,
                'base_qty'      => $baseQty,
                'planned_qty'   => $pQty,
                'issued_qty'    => floatval($detail['issued_qty'] ?? $detail['IssuedQty'] ?? 0),
                'available_qty' => floatval($detail['available_qty'] ?? $detail['AvailableQty'] ?? 0),
                'warehouse'     => $whsCode,
                'issue_mthd'    => $issueMethod,
                'ocr_code'      => $ocrCode,
                'ocr_code2'     => $ocrCode2,
                'ocr_code3'     => $ocrCode3,
                'comments'      => $comments,
            ];
        }

        $itemCode = (string) ($data['item_code'] ?? $data['product_code'] ?? $data['ItemCode'] ?? '');
        if (is_array($data['product'] ?? null)) {
            $itemCode = $data['product']['value'] ?? $itemCode;
        } elseif (is_string($data['product'] ?? null)) {
            $itemCode = $data['product'];
        }

        $plannedQty = floatval($data['planned_qty'] ?? $data['planned_quantity'] ?? $data['PlannedQty'] ?? 0);
        $whs = (string) ($data['warehouse'] ?? $data['whs_code'] ?? $data['to_whs'] ?? $data['WhsCode'] ?? '');
        $postDate = isset($data['post_date']) ? date('Y-m-d\TH:i:s', strtotime($data['post_date'])) : (isset($data['PostingDate']) ? date('Y-m-d\TH:i:s', strtotime($data['PostingDate'])) : date('Y-m-d\TH:i:s'));
        $dueDate = isset($data['due_date']) ? date('Y-m-d\TH:i:s', strtotime($data['due_date'])) : (isset($data['DueDate']) ? date('Y-m-d\TH:i:s', strtotime($data['DueDate'])) : date('Y-m-d\TH:i:s'));
        $comments = (string) ($data['comments'] ?? $data['remarks'] ?? $data['Remarks'] ?? '');
        $shift = $mapShift($data['u_shift'] ?? $data['shift'] ?? $data['Shift'] ?? '');
        $unit = (string) ($data['u_unit'] ?? $data['unit'] ?? $data['Unit'] ?? '');
        $bomId = (string) ($data['production_bom_id'] ?? $data['bom_id'] ?? $data['Bomid'] ?? '');

        // 1. Simpan ke database lokal dengan status yang dikirimkan FE
        $localData = [
            'item_code'         => $itemCode,
            'status'            => $status,
            'type'              => $data['type'] ?? 'Standard',
            'series'            => is_numeric($data['series'] ?? $data['Series'] ?? null) ? (int) ($data['series'] ?? $data['Series']) : 15,
            'planned_qty'       => $plannedQty,
            'warehouse'         => $whs,
            'post_date'         => date('Y-m-d', strtotime($postDate)),
            'start_date'        => isset($data['start_date']) ? date('Y-m-d', strtotime($data['start_date'])) : date('Y-m-d', strtotime($postDate)),
            'due_date'          => date('Y-m-d', strtotime($dueDate)),
            'comments'          => $comments,
            'u_shift'           => $shift,
            'u_unit'            => $unit,
            'production_bom_id' => $bomId,
            'ocr_code'          => $data['ocr_code'] ?? $data['OcrCode'] ?? null,
            'ocr_code2'         => $data['ocr_code2'] ?? $data['OcrCode2'] ?? null,
            'ocr_code3'         => $data['ocr_code3'] ?? $data['OcrCode3'] ?? null,
            'sap_status'        => $status === 'RELEASED' ? 'PENDING' : 'PENDING',
            'created_by'        => $userId,
            'updated_by'        => $userId,
            'details'           => $localDetails,
        ];

        $order = $this->createOrder($localData, $userId);

        $sapResponse = null;

        // 2. Jika status adalah RELEASED, tembak ke SAP /api/addpdo
        if ($status === 'RELEASED') {
            $sapPayload = [
                'ItemCode'    => $itemCode,
                'Series'      => $localData['series'],
                'PlannedQty'  => $plannedQty,
                'PostingDate' => $postDate,
                'DueDate'     => $dueDate,
                'WhsCode'     => $whs,
                'Remarks'     => $comments,
                'Shift'       => $shift,
                'Unit'        => $unit,
                'Bomid'       => $bomId,
                'UserId'      => (string) ($data['user_id'] ?? $data['UserId'] ?? ($userId ? (string)$userId : '1')),
                'AddonId'     => (string) ($data['addon_id'] ?? $data['AddonId'] ?? '2'),
                'Lines'       => $lines,
            ];

            try {
                $response = Http::timeout(30)->post("{$sapUrl}/api/addpdo", $sapPayload);
                if ($response->successful()) {
                    $body = $response->json();
                    if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                        $order->update([
                            'doc_entry'     => $body['DocEntry'] ?? $body['doc_entry'] ?? null,
                            'doc_num'       => $body['DocNum'] ?? $body['doc_num'] ?? null,
                            'sap_status'    => 'SYNCED',
                            'integrated_at' => now(),
                        ]);
                        $sapResponse = $body;
                    } else {
                        $order->update([
                            'sap_status' => 'FAILED',
                            'sap_error'  => $body['Message'] ?? 'Unknown SAP error',
                        ]);
                    }
                }
            } catch (\Exception $ex) {
                $order->update([
                    'sap_status' => 'FAILED',
                    'sap_error'  => $ex->getMessage(),
                ]);
            }
        }

        return [
            'order'        => $order->fresh([
                'parentItem',
                'details.item',
                'details.resource',
                'details.warehouseModel',
                'details.ocr',
                'details.ocr2',
                'details.ocr3',
                'ocr',
                'ocr2',
                'ocr3',
                'warehouseModel'
            ]),
            'status'       => $order->status,
            'sap_response' => $sapResponse,
        ];
    }

    /**
     * Get list of Production Orders (PDO) from SAP API endpoint (/api/getListPDO).
     *
     * @param array $filters
     * @param int|null $userId
     * @return array
     */
    public function getListPdoSap(array $filters = [], ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        $rawFrom = $filters['from'] ?? $filters['from_date'] ?? $filters['From'] ?? date('Y-1-1');
        $rawTo = $filters['to'] ?? $filters['to_date'] ?? $filters['To'] ?? date('Y-12-31');

        $fromTime = strtotime((string) $rawFrom);
        $toTime = strtotime((string) $rawTo);

        $fromFormatted = $fromTime ? date('Y-n-j', $fromTime) : (string) $rawFrom;
        $toFormatted = $toTime ? date('Y-n-j', $toTime) : (string) $rawTo;

        $whsCode = (string) ($filters['whs_code'] ?? $filters['warehouse'] ?? $filters['WhsCode'] ?? '');
        $toWhsCode = (string) ($filters['to_whs_code'] ?? $filters['to_warehouse'] ?? $filters['ToWhsCode'] ?? '');

        $payload = [
            'From'      => $fromFormatted,
            'To'        => $toFormatted,
            'WhsCode'   => $whsCode,
            'ToWhsCode' => $toWhsCode,
        ];

        $items = [];

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/getListPDO", $payload);

            if ($response->successful()) {
                $body = $response->json();
                if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                    $rawItems = $body['Result'] ?? [];
                    if (is_array($rawItems)) {
                        $items = array_values(array_filter($rawItems, function ($item) {
                            if (!is_array($item)) return false;
                            $docEntry = (string) ($item['DocEntry'] ?? '');
                            $docNum = (string) ($item['DocNum'] ?? '');
                            return !in_array($docEntry, ['0', '']) && !in_array($docNum, ['0', '']);
                        }));
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback: proceed to merge local orders
        }

        // Fetch and merge local PLANNED production orders (not yet synced to SAP)
        try {
            $localPlannedQuery = \App\Models\ProductionOrder::query()
                ->with(['parentItem', 'details'])
                ->where(function ($q) {
                    $q->where('status', 'PLANNED')
                      ->orWhereNull('doc_entry')
                      ->orWhere('sap_status', '!=', 'SYNCED');
                });

            if (!empty($whsCode)) {
                $localPlannedQuery->where('warehouse', $whsCode);
            }

            $localOrders = $localPlannedQuery->orderBy('id', 'desc')->get();
            $localItems = [];
            foreach ($localOrders as $lOrder) {
                $localItems[] = [
                    'id'          => $lOrder->id,
                    'DocEntry'    => (string) ($lOrder->doc_entry ?: $lOrder->id),
                    'DocNum'      => (string) ($lOrder->doc_num ?: $lOrder->prod_order_no),
                    'ItemCode'    => (string) $lOrder->item_code,
                    'ProdName'    => (string) ($lOrder->parentItem?->item_name ?? $lOrder->item_code),
                    'Status'      => (string) $lOrder->status,
                    'Type'        => (string) $lOrder->type,
                    'PlannedQty'  => floatval($lOrder->planned_qty),
                    'CmpltQty'    => floatval($lOrder->cmplt_qty),
                    'RjctQty'     => floatval($lOrder->rjct_qty),
                    'PostDate'    => $lOrder->post_date ? date('Y-m-d\TH:i:s', strtotime($lOrder->post_date)) : null,
                    'DueDate'     => $lOrder->due_date ? date('Y-m-d\TH:i:s', strtotime($lOrder->due_date)) : null,
                    'WhsCode'     => (string) $lOrder->warehouse,
                    'Remarks'     => (string) $lOrder->comments,
                    'is_local'    => true,
                ];
            }

            $items = array_merge($localItems, $items);
        } catch (\Exception $e) {
            // Ignore DB error if table not ready
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'GET_LIST_PDO_SAP',
                "Fetched Production Orders list (From: {$fromFormatted}, To: {$toFormatted})."
            );
        }

        return [
            'filters' => $payload,
            'items'   => $items,
        ];
    }

    /**
     * Get detail of Production Order (PDO) from SAP API endpoint (/api/getPDObyId) or Local DB.
     *
     * @param string|int $customQuery
     * @param int|null $userId
     * @return array
     */
    public function getPdoById(string|int $customQuery, ?int $userId = null): array
    {
        // 1. Check local database first
        try {
            $localOrder = \App\Models\ProductionOrder::with([
                'parentItem',
                'details.item',
                'details.resource',
                'details.warehouseModel',
                'details.ocr',
                'details.ocr2',
                'details.ocr3',
                'ocr',
                'ocr2',
                'ocr3',
                'warehouseModel'
            ])->where('id', is_numeric($customQuery) ? (int)$customQuery : 0)
              ->orWhere('prod_order_no', (string) $customQuery)
              ->orWhere('doc_entry', is_numeric($customQuery) ? (int)$customQuery : 0)
              ->orWhere('doc_num', (string) $customQuery)
              ->first();

            if ($localOrder && ($localOrder->status === 'PLANNED' || empty($localOrder->doc_entry))) {
                $header = [
                    'id'          => $localOrder->id,
                    'DocEntry'    => (string) ($localOrder->doc_entry ?: $localOrder->id),
                    'DocNum'      => (string) ($localOrder->doc_num ?: $localOrder->prod_order_no),
                    'Series'      => $localOrder->series ?: 15,
                    'ItemCode'    => (string) $localOrder->item_code,
                    'ProdName'    => (string) ($localOrder->parentItem?->item_name ?? $localOrder->item_code),
                    'Status'      => (string) $localOrder->status,
                    'Type'        => (string) $localOrder->type,
                    'PlannedQty'  => floatval($localOrder->planned_qty),
                    'CmpltQty'    => floatval($localOrder->cmplt_qty),
                    'RjctQty'     => floatval($localOrder->rjct_qty),
                    'PostDate'    => $localOrder->post_date ? date('Y-m-d\TH:i:s', strtotime($localOrder->post_date)) : null,
                    'DueDate'     => $localOrder->due_date ? date('Y-m-d\TH:i:s', strtotime($localOrder->due_date)) : null,
                    'WhsCode'     => (string) $localOrder->warehouse,
                    'Remarks'     => (string) $localOrder->comments,
                    'Shift'       => (string) $localOrder->u_shift,
                    'Unit'        => (string) $localOrder->u_unit,
                    'Bomid'       => (string) $localOrder->production_bom_id,
                    'is_local'    => true,
                ];

                $items = [];
                foreach ($localOrder->details as $idx => $line) {
                    $items[] = [
                        'LineNum'     => $line->line_num ?? $idx,
                        'ItemType'    => $line->type === 'Resource' ? 'R' : ($line->type === 'Text' ? 'T' : 'I'),
                        'ItemCode'    => (string) $line->item_code,
                        'ItemName'    => (string) ($line->item?->item_name ?? $line->item_code),
                        'BaseQty'     => floatval($line->base_qty),
                        'PlannedQty'  => floatval($line->planned_qty),
                        'IssuedQty'   => floatval($line->issued_qty),
                        'WhsCode'     => (string) $line->warehouse,
                        'IssueMethod' => (string) ($line->issue_mthd ?: 'M'),
                        'OcrCode'     => (string) $line->ocr_code,
                        'OcrCode2'    => (string) $line->ocr_code2,
                        'OcrCode3'    => (string) $line->ocr_code3,
                    ];
                }

                return [
                    'header' => $header,
                    'items'  => $items,
                    'order'  => $localOrder,
                ];
            }
        } catch (\Exception $e) {
            // Fallback to SAP query
        }

        // 2. Query SAP API getPDObyId
        $sapUrl = config('services.sap.url');

        $payload = [
            'CustomQuery' => (string) $customQuery,
        ];

        $response = Http::timeout(30)->post("{$sapUrl}/api/getPDObyId", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP getPDObyId. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP getPDObyId error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        $result = $body['Result'] ?? [];
        $header = $result['Table1'][0] ?? (is_array($result) && !isset($result['Table1']) ? ($result[0] ?? $result) : null);
        $items = $result['Table2'] ?? [];

        // Check if header is a dummy 0 record
        if ($header && is_array($header)) {
            $hDocEntry = (string) ($header['DocEntry'] ?? '');
            $hDocNum = (string) ($header['DocNum'] ?? '');
            if (in_array($hDocEntry, ['0', '']) && in_array($hDocNum, ['0', ''])) {
                $header = null;
                $items = [];
            }
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'GET_PDO_DETAIL_SAP',
                "Fetched Production Order detail from SAP for query: {$customQuery}."
            );
        }

        return [
            'header' => $header,
            'items'  => $items,
            'raw'    => $result,
        ];
    }

    /**
     * Get list of Production Receipts from SAP API endpoint (/api/getListReceiptProd).
     *
     * @param array $filters
     * @param int|null $userId
     * @return array
     */
    public function getListReceiptProd(array $filters = [], ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        $rawFrom = $filters['from'] ?? $filters['from_date'] ?? $filters['From'] ?? date('Y-1-1');
        $rawTo = $filters['to'] ?? $filters['to_date'] ?? $filters['To'] ?? date('Y-12-31');

        $fromTime = strtotime((string) $rawFrom);
        $toTime = strtotime((string) $rawTo);

        $fromFormatted = $fromTime ? date('Y-n-j', $fromTime) : (string) $rawFrom;
        $toFormatted = $toTime ? date('Y-n-j', $toTime) : (string) $rawTo;

        $whsCode = (string) ($filters['whs_code'] ?? $filters['warehouse'] ?? $filters['WhsCode'] ?? '');
        $toWhsCode = (string) ($filters['to_whs_code'] ?? $filters['to_warehouse'] ?? $filters['ToWhsCode'] ?? '');

        $payload = [
            'From'      => $fromFormatted,
            'To'        => $toFormatted,
            'WhsCode'   => $whsCode,
            'ToWhsCode' => $toWhsCode,
        ];

        $response = Http::timeout(30)->post("{$sapUrl}/api/getListReceiptProd", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP getListReceiptProd. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP getListReceiptProd error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        $items = $body['Result'] ?? [];
        if (is_array($items)) {
            $items = array_values(array_filter($items, function ($item) {
                if (!is_array($item)) return false;
                $docEntry = (string) ($item['DocEntry'] ?? '');
                $docNum = (string) ($item['DocNum'] ?? '');
                return !in_array($docEntry, ['0', '']) && !in_array($docNum, ['0', '']);
            }));
        } else {
            $items = [];
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'GET_LIST_RECEIPT_PROD_SAP',
                "Fetched Production Receipts list from SAP (From: {$fromFormatted}, To: {$toFormatted})."
            );
        }

        return [
            'filters' => $payload,
            'items'   => $items,
        ];
    }

    /**
     * Get detail of Production Receipt from SAP API endpoint (/api/getReceiptProdbyId).
     *
     * @param string|int $customQuery
     * @param int|null $userId
     * @return array
     */
    public function getReceiptProdById(string|int $customQuery, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        $payload = [
            'CustomQuery' => (string) $customQuery,
        ];

        $response = Http::timeout(30)->post("{$sapUrl}/api/getReceiptProdbyId", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP getReceiptProdbyId. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP getReceiptProdbyId error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        $result = $body['Result'] ?? [];
        $header = $result['Table1'][0] ?? null;
        $items = $result['Table2'] ?? [];

        // Check if header is a dummy 0 record
        if ($header && is_array($header)) {
            $hDocEntry = (string) ($header['DocEntry'] ?? '');
            $hDocNum = (string) ($header['DocNum'] ?? '');
            if (in_array($hDocEntry, ['0', '']) && in_array($hDocNum, ['0', ''])) {
                $header = null;
                $items = [];
            }
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'GET_RECEIPT_PROD_DETAIL_SAP',
                "Fetched Production Receipt detail from SAP for query: {$customQuery}."
            );
        }

        return [
            'header' => $header,
            'items'  => $items,
            'raw'    => $result,
        ];
    }

    /**
     * Get list of Issue for Production from SAP API endpoint (/api/getListIssueProd).
     *
     * @param array $filters
     * @param int|null $userId
     * @return array
     */
    public function getListIssueProd(array $filters = [], ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        $rawFrom = $filters['from'] ?? $filters['from_date'] ?? $filters['From'] ?? date('Y-1-1');
        $rawTo = $filters['to'] ?? $filters['to_date'] ?? $filters['To'] ?? date('Y-12-31');

        $fromTime = strtotime((string) $rawFrom);
        $toTime = strtotime((string) $rawTo);

        $fromFormatted = $fromTime ? date('Y-n-j', $fromTime) : (string) $rawFrom;
        $toFormatted = $toTime ? date('Y-n-j', $toTime) : (string) $rawTo;

        $whsCode = (string) ($filters['whs_code'] ?? $filters['warehouse'] ?? $filters['WhsCode'] ?? '');
        $toWhsCode = (string) ($filters['to_whs_code'] ?? $filters['to_warehouse'] ?? $filters['ToWhsCode'] ?? '');

        $payload = [
            'From'      => $fromFormatted,
            'To'        => $toFormatted,
            'WhsCode'   => $whsCode,
            'ToWhsCode' => $toWhsCode,
        ];

        $response = Http::timeout(30)->post("{$sapUrl}/api/getListIssueProd", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP getListIssueProd. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP getListIssueProd error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        $items = $body['Result'] ?? [];
        if (is_array($items)) {
            $items = array_values(array_filter($items, function ($item) {
                if (!is_array($item)) return false;
                $docEntry = (string) ($item['DocEntry'] ?? '');
                $docNum = (string) ($item['DocNum'] ?? '');
                return !in_array($docEntry, ['0', '']) && !in_array($docNum, ['0', '']);
            }));
        } else {
            $items = [];
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'GET_LIST_ISSUE_PROD_SAP',
                "Fetched Issue for Production list from SAP (From: {$fromFormatted}, To: {$toFormatted})."
            );
        }

        return [
            'filters' => $payload,
            'items'   => $items,
        ];
    }

    /**
     * Get detail of Issue for Production from SAP API endpoint (/api/getIssueProdbyId).
     *
     * @param string|int $customQuery
     * @param int|null $userId
     * @return array
     */
    public function getIssueProdById(string|int $customQuery, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        $payload = [
            'CustomQuery' => (string) $customQuery,
        ];

        $response = Http::timeout(30)->post("{$sapUrl}/api/getIssueProdbyId", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP getIssueProdbyId. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP getIssueProdbyId error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        $result = $body['Result'] ?? [];
        $header = $result['Table1'][0] ?? null;
        $items = $result['Table2'] ?? [];

        // Check if header is a dummy 0 record
        if ($header && is_array($header)) {
            $hDocEntry = (string) ($header['DocEntry'] ?? '');
            $hDocNum = (string) ($header['DocNum'] ?? '');
            if (in_array($hDocEntry, ['0', '']) && in_array($hDocNum, ['0', ''])) {
                $header = null;
                $items = [];
            }
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'GET_ISSUE_PROD_DETAIL_SAP',
                "Fetched Issue for Production detail from SAP for query: {$customQuery}."
            );
        }

        return [
            'header' => $header,
            'items'  => $items,
            'raw'    => $result,
        ];
    }

    /**
     * Cancel Production Order (PDO) on SAP (/api/cancelpdo).
     *
     * @param array $data
     * @param int|null $userId
     * @return array
     */
    public function cancelPdoSap(array $data, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        $docEntry = (string) ($data['doc_entry'] ?? $data['DocEntry'] ?? '');
        if (empty($docEntry)) {
            throw new \Exception('DocEntry wajib diisi untuk membatalkan PDO.');
        }

        $payload = [
            'DocEntry' => $docEntry,
            'UserId'   => $userId ?? 1,
            'AddonId'  => 2,
        ];

        $response = Http::timeout(30)->post("{$sapUrl}/api/cancelpdo", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP cancelpdo. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP cancelpdo error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'CANCEL_PDO_SAP',
                "Cancelled Production Order (PDO) on SAP for DocEntry {$docEntry}."
            );
        }

        return [
            'payload' => $payload,
            'sap_response' => $body,
        ];
    }

    /**
     * Helper to prepare payload for Add Issue or Add Receipt Production.
     *
     * @param array $data
     * @param int|null $userId
     * @param string $transactionName
     * @return array
     * @throws \Exception
     */
    protected function prepareProdTransactionPayload(array $data, ?int $userId, string $transactionName): array
    {
        $rawDocDate = $data['doc_date'] ?? $data['DocDate'] ?? null;
        $rawDocDueDate = $data['doc_due_date'] ?? $data['DocDueDate'] ?? null;

        if (empty($rawDocDate)) {
            throw new \Exception("Field 'DocDate' wajib diisi (format YYYY-MM-DD).");
        }
        if (empty($rawDocDueDate)) {
            throw new \Exception("Field 'DocDueDate' wajib diisi (format YYYY-MM-DD).");
        }

        $docDate = date('Y-m-d', strtotime((string) $rawDocDate));
        $docDueDate = date('Y-m-d', strtotime((string) $rawDocDueDate));

        $rawLines = $data['lines'] ?? $data['Lines'] ?? [];
        if (!is_array($rawLines) || empty($rawLines)) {
            throw new \Exception("Field 'Lines' wajib diisi dan minimal berisi 1 item baris.");
        }

        $lines = [];
        foreach ($rawLines as $idx => $line) {
            if (!is_array($line)) {
                continue;
            }

            $baseEntry = $line['base_entry'] ?? $line['BaseEntry'] ?? null;
            $baseLine = $line['base_line'] ?? $line['BaseLine'] ?? null;
            $quantity = $line['quantity'] ?? $line['Quantity'] ?? null;

            if ($baseEntry === null || $baseEntry === '') {
                throw new \Exception("Lines index [{$idx}]: 'BaseEntry' (DocEntry Production Order) wajib diisi.");
            }
            if ($baseLine === null || $baseLine === '') {
                throw new \Exception("Lines index [{$idx}]: 'BaseLine' (LineNum component/item) wajib diisi.");
            }
            if ($quantity === null || !is_numeric($quantity) || floatval($quantity) <= 0) {
                throw new \Exception("Lines index [{$idx}]: 'Quantity' wajib diisi dengan nilai lebih dari 0.");
            }

            $lines[] = [
                'BaseType'  => is_numeric($line['base_type'] ?? $line['BaseType'] ?? null) ? (int) ($line['base_type'] ?? $line['BaseType']) : 202,
                'BaseEntry' => is_numeric($baseEntry) ? (int) $baseEntry : (string) $baseEntry,
                'BaseLine'  => is_numeric($baseLine) ? (int) $baseLine : (string) $baseLine,
                'Quantity'  => floatval($quantity),
                'WhsCode'   => (string) ($line['whs_code'] ?? $line['warehouse'] ?? $line['WhsCode'] ?? ''),
                'UoMEntry'  => is_numeric($line['uom_entry'] ?? $line['UoMEntry'] ?? null) ? (int) ($line['uom_entry'] ?? $line['UoMEntry']) : ($line['uom_entry'] ?? $line['UoMEntry'] ?? 1),
                'OcrCode'   => (string) ($line['ocr_code'] ?? $line['OcrCode'] ?? ''),
                'OcrCode2'  => (string) ($line['ocr_code2'] ?? $line['OcrCode2'] ?? ''),
                'OcrCode3'  => (string) ($line['ocr_code3'] ?? $line['OcrCode3'] ?? ''),
            ];
        }

        if (empty($lines)) {
            throw new \Exception("Lines valid tidak ditemukan dalam request.");
        }

        return [
            'DocDate'    => $docDate,
            'DocDueDate' => $docDueDate,
            'Comments'   => (string) ($data['comments'] ?? $data['Comments'] ?? ''),
            'Shift'      => (string) ($data['shift'] ?? $data['u_shift'] ?? $data['Shift'] ?? $data['U_Shift'] ?? ''),
            'Unit'       => (string) ($data['unit'] ?? $data['u_unit'] ?? $data['Unit'] ?? $data['U_Unit'] ?? ''),
            'Bomid'      => (string) ($data['bom_id'] ?? $data['bomid'] ?? $data['u_bom_id'] ?? $data['Bomid'] ?? $data['U_BomId'] ?? ''),
            'AddonId'    => (string) ($data['addon_id'] ?? $data['AddonId'] ?? $data['U_AddonId'] ?? 'ADDON-INT-01'),
            'UserId'     => (string) ($data['user_id'] ?? $data['UserId'] ?? $data['U_UserId'] ?? ($userId ? (string)$userId : '1')),
            'Lines'      => $lines,
        ];
    }

    /**
     * Add Goods Issue for Production (Stores locally and syncs to SAP /api/addissueprod).
     *
     * @param array $data
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function addIssueProdSap(array $data, ?int $userId = null): array
    {
        $payload = $this->prepareProdTransactionPayload($data, $userId, 'Issue for Production');

        // 1. Simpan ke database lokal (production_issues & production_issue_items)
        $issueNo = 'ISS-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $firstBaseEntry = $payload['Lines'][0]['BaseEntry'] ?? null;

        $pdo = null;
        if ($firstBaseEntry) {
            $pdo = \App\Models\ProductionOrder::where('id', is_numeric($firstBaseEntry) ? (int)$firstBaseEntry : 0)
                ->orWhere('doc_entry', is_numeric($firstBaseEntry) ? (int)$firstBaseEntry : 0)
                ->orWhere('prod_order_no', (string)$firstBaseEntry)
                ->first();
        }

        $localIssue = \App\Models\ProductionIssue::create([
            'issue_no'            => $issueNo,
            'production_order_id' => $pdo?->id,
            'doc_date'            => $payload['DocDate'],
            'doc_due_date'        => $payload['DocDueDate'],
            'u_shift'             => $payload['Shift'],
            'u_unit'              => $payload['Unit'],
            'bom_id'              => $payload['Bomid'],
            'comments'            => $payload['Comments'],
            'status'              => 'POSTED',
            'sap_status'          => 'PENDING',
            'created_by'          => $userId,
            'updated_by'          => $userId,
        ]);

        foreach ($payload['Lines'] as $idx => $line) {
            $baseLine = $line['BaseLine'];
            $qty = floatval($line['Quantity']);

            // Find matching PDO item
            $pdoItem = null;
            if ($pdo) {
                $pdoItem = $pdo->details()->where('line_num', is_numeric($baseLine) ? (int)$baseLine : 0)->first();
                if (!$pdoItem && !empty($line['ItemCode'])) {
                    $pdoItem = $pdo->details()->where('item_code', $line['ItemCode'])->first();
                }
            }

            \App\Models\ProductionIssueItem::create([
                'production_issue_id'      => $localIssue->id,
                'production_order_id'      => $pdo?->id,
                'production_order_item_id' => $pdoItem?->id,
                'line_num'                 => $idx,
                'base_type'                => $line['BaseType'] ?? 202,
                'base_entry'               => (string)$line['BaseEntry'],
                'base_line'                => (string)$line['BaseLine'],
                'item_code'                => $line['ItemCode'] ?? $pdoItem?->item_code ?? null,
                'quantity'                 => $qty,
                'warehouse'                => $line['WhsCode'] ?? null,
                'uom_entry'                => (string)($line['UoMEntry'] ?? 1),
                'ocr_code'                 => $line['OcrCode'] ?? null,
                'ocr_code2'                => $line['OcrCode2'] ?? null,
                'ocr_code3'                => $line['OcrCode3'] ?? null,
            ]);

            // 2. Update issued_qty di baris bahan baku PDO
            if ($pdoItem) {
                $pdoItem->increment('issued_qty', $qty);
            }
        }

        // Catat referensi nomor issue di tabel PDO Header
        if ($pdo) {
            $existingIssues = array_filter(array_map('trim', explode(',', (string)$pdo->issue_for_production)));
            if (!in_array($issueNo, $existingIssues)) {
                $existingIssues[] = $issueNo;
                $pdo->update(['issue_for_production' => implode(', ', $existingIssues)]);
            }
        }

        // 3. Tembakkan ke SAP B1 API
        $sapUrl = config('services.sap.url');
        $sapResponse = null;
        try {
            $response = Http::timeout(45)->post("{$sapUrl}/api/addissueprod", $payload);
            if ($response->successful()) {
                $body = $response->json();
                if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                    $localIssue->update([
                        'doc_entry'     => $body['DocEntry'] ?? $body['doc_entry'] ?? null,
                        'doc_num'       => $body['DocNum'] ?? $body['doc_num'] ?? null,
                        'sap_status'    => 'SYNCED',
                        'integrated_at' => now(),
                    ]);
                    $sapResponse = $body;
                } else {
                    $localIssue->update([
                        'sap_status' => 'FAILED',
                        'sap_error'  => $body['Message'] ?? 'Unknown SAP error',
                    ]);
                }
            }
        } catch (\Exception $ex) {
            $localIssue->update([
                'sap_status' => 'FAILED',
                'sap_error'  => $ex->getMessage(),
            ]);
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'ADD_ISSUE_PROD',
                "Goods Issue for Production {$issueNo} recorded locally" . ($sapResponse ? " and synced to SAP." : ".")
            );
        }

        return [
            'issue'        => $localIssue->fresh(['items', 'productionOrder']),
            'payload'      => $payload,
            'sap_response' => $sapResponse,
        ];
    }

    /**
     * Add Receipt for Production (Stores locally and syncs to SAP /api/addreceiptprod).
     *
     * @param array $data
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function addReceiptProdSap(array $data, ?int $userId = null): array
    {
        $payload = $this->prepareProdTransactionPayload($data, $userId, 'Receipt for Production');

        // 1. Simpan ke database lokal (production_receipts & production_receipt_items)
        $receiptNo = 'RCP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $firstBaseEntry = $payload['Lines'][0]['BaseEntry'] ?? null;

        $pdo = null;
        if ($firstBaseEntry) {
            $pdo = \App\Models\ProductionOrder::where('id', is_numeric($firstBaseEntry) ? (int)$firstBaseEntry : 0)
                ->orWhere('doc_entry', is_numeric($firstBaseEntry) ? (int)$firstBaseEntry : 0)
                ->orWhere('prod_order_no', (string)$firstBaseEntry)
                ->first();
        }

        $localReceipt = \App\Models\ProductionReceipt::create([
            'receipt_no'          => $receiptNo,
            'production_order_id' => $pdo?->id,
            'doc_date'            => $payload['DocDate'],
            'doc_due_date'        => $payload['DocDueDate'],
            'u_shift'             => $payload['Shift'],
            'u_unit'              => $payload['Unit'],
            'bom_id'              => $payload['Bomid'],
            'comments'            => $payload['Comments'],
            'status'              => 'POSTED',
            'sap_status'          => 'PENDING',
            'created_by'          => $userId,
            'updated_by'          => $userId,
        ]);

        $totalReceivedQty = 0;
        foreach ($payload['Lines'] as $idx => $line) {
            $qty = floatval($line['Quantity']);
            $totalReceivedQty += $qty;

            \App\Models\ProductionReceiptItem::create([
                'production_receipt_id' => $localReceipt->id,
                'production_order_id'   => $pdo?->id,
                'line_num'              => $idx,
                'base_type'             => $line['BaseType'] ?? 202,
                'base_entry'            => (string)$line['BaseEntry'],
                'base_line'             => isset($line['BaseLine']) ? (string)$line['BaseLine'] : null,
                'item_code'             => $line['ItemCode'] ?? $pdo?->item_code ?? null,
                'quantity'              => $qty,
                'warehouse'             => $line['WhsCode'] ?? null,
                'uom_entry'             => (string)($line['UoMEntry'] ?? 1),
                'ocr_code'              => $line['OcrCode'] ?? null,
                'ocr_code2'             => $line['OcrCode2'] ?? null,
                'ocr_code3'             => $line['OcrCode3'] ?? null,
            ]);
        }

        // 2. Update cmplt_qty dan receipt_qty di PDO Header
        if ($pdo) {
            $newCmpltQty = floatval($pdo->cmplt_qty) + $totalReceivedQty;
            $existingReceipts = array_filter(array_map('trim', explode(',', (string)$pdo->receipt_from_production)));
            if (!in_array($receiptNo, $existingReceipts)) {
                $existingReceipts[] = $receiptNo;
            }

            $updateData = [
                'cmplt_qty'               => $newCmpltQty,
                'receipt_qty'             => $newCmpltQty,
                'receipt_from_production' => implode(', ', $existingReceipts),
            ];

            // Jika seluruh kuantitas rencana sudah tercapai
            if ($newCmpltQty >= floatval($pdo->planned_qty) && floatval($pdo->planned_qty) > 0) {
                $updateData['status'] = 'CLOSED';
            }

            $pdo->update($updateData);
        }

        // 3. Tembakkan ke SAP B1 API
        $sapUrl = config('services.sap.url');
        $sapResponse = null;
        try {
            $response = Http::timeout(45)->post("{$sapUrl}/api/addreceiptprod", $payload);
            if ($response->successful()) {
                $body = $response->json();
                if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                    $localReceipt->update([
                        'doc_entry'     => $body['DocEntry'] ?? $body['doc_entry'] ?? null,
                        'doc_num'       => $body['DocNum'] ?? $body['doc_num'] ?? null,
                        'sap_status'    => 'SYNCED',
                        'integrated_at' => now(),
                    ]);
                    $sapResponse = $body;
                } else {
                    $localReceipt->update([
                        'sap_status' => 'FAILED',
                        'sap_error'  => $body['Message'] ?? 'Unknown SAP error',
                    ]);
                }
            }
        } catch (\Exception $ex) {
            $localReceipt->update([
                'sap_status' => 'FAILED',
                'sap_error'  => $ex->getMessage(),
            ]);
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'ADD_RECEIPT_PROD',
                "Receipt from Production {$receiptNo} recorded locally" . ($sapResponse ? " and synced to SAP." : ".")
            );
        }

        return [
            'receipt'      => $localReceipt->fresh(['items', 'productionOrder']),
            'payload'      => $payload,
            'sap_response' => $sapResponse,
        ];
    }

    /**
     * Cancel Inventory Transfer (IT) on SAP (/api/CancelIT).
     *
     * @param array $data
     * @param int|null $userId
     * @return array
     */
    public function cancelItSap(array $data, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        $docEntry = (string) ($data['doc_entry'] ?? $data['DocEntry'] ?? '');
        if (empty($docEntry)) {
            throw new \Exception('DocEntry wajib diisi untuk membatalkan Inventory Transfer (IT).');
        }

        $payload = [
            'DocEntry' => $docEntry,
            'UserId'   => $userId ?? 1,
            'AddonId'  => 2,
        ];

        $response = Http::timeout(30)->post("{$sapUrl}/api/CancelIT", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP CancelIT. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP CancelIT error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'CANCEL_IT_SAP',
                "Cancelled Inventory Transfer (IT) on SAP for DocEntry {$docEntry}."
            );
        }

        return [
            'payload' => $payload,
            'sap_response' => $body,
        ];
    }
}
