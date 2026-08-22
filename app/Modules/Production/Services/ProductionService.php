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
        if ($oldStatus === 'RELEASE') $oldStatus = 'RELEASED';
        $newStatus = isset($data['status']) ? strtoupper(trim((string) $data['status'])) : $oldStatus;
        if ($newStatus === 'RELEASE') $newStatus = 'RELEASED';
        $data['status'] = $newStatus;

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
                    'Bomid'       => (string) ($data['Bomid'] ?? $data['bom_id'] ?? $data['production_bom_id'] ?? $updatedOrder->production_bom_id ?? ''),
                    'UserId'      => (string) ($data['UserId'] ?? $data['user_id'] ?? ($userId ? (string)$userId : '1')),
                    'AddonId'     => (string) ($data['AddonId'] ?? $data['addon_id'] ?? '2'),
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
        $rawStatus = strtoupper(trim((string) ($data['status'] ?? $data['Status'] ?? 'PLANNED')));
        $status = ($rawStatus === 'RELEASE' || $rawStatus === 'RELEASED') ? 'RELEASED' : (empty($rawStatus) ? 'PLANNED' : $rawStatus);

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
        $rawBomId = $data['production_bom_id'] ?? $data['bom_id'] ?? $data['Bomid'] ?? null;
        $bomId = is_numeric($rawBomId) && (int)$rawBomId > 0 ? (int)$rawBomId : null;

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
                'Bomid'       => (string) ($data['Bomid'] ?? $data['bom_id'] ?? $data['production_bom_id'] ?? $bomId ?? ''),
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

        // Normalize status filter if provided (e.g. RELEASE or RELEASED)
        $rawStatusFilter = strtoupper(trim((string) ($filters['status'] ?? $filters['Status'] ?? '')));
        $statusFilter = null;
        if ($rawStatusFilter === 'RELEASE' || $rawStatusFilter === 'RELEASED' || $rawStatusFilter === 'R') {
            $statusFilter = 'RELEASED';
        } elseif ($rawStatusFilter === 'PLAN' || $rawStatusFilter === 'PLANNED' || $rawStatusFilter === 'P') {
            $statusFilter = 'PLANNED';
        } elseif ($rawStatusFilter === 'CLOSE' || $rawStatusFilter === 'CLOSED' || $rawStatusFilter === 'C') {
            $statusFilter = 'CLOSED';
        } elseif ($rawStatusFilter === 'CANCEL' || $rawStatusFilter === 'CANCELLED' || $rawStatusFilter === 'L') {
            $statusFilter = 'CANCELLED';
        } elseif (!empty($rawStatusFilter) && $rawStatusFilter !== 'ALL') {
            $statusFilter = $rawStatusFilter;
        }

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

        // Normalize Status on SAP items & apply status filter if present
        $normalizedItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $s = strtoupper(trim((string) ($item['Status'] ?? '')));
            if ($s === 'R' || $s === 'RELEASE' || $s === 'RELEASED') {
                $item['Status'] = 'RELEASED';
            } elseif ($s === 'P' || $s === 'PLAN' || $s === 'PLANNED') {
                $item['Status'] = 'PLANNED';
            } elseif ($s === 'C' || $s === 'CLOSE' || $s === 'CLOSED') {
                $item['Status'] = 'CLOSED';
            } elseif ($s === 'L' || $s === 'CANCEL' || $s === 'CANCELLED') {
                $item['Status'] = 'CANCELLED';
            }

            // Filter if status filter is active
            if ($statusFilter && $item['Status'] !== $statusFilter) {
                continue;
            }

            $normalizedItems[] = $item;
        }
        $items = $normalizedItems;

        // Fetch and merge local production orders matching the status filter
        try {
            $localQuery = \App\Models\ProductionOrder::query()
                ->with(['parentItem', 'details']);

            if ($statusFilter) {
                $localQuery->where('status', $statusFilter);
            } else {
                $localQuery->where(function ($q) {
                    $q->where('status', 'PLANNED')
                      ->orWhereNull('doc_entry')
                      ->orWhere('sap_status', '!=', 'SYNCED');
                });
            }

            if (!empty($whsCode)) {
                $localQuery->where('warehouse', $whsCode);
            }

            $localOrders = $localQuery->orderBy('id', 'desc')->get();
            $existingDocEntries = array_column($items, 'DocEntry');
            $existingDocNums = array_column($items, 'DocNum');

            $localItems = [];
            foreach ($localOrders as $lOrder) {
                if ($lOrder->doc_entry && in_array((string)$lOrder->doc_entry, $existingDocEntries)) {
                    continue;
                }
                if ($lOrder->prod_order_no && in_array((string)$lOrder->prod_order_no, $existingDocNums)) {
                    continue;
                }

                $localItems[] = [
                    'id'          => $lOrder->id,
                    'DocEntry'    => (string) ($lOrder->doc_entry ?: $lOrder->id),
                    'DocNum'      => (string) ($lOrder->doc_num ?: $lOrder->prod_order_no),
                    'ItemCode'    => (string) $lOrder->item_code,
                    'ProdName'    => (string) ($lOrder->parentItem?->item_name ?? $this->resolveItemName($lOrder->item_code)),
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
     * Always checks SAP to sync status (e.g. PLANNED -> RELEASED or CLOSED) if record exists on SAP.
     *
     * @param string|int $customQuery
     * @param int|null $userId
     * @return array
     */
    public function getPdoById(string|int $customQuery, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        // 1. Check local database first
        $localOrder = null;
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
        } catch (\Exception $e) {
            // DB fallback
        }

        $sapQuery = $localOrder?->doc_entry ?: ($localOrder?->doc_num ?: ($localOrder?->prod_order_no ?: $customQuery));
        $header = null;
        $items = [];
        $result = [];

        // 2. Query SAP API getPDObyId to get real-time status & details
        try {
            $payload = [
                'CustomQuery' => (string) $sapQuery,
            ];

            $response = Http::timeout(25)->post("{$sapUrl}/api/getPDObyId", $payload);

            if ($response->successful()) {
                $body = $response->json();
                if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                    $result = $body['Result'] ?? [];
                    if (isset($result['Table1'])) {
                        $header = $result['Table1'][0] ?? null;
                        $items = $result['Table2'] ?? [];
                    } elseif (isset($result['Header'])) {
                        $header = $result['Header'];
                        $items = $result['Item'] ?? $result['Items'] ?? $result['Lines'] ?? [];
                    } elseif (isset($result['Lines'])) {
                        $header = $result;
                        $items = $result['Lines'];
                    } elseif (is_array($result) && isset($result[0])) {
                        $header = $result[0];
                        $items = $result;
                    }

                    // Check if header is a dummy 0 record
                    if ($header && is_array($header)) {
                        $hDocEntry = (string) ($header['DocEntry'] ?? '');
                        $hDocNum = (string) ($header['DocNum'] ?? '');
                        if (in_array($hDocEntry, ['0', '']) && in_array($hDocNum, ['0', ''])) {
                            $header = null;
                            $items = [];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback to local DB
        }

        // If found in SAP, normalize status and update local DB if applicable
        if ($header && is_array($header)) {
            $rawStatus = strtoupper(trim((string) ($header['Status'] ?? '')));
            if ($rawStatus === 'R' || $rawStatus === 'RELEASE' || $rawStatus === 'RELEASED') {
                $normStatus = 'RELEASED';
            } elseif ($rawStatus === 'P' || $rawStatus === 'PLAN' || $rawStatus === 'PLANNED') {
                $normStatus = 'PLANNED';
            } elseif ($rawStatus === 'C' || $rawStatus === 'CLOSE' || $rawStatus === 'CLOSED') {
                $normStatus = 'CLOSED';
            } elseif ($rawStatus === 'L' || $rawStatus === 'CANCEL' || $rawStatus === 'CANCELLED') {
                $normStatus = 'CANCELLED';
            } else {
                $normStatus = $rawStatus ?: 'RELEASED';
            }
            $header['Status'] = $normStatus;

            // Sync updated status to local database
            if ($localOrder) {
                try {
                    $localOrder->update([
                        'status'     => $normStatus,
                        'doc_entry'  => $header['DocEntry'] ?? $localOrder->doc_entry,
                        'doc_num'    => $header['DocNum'] ?? $localOrder->doc_num,
                        'sap_status' => 'SYNCED',
                        'cmplt_qty'  => floatval($header['CmpltQty'] ?? $localOrder->cmplt_qty),
                        'rjct_qty'   => floatval($header['RjctQty'] ?? $localOrder->rjct_qty),
                    ]);
                } catch (\Exception $e) {
                    // Ignore DB sync error
                }
            }

            // Normalize header ItemCode & ProdName
            $hCode = (string) ($header['ItemCode'] ?? $header['item_code'] ?? $header['item'] ?? $localOrder?->item_code ?? '');
            $hName = (string) ($header['ProdName'] ?? $header['prod_name'] ?? $header['ItemName'] ?? $header['item_name'] ?? $localOrder?->parentItem?->item_name ?? '');
            if ((empty($hName) || $hName === $hCode) && !empty($hCode)) {
                $hName = $this->resolveItemName($hCode);
            }
            $header['ItemCode'] = $hCode;
            $header['ProdName'] = $hName;
            unset($header['item_code'], $header['item'], $header['prod_name'], $header['item_name'], $header['ItemName']);

            // Normalize item lines
            if (!empty($items) && is_array($items)) {
                foreach ($items as &$it) {
                    if (!is_array($it)) continue;
                    $itCode = (string) ($it['ItemCode'] ?? $it['item_code'] ?? $it['item'] ?? '');
                    $itName = (string) ($it['ItemName'] ?? $it['item_name'] ?? $it['ProdName'] ?? $it['prod_name'] ?? $it['Dscription'] ?? $it['dscription'] ?? '');
                    if ((empty($itName) || $itName === $itCode) && !empty($itCode)) {
                        $itName = $this->resolveItemName($itCode);
                    }
                    $it['ItemCode'] = $itCode;
                    $it['ItemName'] = $itName;
                    unset($it['item_code'], $it['item'], $it['item_name'], $it['prod_name'], $it['ProdName'], $it['Dscription'], $it['dscription']);
                }
                unset($it);
            }
        }

        // 3. Fallback to local DB record if SAP data is not found or empty
        if ((empty($header) && empty($items)) && $localOrder) {
            $hCode = (string) $localOrder->item_code;
            $hName = (string) ($localOrder->parentItem?->item_name ?? $this->resolveItemName($hCode));

            $header = [
                'id'          => $localOrder->id,
                'DocEntry'    => (string) ($localOrder->doc_entry ?: $localOrder->id),
                'DocNum'      => (string) ($localOrder->doc_num ?: $localOrder->prod_order_no),
                'Series'      => $localOrder->series ?: 15,
                'ItemCode'    => $hCode,
                'ProdName'    => $hName,
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
                $lCode = (string) $line->item_code;
                $lName = (string) ($line->item?->item_name ?? $this->resolveItemName($lCode));

                $items[] = [
                    'LineNum'     => $line->line_num ?? $idx,
                    'ItemType'    => $line->type === 'Resource' ? 'R' : ($line->type === 'Text' ? 'T' : 'I'),
                    'ItemCode'    => $lCode,
                    'ItemName'    => $lName,
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
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'GET_PDO_DETAIL_SAP',
                "Fetched Production Order detail for query: {$customQuery}."
            );
        }

        return [
            'header' => $header,
            'items'  => $items,
            'raw'    => $result,
        ];
    }

    /**
     * Check and sync status for all local Production Orders that are currently PLANNED against SAP B1.
     *
     * @param int|null $userId
     * @return array
     */
    public function syncPendingPdoStatus(?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');
        $plannedOrders = \App\Models\ProductionOrder::where('status', 'PLANNED')
            ->orWhereNull('doc_entry')
            ->orWhere('sap_status', '!=', 'SYNCED')
            ->get();

        $updatedCount = 0;
        $updatedOrders = [];

        foreach ($plannedOrders as $order) {
            $queryKey = $order->doc_entry ?: ($order->doc_num ?: $order->prod_order_no);
            if (empty($queryKey)) continue;

            try {
                $response = Http::timeout(15)->post("{$sapUrl}/api/getPDObyId", [
                    'CustomQuery' => (string) $queryKey,
                ]);

                if ($response->successful()) {
                    $body = $response->json();
                    if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                        $result = $body['Result'] ?? [];
                        $header = $result['Table1'][0] ?? (is_array($result) && !isset($result['Table1']) ? ($result[0] ?? null) : null);

                        if ($header && is_array($header)) {
                            $hDocEntry = (string) ($header['DocEntry'] ?? '');
                            $hDocNum = (string) ($header['DocNum'] ?? '');
                            if (!in_array($hDocEntry, ['0', '']) && !in_array($hDocNum, ['0', ''])) {
                                $rawStatus = strtoupper(trim((string) ($header['Status'] ?? '')));
                                if ($rawStatus === 'R' || $rawStatus === 'RELEASE' || $rawStatus === 'RELEASED') {
                                    $normStatus = 'RELEASED';
                                } elseif ($rawStatus === 'C' || $rawStatus === 'CLOSE' || $rawStatus === 'CLOSED') {
                                    $normStatus = 'CLOSED';
                                } elseif ($rawStatus === 'L' || $rawStatus === 'CANCEL' || $rawStatus === 'CANCELLED') {
                                    $normStatus = 'CANCELLED';
                                } else {
                                    $normStatus = 'PLANNED';
                                }

                                $order->update([
                                    'status'     => $normStatus,
                                    'doc_entry'  => $header['DocEntry'] ?? $order->doc_entry,
                                    'doc_num'    => $header['DocNum'] ?? $order->doc_num,
                                    'sap_status' => 'SYNCED',
                                    'cmplt_qty'  => floatval($header['CmpltQty'] ?? $order->cmplt_qty),
                                    'rjct_qty'   => floatval($header['RjctQty'] ?? $order->rjct_qty),
                                ]);

                                $updatedCount++;
                                $updatedOrders[] = [
                                    'id'            => $order->id,
                                    'prod_order_no' => $order->prod_order_no,
                                    'doc_entry'     => $header['DocEntry'],
                                    'status'        => $normStatus,
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue to next order
            }
        }

        if ($userId && $updatedCount > 0) {
            $this->auditLogService->log(
                $userId,
                'SYNC_PENDING_PDO_STATUS',
                "Synchronized status for {$updatedCount} pending Production Orders with SAP."
            );
        }

        return [
            'success'       => true,
            'updated_count' => $updatedCount,
            'orders'        => $updatedOrders,
            'message'       => "Sinkronisasi status PDO selesai. {$updatedCount} order diperbarui.",
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

        $items = [];

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/getListReceiptProd", $payload);
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
            // Proceed to local merge
        }

        // Fetch and merge local Production Receipts from Database
        try {
            $localReceiptQuery = \App\Models\ProductionReceipt::query()
                ->with(['items.item', 'productionOrder.parentItem']);

            if (!empty($rawFrom) && !empty($rawTo)) {
                $localReceiptQuery->whereBetween('doc_date', [$rawFrom, $rawTo]);
            }

            $localReceipts = $localReceiptQuery->orderBy('id', 'desc')->get();
            $existingDocEntries = array_column($items, 'DocEntry');
            $existingDocNums = array_column($items, 'DocNum');

            $localItems = [];
            foreach ($localReceipts as $lReceipt) {
                if ($lReceipt->doc_entry && in_array((string)$lReceipt->doc_entry, $existingDocEntries)) {
                    continue;
                }
                if ($lReceipt->doc_num && in_array((string)$lReceipt->doc_num, $existingDocNums)) {
                    continue;
                }

                $firstItem = $lReceipt->items->first();
                $totalQty = $lReceipt->items->sum('quantity');
                $itemCode = (string) ($firstItem?->item_code ?: $lReceipt->productionOrder?->item_code ?: '');
                $itemName = (string) ($firstItem?->item?->item_name ?: $lReceipt->productionOrder?->parentItem?->item_name ?: '');
                if (empty($itemName) || $itemName === $itemCode) {
                    $itemName = $this->resolveItemName($itemCode);
                }

                $localItems[] = [
                    'id'          => $lReceipt->id,
                    'DocEntry'    => (string) ($lReceipt->doc_entry ?: $lReceipt->id),
                    'DocNum'      => (string) ($lReceipt->doc_num ?: $lReceipt->receipt_no),
                    'DocDate'     => $lReceipt->doc_date ? date('Y-m-d\TH:i:s', strtotime($lReceipt->doc_date)) : null,
                    'DocDueDate'  => $lReceipt->doc_due_date ? date('Y-m-d\TH:i:s', strtotime($lReceipt->doc_due_date)) : null,
                    'Comments'    => (string) $lReceipt->comments,
                    'Shift'       => (string) $lReceipt->u_shift,
                    'Unit'        => (string) $lReceipt->u_unit,
                    'Status'      => (string) $lReceipt->status,
                    'SapStatus'   => (string) $lReceipt->sap_status,
                    'BaseEntry'   => (string) ($lReceipt->productionOrder?->doc_entry ?: $lReceipt->productionOrder?->id ?: $firstItem?->base_entry ?: ''),
                    'BaseType'    => 202,
                    'ItemCode'    => $itemCode,
                    'ItemName'    => $itemName,
                    'Quantity'    => floatval($totalQty),
                    'WhsCode'     => (string) ($firstItem?->warehouse ?: ''),
                    'is_local'    => true,
                ];
            }

            $items = array_merge($localItems, $items);
        } catch (\Exception $e) {
            // Fallback
        }

        // Enrich any missing ItemCode/ItemName in list items
        if (!empty($items)) {
            foreach ($items as &$it) {
                if (!is_array($it)) continue;
                $c = (string) ($it['ItemCode'] ?? $it['item_code'] ?? $it['item'] ?? $it['Code'] ?? $it['code'] ?? '');
                $n = (string) ($it['ItemName'] ?? $it['item_name'] ?? $it['ProdName'] ?? $it['prod_name'] ?? $it['Dscription'] ?? $it['dscription'] ?? $it['ItemDescription'] ?? $it['item_description'] ?? $it['Description'] ?? '');
                
                if ((empty($n) || $n === $c) && !empty($c)) {
                    $n = $this->resolveItemName($c);
                }

                $it['ItemCode'] = $c;
                $it['ItemName'] = $n;

                // Clean duplicate keys
                unset($it['item_code'], $it['item'], $it['code'], $it['item_name'], $it['prod_name'], $it['ProdName'], $it['Dscription'], $it['dscription'], $it['ItemDescription'], $it['item_description'], $it['quantity'], $it['whs_code'], $it['warehouse']);
            }
            unset($it);
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'GET_LIST_RECEIPT_PROD_SAP',
                "Fetched Production Receipts list from SAP & Local DB (From: {$fromFormatted}, To: {$toFormatted})."
            );
        }

        return [
            'filters' => $payload,
            'items'   => $items,
        ];
    }

    /**
     * Get detail of Production Receipt from SAP API endpoint (/api/getReceiptProdbyId) or Local DB.
     *
     * @param string|int $customQuery
     * @param int|null $userId
     * @return array
     */
    public function getReceiptProdById(string|int $customQuery, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        // 1. Check local database first
        $localReceipt = null;
        try {
            $localReceipt = \App\Models\ProductionReceipt::with(['items.item', 'productionOrder.parentItem'])
                ->where('id', is_numeric($customQuery) ? (int)$customQuery : 0)
                ->orWhere('receipt_no', (string)$customQuery)
                ->orWhere('doc_entry', is_numeric($customQuery) ? (int)$customQuery : 0)
                ->orWhere('doc_num', (string)$customQuery)
                ->first();
        } catch (\Exception $e) {
            // DB fallback
        }

        $sapQuery = $localReceipt?->doc_entry ?: $customQuery;
        $header = null;
        $items = [];
        $result = [];

        // 2. Try querying SAP if SAP query identifier is available
        try {
            $payload = [
                'CustomQuery' => (string) $sapQuery,
            ];

            $response = Http::timeout(30)->post("{$sapUrl}/api/getReceiptProdbyId", $payload);

            if ($response->successful()) {
                $body = $response->json();
                if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                    $result = $body['Result'] ?? [];
                    if (isset($result['Table1'])) {
                        $header = $result['Table1'][0] ?? null;
                        $items = $result['Table2'] ?? [];
                    } elseif (isset($result['Header'])) {
                        $header = $result['Header'];
                        $items = $result['Item'] ?? $result['Items'] ?? $result['Lines'] ?? [];
                    } elseif (isset($result['Lines'])) {
                        $header = $result;
                        $items = $result['Lines'];
                    } elseif (is_array($result) && isset($result[0])) {
                        $header = $result[0];
                        $items = $result;
                    }

                    // Check if header is a dummy 0 record
                    if ($header && is_array($header)) {
                        $hDocEntry = (string) ($header['DocEntry'] ?? '');
                        $hDocNum = (string) ($header['DocNum'] ?? '');
                        if (in_array($hDocEntry, ['0', '']) && in_array($hDocNum, ['0', ''])) {
                            $header = null;
                            $items = [];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Proceed to local fallback
        }

        // 3. If SAP did not return valid data, fallback to local DB record
        if ((empty($header) && empty($items)) && $localReceipt) {
            $firstItem = $localReceipt->items->first();
            $itemCode = (string) ($firstItem?->item_code ?: $localReceipt->productionOrder?->item_code ?: '');
            $itemName = (string) ($firstItem?->item?->item_name ?: $localReceipt->productionOrder?->parentItem?->item_name ?: '');
            if (empty($itemName) || $itemName === $itemCode) {
                $itemName = $this->resolveItemName($itemCode);
            }

            $header = [
                'id'          => $localReceipt->id,
                'DocEntry'    => (string) ($localReceipt->doc_entry ?: $localReceipt->id),
                'DocNum'      => (string) ($localReceipt->doc_num ?: $localReceipt->receipt_no),
                'DocDate'     => $localReceipt->doc_date ? date('Y-m-d\TH:i:s', strtotime($localReceipt->doc_date)) : null,
                'DocDueDate'  => $localReceipt->doc_due_date ? date('Y-m-d\TH:i:s', strtotime($localReceipt->doc_due_date)) : null,
                'Comments'    => (string) $localReceipt->comments,
                'Shift'       => (string) $localReceipt->u_shift,
                'Unit'        => (string) $localReceipt->u_unit,
                'Status'      => (string) $localReceipt->status,
                'SapStatus'   => (string) $localReceipt->sap_status,
                'BaseEntry'   => (string) ($localReceipt->productionOrder?->doc_entry ?: $localReceipt->productionOrder?->id ?: $firstItem?->base_entry ?: ''),
                'BaseType'    => 202,
                'ItemCode'    => $itemCode,
                'ItemName'    => $itemName,
                'Quantity'    => floatval($localReceipt->items->sum('quantity')),
                'WhsCode'     => (string) ($firstItem?->warehouse ?: ''),
                'is_local'    => true,
            ];

            $items = [];
            foreach ($localReceipt->items as $idx => $line) {
                $lCode = (string) $line->item_code;
                $lName = (string) ($line->item?->item_name ?: '');
                if (empty($lName) || $lName === $lCode) {
                    $lName = $this->resolveItemName($lCode);
                }

                $items[] = [
                    'LineNum'   => $line->line_num ?? $idx,
                    'BaseType'  => $line->base_type ?? 202,
                    'BaseEntry' => (string) $line->base_entry,
                    'BaseLine'  => (string) $line->base_line,
                    'ItemCode'  => $lCode,
                    'ItemName'  => $lName,
                    'Quantity'  => floatval($line->quantity),
                    'WhsCode'   => (string) $line->warehouse,
                    'UoMEntry'  => (string) $line->uom_entry,
                    'OcrCode'   => (string) $line->ocr_code,
                    'OcrCode2'  => (string) $line->ocr_code2,
                    'OcrCode3'  => (string) $line->ocr_code3,
                ];
            }
        }

        // Normalize and enrich Items
        if (!empty($items) && is_array($items)) {
            $normalizedItems = [];
            foreach ($items as $idx => $it) {
                if (!is_array($it)) continue;
                $c = (string) ($it['ItemCode'] ?? $it['item_code'] ?? $it['item'] ?? $it['Code'] ?? $it['code'] ?? '');
                if (empty($c) && !empty($it['BaseEntry'])) {
                    try {
                        $pdoDetail = $this->getPdoById($it['BaseEntry']);
                        $c = (string) ($pdoDetail['header']['ItemCode'] ?? '');
                    } catch (\Exception $e) {}
                }
                if (empty($c) && $header) {
                    $c = (string) ($header['ItemCode'] ?? $header['item_code'] ?? '');
                }

                $n = (string) ($it['ItemName'] ?? $it['item_name'] ?? $it['ProdName'] ?? $it['prod_name'] ?? $it['Dscription'] ?? $it['dscription'] ?? $it['ItemDescription'] ?? $it['item_description'] ?? $it['Description'] ?? '');

                if ((empty($n) || $n === $c) && !empty($c)) {
                    $n = $this->resolveItemName($c);
                }

                $it['ItemCode'] = $c;
                $it['ItemName'] = $n;
                $it['Quantity'] = isset($it['Quantity']) ? floatval($it['Quantity']) : (isset($it['quantity']) ? floatval($it['quantity']) : 0.0);
                $it['WhsCode'] = (string) ($it['WhsCode'] ?? $it['whs_code'] ?? $it['warehouse'] ?? '');

                // Clean duplicate keys
                unset($it['item_code'], $it['item'], $it['code'], $it['item_name'], $it['prod_name'], $it['ProdName'], $it['Dscription'], $it['dscription'], $it['ItemDescription'], $it['item_description'], $it['quantity'], $it['whs_code'], $it['warehouse']);

                $normalizedItems[] = $it;
            }
            $items = $normalizedItems;
        }

        // Normalize and enrich Header
        if ($header && is_array($header)) {
            $hCode = (string) ($header['ItemCode'] ?? $header['item_code'] ?? $header['item'] ?? ($items[0]['ItemCode'] ?? ''));
            $hName = (string) ($header['ItemName'] ?? $header['item_name'] ?? $header['ProdName'] ?? ($items[0]['ItemName'] ?? ''));

            if ((empty($hName) || $hName === $hCode) && !empty($hCode)) {
                $hName = $this->resolveItemName($hCode);
            }

            $header['ItemCode'] = $hCode;
            $header['ItemName'] = $hName;

            // Clean duplicate keys
            unset($header['item_code'], $header['item'], $header['code'], $header['item_name'], $header['prod_name'], $header['ProdName'], $header['Dscription'], $header['dscription'], $header['ItemDescription'], $header['item_description'], $header['quantity'], $header['whs_code'], $header['warehouse']);
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'GET_RECEIPT_PROD_DETAIL_SAP',
                "Fetched Production Receipt detail for query: {$customQuery}."
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

        $items = [];

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/getListIssueProd", $payload);
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
            // Proceed to local merge
        }

        // Fetch and merge local Production Issues from Database
        try {
            $localIssueQuery = \App\Models\ProductionIssue::query()
                ->with(['items.item', 'productionOrder.parentItem']);

            if (!empty($rawFrom) && !empty($rawTo)) {
                $localIssueQuery->whereBetween('doc_date', [$rawFrom, $rawTo]);
            }

            $localIssues = $localIssueQuery->orderBy('id', 'desc')->get();
            $existingDocEntries = array_column($items, 'DocEntry');
            $existingDocNums = array_column($items, 'DocNum');

            $localItems = [];
            foreach ($localIssues as $lIssue) {
                if ($lIssue->doc_entry && in_array((string)$lIssue->doc_entry, $existingDocEntries)) {
                    continue;
                }
                if ($lIssue->doc_num && in_array((string)$lIssue->doc_num, $existingDocNums)) {
                    continue;
                }

                $firstItem = $lIssue->items->first();
                $totalQty = $lIssue->items->sum('quantity');
                $itemCode = (string) ($firstItem?->item_code ?: $lIssue->productionOrder?->item_code ?: '');
                $itemName = (string) ($firstItem?->item?->item_name ?: $lIssue->productionOrder?->parentItem?->item_name ?: '');
                if (empty($itemName) || $itemName === $itemCode) {
                    $itemName = $this->resolveItemName($itemCode);
                }

                $localItems[] = [
                    'id'          => $lIssue->id,
                    'DocEntry'    => (string) ($lIssue->doc_entry ?: $lIssue->id),
                    'DocNum'      => (string) ($lIssue->doc_num ?: $lIssue->issue_no),
                    'DocDate'     => $lIssue->doc_date ? date('Y-m-d\TH:i:s', strtotime($lIssue->doc_date)) : null,
                    'DocDueDate'  => $lIssue->doc_due_date ? date('Y-m-d\TH:i:s', strtotime($lIssue->doc_due_date)) : null,
                    'Comments'    => (string) $lIssue->comments,
                    'Shift'       => (string) $lIssue->u_shift,
                    'Unit'        => (string) $lIssue->u_unit,
                    'Status'      => (string) $lIssue->status,
                    'SapStatus'   => (string) $lIssue->sap_status,
                    'BaseEntry'   => (string) ($lIssue->productionOrder?->doc_entry ?: $lIssue->productionOrder?->id ?: $firstItem?->base_entry ?: ''),
                    'BaseType'    => 202,
                    'ItemCode'    => $itemCode,
                    'ItemName'    => $itemName,
                    'Quantity'    => floatval($totalQty),
                    'WhsCode'     => (string) ($firstItem?->warehouse ?: ''),
                    'is_local'    => true,
                ];
            }

            $items = array_merge($localItems, $items);
        } catch (\Exception $e) {
            // Fallback
        }

        // Enrich any missing ItemCode/ItemName in list items
        if (!empty($items)) {
            foreach ($items as &$it) {
                if (!is_array($it)) continue;
                $c = (string) ($it['ItemCode'] ?? $it['item_code'] ?? $it['item'] ?? $it['Code'] ?? $it['code'] ?? '');
                $n = (string) ($it['ItemName'] ?? $it['item_name'] ?? $it['ProdName'] ?? $it['prod_name'] ?? $it['Dscription'] ?? $it['dscription'] ?? $it['ItemDescription'] ?? $it['item_description'] ?? $it['Description'] ?? '');

                if ((empty($n) || $n === $c) && !empty($c)) {
                    $n = $this->resolveItemName($c);
                }

                $it['ItemCode'] = $c;
                $it['ItemName'] = $n;

                // Clean duplicate keys
                unset($it['item_code'], $it['item'], $it['code'], $it['item_name'], $it['prod_name'], $it['ProdName'], $it['Dscription'], $it['dscription'], $it['ItemDescription'], $it['item_description'], $it['quantity'], $it['whs_code'], $it['warehouse']);
            }
            unset($it);
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
     * Get detail of Issue for Production from SAP API endpoint (/api/getIssueProdbyId) or Local DB.
     *
     * @param string|int $customQuery
     * @param int|null $userId
     * @return array
     */
    public function getIssueProdById(string|int $customQuery, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        // 1. Check local database first
        $localIssue = null;
        try {
            $localIssue = \App\Models\ProductionIssue::with(['items.item', 'productionOrder.parentItem'])
                ->where('id', is_numeric($customQuery) ? (int)$customQuery : 0)
                ->orWhere('issue_no', (string)$customQuery)
                ->orWhere('doc_entry', is_numeric($customQuery) ? (int)$customQuery : 0)
                ->orWhere('doc_num', (string)$customQuery)
                ->first();
        } catch (\Exception $e) {
            // DB fallback
        }

        $sapQuery = $localIssue?->doc_entry ?: $customQuery;
        $header = null;
        $items = [];
        $result = [];

        // 2. Try querying SAP
        try {
            $payload = [
                'CustomQuery' => (string) $sapQuery,
            ];

            $response = Http::timeout(30)->post("{$sapUrl}/api/getIssueProdbyId", $payload);

            if ($response->successful()) {
                $body = $response->json();
                if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                    $result = $body['Result'] ?? [];
                    if (isset($result['Table1'])) {
                        $header = $result['Table1'][0] ?? null;
                        $items = $result['Table2'] ?? [];
                    } elseif (isset($result['Header'])) {
                        $header = $result['Header'];
                        $items = $result['Item'] ?? $result['Items'] ?? $result['Lines'] ?? [];
                    } elseif (isset($result['Lines'])) {
                        $header = $result;
                        $items = $result['Lines'];
                    } elseif (is_array($result) && isset($result[0])) {
                        $header = $result[0];
                        $items = $result;
                    }

                    // Check if header is a dummy 0 record
                    if ($header && is_array($header)) {
                        $hDocEntry = (string) ($header['DocEntry'] ?? '');
                        $hDocNum = (string) ($header['DocNum'] ?? '');
                        if (in_array($hDocEntry, ['0', '']) && in_array($hDocNum, ['0', ''])) {
                            $header = null;
                            $items = [];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Proceed to local fallback
        }

        // 3. Fallback to local DB record if SAP data not found
        if ((empty($header) && empty($items)) && $localIssue) {
            $firstItem = $localIssue->items->first();
            $itemCode = (string) ($firstItem?->item_code ?: $localIssue->productionOrder?->item_code ?: '');
            $itemName = (string) ($firstItem?->item?->item_name ?: $localIssue->productionOrder?->parentItem?->item_name ?: '');
            if (empty($itemName) || $itemName === $itemCode) {
                $itemName = $this->resolveItemName($itemCode);
            }

            $header = [
                'id'          => $localIssue->id,
                'DocEntry'    => (string) ($localIssue->doc_entry ?: $localIssue->id),
                'DocNum'      => (string) ($localIssue->doc_num ?: $localIssue->issue_no),
                'DocDate'     => $localIssue->doc_date ? date('Y-m-d\TH:i:s', strtotime($localIssue->doc_date)) : null,
                'DocDueDate'  => $localIssue->doc_due_date ? date('Y-m-d\TH:i:s', strtotime($localIssue->doc_due_date)) : null,
                'Comments'    => (string) $localIssue->comments,
                'Shift'       => (string) $localIssue->u_shift,
                'Unit'        => (string) $localIssue->u_unit,
                'Status'      => (string) $localIssue->status,
                'SapStatus'   => (string) $localIssue->sap_status,
                'BaseEntry'   => (string) ($localIssue->productionOrder?->doc_entry ?: $localIssue->productionOrder?->id ?: $firstItem?->base_entry ?: ''),
                'BaseType'    => 202,
                'ItemCode'    => $itemCode,
                'ItemName'    => $itemName,
                'Quantity'    => floatval($localIssue->items->sum('quantity')),
                'WhsCode'     => (string) ($firstItem?->warehouse ?: ''),
                'is_local'    => true,
            ];

            $items = [];
            foreach ($localIssue->items as $idx => $line) {
                $lCode = (string) $line->item_code;
                $lName = (string) ($line->item?->item_name ?: '');
                if (empty($lName) || $lName === $lCode) {
                    $lName = $this->resolveItemName($lCode);
                }

                $items[] = [
                    'LineNum'   => $line->line_num ?? $idx,
                    'BaseType'  => $line->base_type ?? 202,
                    'BaseEntry' => (string) $line->base_entry,
                    'BaseLine'  => (string) $line->base_line,
                    'ItemCode'  => $lCode,
                    'ItemName'  => $lName,
                    'Quantity'  => floatval($line->quantity),
                    'WhsCode'   => (string) $line->warehouse,
                    'UoMEntry'  => (string) $line->uom_entry,
                    'OcrCode'   => (string) $line->ocr_code,
                    'OcrCode2'  => (string) $line->ocr_code2,
                    'OcrCode3'  => (string) $line->ocr_code3,
                ];
            }
        }

        // Normalize and enrich Items
        if (!empty($items) && is_array($items)) {
            $normalizedItems = [];
            foreach ($items as $idx => $it) {
                if (!is_array($it)) continue;
                $c = (string) ($it['ItemCode'] ?? $it['item_code'] ?? $it['item'] ?? $it['Code'] ?? $it['code'] ?? '');
                if (empty($c) && !empty($it['BaseEntry'])) {
                    try {
                        $pdoDetail = $this->getPdoById($it['BaseEntry']);
                        $sapLines = $pdoDetail['items'] ?? [];
                        foreach ($sapLines as $sLine) {
                            if ((string)($sLine['LineNum'] ?? '') === (string)($it['BaseLine'] ?? ($it['line_num'] ?? ''))) {
                                $c = (string) ($sLine['ItemCode'] ?? '');
                                break;
                            }
                        }
                    } catch (\Exception $e) {}
                }

                $n = (string) ($it['ItemName'] ?? $it['item_name'] ?? $it['ProdName'] ?? $it['prod_name'] ?? $it['Dscription'] ?? $it['dscription'] ?? $it['ItemDescription'] ?? $it['item_description'] ?? $it['Description'] ?? '');

                if ((empty($n) || $n === $c) && !empty($c)) {
                    $n = $this->resolveItemName($c);
                }

                $it['ItemCode'] = $c;
                $it['ItemName'] = $n;
                $it['Quantity'] = isset($it['Quantity']) ? floatval($it['Quantity']) : (isset($it['quantity']) ? floatval($it['quantity']) : 0.0);
                $it['WhsCode'] = (string) ($it['WhsCode'] ?? $it['whs_code'] ?? $it['warehouse'] ?? '');

                // Clean duplicate keys
                unset($it['item_code'], $it['item'], $it['code'], $it['item_name'], $it['prod_name'], $it['ProdName'], $it['Dscription'], $it['dscription'], $it['ItemDescription'], $it['item_description'], $it['quantity'], $it['whs_code'], $it['warehouse']);

                $normalizedItems[] = $it;
            }
            $items = $normalizedItems;
        }

        // Normalize and enrich Header
        if ($header && is_array($header)) {
            $hCode = (string) ($header['ItemCode'] ?? $header['item_code'] ?? $header['item'] ?? ($items[0]['ItemCode'] ?? ''));
            $hName = (string) ($header['ItemName'] ?? $header['item_name'] ?? $header['ProdName'] ?? ($items[0]['ItemName'] ?? ''));

            if ((empty($hName) || $hName === $hCode) && !empty($hCode)) {
                $hName = $this->resolveItemName($hCode);
            }

            $header['ItemCode'] = $hCode;
            $header['ItemName'] = $hName;

            // Clean duplicate keys
            unset($header['item_code'], $header['item'], $header['code'], $header['item_name'], $header['prod_name'], $header['ProdName'], $header['Dscription'], $header['dscription'], $header['ItemDescription'], $header['item_description'], $header['quantity'], $header['whs_code'], $header['warehouse']);
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'GET_ISSUE_PROD_DETAIL_SAP',
                "Fetched Issue for Production detail for query: {$customQuery}."
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

        try {
            \App\Models\ProductionOrder::where('doc_entry', (int) $docEntry)
                ->orWhere('doc_num', (string) $docEntry)
                ->update(['status' => 'CANCELLED']);
        } catch (\Exception $e) {
            // DB fallback
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
     * Close Production Order (PDO) on SAP (/api/closepdo).
     *
     * @param array $data
     * @param int|null $userId
     * @return array
     */
    public function closePdoSap(array $data, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        $docEntry = (string) ($data['doc_entry'] ?? $data['DocEntry'] ?? '');
        if (empty($docEntry)) {
            throw new \Exception('DocEntry wajib diisi untuk menutup PDO.');
        }

        $payload = [
            'DocEntry' => $docEntry,
            'UserId'   => $userId ?? 1,
            'AddonId'  => 2,
        ];

        $response = Http::timeout(30)->post("{$sapUrl}/api/closepdo", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP closepdo. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP closepdo error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        try {
            \App\Models\ProductionOrder::where('doc_entry', (int) $docEntry)
                ->orWhere('doc_num', (string) $docEntry)
                ->update(['status' => 'CLOSED']);
        } catch (\Exception $e) {
            // DB fallback
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'CLOSE_PDO_SAP',
                "Closed Production Order (PDO) on SAP for DocEntry {$docEntry}."
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
            $rawDocDueDate = $rawDocDate;
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
            $baseLine = $line['base_line'] ?? $line['BaseLine'] ?? 0;
            $quantity = $line['quantity'] ?? $line['Quantity'] ?? null;

            if ($baseEntry === null || $baseEntry === '') {
                throw new \Exception("Lines index [{$idx}]: 'BaseEntry' (DocEntry Production Order) wajib diisi.");
            }
            if ($quantity === null || !is_numeric($quantity) || floatval($quantity) <= 0) {
                throw new \Exception("Lines index [{$idx}]: 'Quantity' wajib diisi dengan nilai lebih dari 0.");
            }

            $itemCode = (string) ($line['item_code'] ?? $line['ItemCode'] ?? $line['item'] ?? $line['product'] ?? $line['code'] ?? '');
            if (is_array($line['item'] ?? null)) {
                $itemCode = (string) ($line['item']['value'] ?? $line['item']['code'] ?? $line['item']['item_code'] ?? $itemCode);
            }

            $lines[] = [
                'BaseType'  => is_numeric($line['base_type'] ?? $line['BaseType'] ?? null) ? (int) ($line['base_type'] ?? $line['BaseType']) : 202,
                'BaseEntry' => is_numeric($baseEntry) ? (int) $baseEntry : (string) $baseEntry,
                'BaseLine'  => is_numeric($baseLine) ? (int) $baseLine : (string) $baseLine,
                'ItemCode'  => $itemCode,
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

            // Find matching PDO raw material component item
            $pdoItem = null;
            $itemCode = (string) ($line['ItemCode'] ?? '');

            if ($pdo) {
                $pdoItem = $pdo->details()->where('line_num', is_numeric($baseLine) ? (int)$baseLine : 0)->first();
                if (!$pdoItem && !empty($itemCode)) {
                    $pdoItem = $pdo->details()->where('item_code', $itemCode)->first();
                }
                if (empty($itemCode) && $pdoItem) {
                    $itemCode = (string) $pdoItem->item_code;
                }
            }

            // If still empty and PDO is in SAP, resolve from getPdoById
            if (empty($itemCode) && !empty($line['BaseEntry'])) {
                try {
                    $pdoDetail = $this->getPdoById($line['BaseEntry']);
                    $sapLines = $pdoDetail['items'] ?? [];
                    foreach ($sapLines as $sLine) {
                        if ((string)($sLine['LineNum'] ?? '') === (string)$baseLine) {
                            $itemCode = (string) ($sLine['ItemCode'] ?? $sLine['item_code'] ?? '');
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    // ignore
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
                'item_code'                => $itemCode ?: ($pdoItem?->item_code ?? null),
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
        $sapPayload = [
            'DocDate'    => $payload['DocDate'],
            'DocDueDate' => $payload['DocDueDate'],
            'Comments'   => $payload['Comments'],
            'Shift'      => $payload['Shift'],
            'Unit'       => $payload['Unit'],
            'AddonId'    => $payload['AddonId'],
            'UserId'     => $payload['UserId'],
            'Lines'      => array_map(function ($l) {
                return [
                    'BaseType'  => (int) ($l['BaseType'] ?? 202),
                    'BaseEntry' => is_numeric($l['BaseEntry']) ? (int)$l['BaseEntry'] : $l['BaseEntry'],
                    'BaseLine'  => is_numeric($l['BaseLine']) ? (int)$l['BaseLine'] : 0,
                    'ItemCode'  => (string) ($l['ItemCode'] ?? ''),
                    'Quantity'  => floatval($l['Quantity']),
                    'WhsCode'   => (string) ($l['WhsCode'] ?? ''),
                    'UoMEntry'  => is_numeric($l['UoMEntry'] ?? 1) ? (int)($l['UoMEntry'] ?? 1) : 1,
                    'OcrCode'   => (string) ($l['OcrCode'] ?? ''),
                    'OcrCode2'  => (string) ($l['OcrCode2'] ?? ''),
                    'OcrCode3'  => (string) ($l['OcrCode3'] ?? ''),
                ];
            }, $payload['Lines']),
        ];

        try {
            $response = Http::timeout(45)->post("{$sapUrl}/api/addissueprod", $sapPayload);
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

            // Resolve Item Code: For Receipt from Production, it is the Product (Finished Good) of the PDO
            $itemCode = (string) ($line['ItemCode'] ?? '');
            if (empty($itemCode)) {
                $itemCode = (string) ($pdo?->item_code ?? '');
            }
            if (empty($itemCode) && !empty($line['BaseEntry'])) {
                try {
                    $pdoDetail = $this->getPdoById($line['BaseEntry']);
                    $itemCode = (string) ($pdoDetail['header']['ItemCode'] ?? $pdoDetail['header']['item_code'] ?? '');
                } catch (\Exception $e) {
                    // ignore
                }
            }

            \App\Models\ProductionReceiptItem::create([
                'production_receipt_id' => $localReceipt->id,
                'production_order_id'   => $pdo?->id,
                'line_num'              => $idx,
                'base_type'             => $line['BaseType'] ?? 202,
                'base_entry'            => (string)$line['BaseEntry'],
                'base_line'             => isset($line['BaseLine']) ? (string)$line['BaseLine'] : '0',
                'item_code'             => $itemCode ?: ($pdo?->item_code ?? null),
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
        $sapPayload = [
            'DocDate'    => $payload['DocDate'],
            'DocDueDate' => $payload['DocDueDate'],
            'Comments'   => $payload['Comments'],
            'Shift'      => $payload['Shift'],
            'Unit'       => $payload['Unit'],
            'AddonId'    => $payload['AddonId'],
            'UserId'     => $payload['UserId'],
            'Lines'      => array_map(function ($l) {
                return [
                    'BaseType'  => (int) ($l['BaseType'] ?? 202),
                    'BaseEntry' => is_numeric($l['BaseEntry']) ? (int)$l['BaseEntry'] : $l['BaseEntry'],
                    'BaseLine'  => is_numeric($l['BaseLine']) ? (int)$l['BaseLine'] : 0,
                    'ItemCode'  => (string) ($l['ItemCode'] ?? ''),
                    'Quantity'  => floatval($l['Quantity']),
                    'WhsCode'   => (string) ($l['WhsCode'] ?? ''),
                    'UoMEntry'  => is_numeric($l['UoMEntry'] ?? 1) ? (int)($l['UoMEntry'] ?? 1) : 1,
                    'OcrCode'   => (string) ($l['OcrCode'] ?? ''),
                    'OcrCode2'  => (string) ($l['OcrCode2'] ?? ''),
                    'OcrCode3'  => (string) ($l['OcrCode3'] ?? ''),
                ];
            }, $payload['Lines']),
        ];

        try {
            $response = Http::timeout(45)->post("{$sapUrl}/api/addreceiptprod", $sapPayload);
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

    /**
     * Get list of Units from SAP API endpoint (/api/GetUnit).
     *
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function getUnits(?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/GetUnit");
            if (!$response->successful()) {
                $response = Http::timeout(30)->get("{$sapUrl}/api/GetUnit");
            }

            if (!$response->successful()) {
                throw new \Exception('Gagal menghubungi API SAP GetUnit. HTTP Status: ' . $response->status());
            }

            $body = $response->json();

            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                throw new \Exception('API SAP GetUnit error: ' . ($body['Message'] ?? 'Unknown SAP error'));
            }

            $rawUnits = $body['Result'] ?? [];
            $units = [];
            if (is_array($rawUnits)) {
                foreach ($rawUnits as $u) {
                    if (is_array($u)) {
                        $code = (string) ($u['Code'] ?? $u['code'] ?? '');
                        $name = (string) ($u['Name'] ?? $u['name'] ?? $code);
                        if (!empty($code)) {
                            $units[] = [
                                'code' => $code,
                                'name' => $name,
                                'Code' => $code,
                                'Name' => $name,
                            ];
                        }
                    }
                }
            }

            if ($userId) {
                $this->auditLogService->log(
                    $userId,
                    'GET_UNITS_SAP',
                    "Fetched Master Units list from SAP."
                );
            }

            return $units;
        } catch (\Exception $e) {
            throw new \Exception('Gagal mengambil data Master Unit dari SAP: ' . $e->getMessage());
        }
    }

    /**
     * Helper to resolve item name from Master Items or Production Items tables.
     *
     * @param string|null $itemCode
     * @return string
     */
    public function resolveItemName(?string $itemCode): string
    {
        if (empty($itemCode)) {
            return '';
        }

        try {
            $name = \App\Models\Item::where('item_code', $itemCode)->value('item_name');
            if (!empty($name)) {
                return (string) $name;
            }
        } catch (\Exception $e) {
            // ignore
        }

        try {
            $name = \App\Models\ProductionItem::where('item_code', $itemCode)->value('item_name');
            if (!empty($name)) {
                return (string) $name;
            }
        } catch (\Exception $e) {
            // ignore
        }

        return (string) $itemCode;
    }
}
