<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Repositories\ProductionRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
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

                $response = Http::retry(3, 1000)->timeout(45)->post("{$sapUrl}/api/addpdo", $sapPayload);
                if ($response->successful()) {
                    $body = $response->json();
                    if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                        $message = (string) ($body['Message'] ?? $body['message'] ?? '');
                        $sapDocNum = null;
                        $sapDocEntry = null;

                        // 1. Prioritize explicit Message patterns from SAP
                        if (!empty($message)) {
                            // Pattern 0 (HIGHEST PRIORITY): "DocNum: 260910013 - 6717" or "DocNum: 260910013 - 6717)"
                            // Actual SAP middleware format — no "DocEntry" keyword in string!
                            if (preg_match('/DocNum\s*[:=]\s*([0-9]+)\s*-\s*([0-9]+)/i', $message, $matches)) {
                                $sapDocNum = $matches[1];
                                $sapDocEntry = $matches[2];
                            }
                            // Pattern 1: "DocNum - DocEntry : 260910013 - 6717"
                            elseif (preg_match('/DocNum\s*-\s*DocEntry\s*[:=]?\s*([0-9]+)\s*-\s*([0-9]+)/i', $message, $matches)) {
                                $sapDocNum = $matches[1];
                                $sapDocEntry = $matches[2];
                            }
                            // Pattern 2: "DocNum: 260910013 - DocEntry: 6717" or "DocNum: 260910013, DocEntry: 6717"
                            elseif (preg_match('/DocNum\s*[:=]\s*([0-9]+).*?DocEntry\s*[:=]\s*([0-9]+)/i', $message, $matches)) {
                                $sapDocNum = $matches[1];
                                $sapDocEntry = $matches[2];
                            }
                            // Pattern 3: "DocEntry: 6717 - DocNum: 260910013"
                            elseif (preg_match('/DocEntry\s*[:=]\s*([0-9]+).*?DocNum\s*[:=]\s*([0-9]+)/i', $message, $matches)) {
                                $sapDocEntry = $matches[1];
                                $sapDocNum = $matches[2];
                            }
                        }

                        // 2. Fallback to JSON fields if not matched from Message
                        if (empty($sapDocNum)) {
                            $sapDocNum = $body['DocNum'] ?? $body['doc_num'] ?? $body['Result']['DocNum'] ?? null;
                            if (is_string($sapDocNum) && str_contains($sapDocNum, '-')) {
                                $parts = array_map('trim', explode('-', $sapDocNum));
                                if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                                    $sapDocNum = $parts[0];
                                    if (empty($sapDocEntry)) {
                                        $sapDocEntry = $parts[1];
                                    }
                                }
                            }
                        }

                        if (empty($sapDocEntry)) {
                            $sapDocEntry = $body['DocEntry'] ?? $body['doc_entry'] ?? $body['Result']['DocEntry'] ?? null;
                        }

                        // 3. Fallback regex for individual labels in message
                        if (!empty($message)) {
                            if (empty($sapDocNum) && preg_match('/DocNum\s*[:=]\s*([0-9]+)/i', $message, $matches)) {
                                $sapDocNum = $matches[1];
                            }
                            if (empty($sapDocEntry) && preg_match('/DocEntry\s*[:=]\s*([0-9]+)/i', $message, $matches)) {
                                $sapDocEntry = $matches[1];
                            }
                        }

                        if (empty($sapDocEntry)) {
                            $sapDocEntry = $sapDocNum;
                        }

                        $updatedOrder->update([
                            'doc_entry'     => $sapDocEntry,
                            'doc_num'       => (string) ($sapDocNum ?: $updatedOrder->doc_num),
                            'prod_order_no' => (string) ($sapDocNum ?: $updatedOrder->prod_order_no),
                            'sap_status'    => 'SYNCED',
                            'integrated_at' => now(),
                        ]);
                    } else {
                        $errorMsg = $body['Message'] ?? $body['ErrorMessage'] ?? 'SAP Error Code ' . ($body['ErrorCode'] ?? 'unknown');
                        $updatedOrder->update([
                            'status'     => $oldStatus,
                            'sap_status' => 'FAILED',
                            'sap_error'  => $errorMsg,
                        ]);
                        throw new \Exception("Gagal rilis ke SAP: {$errorMsg}");
                    }
                } else {
                    $errorMsg = 'SAP API Error HTTP ' . $response->status() . ': ' . ($response->body() ?: 'Gagal terhubung ke SAP API');
                    $updatedOrder->update([
                        'status'     => $oldStatus,
                        'sap_status' => 'FAILED',
                        'sap_error'  => $errorMsg,
                    ]);
                    throw new \Exception("Gagal rilis ke SAP: {$errorMsg}");
                }
            } catch (\Exception $ex) {
                $updatedOrder->update([
                    'status'     => $oldStatus,
                    'sap_status' => 'FAILED',
                    'sap_error'  => $ex->getMessage(),
                ]);
                throw new \Exception($ex->getMessage(), $ex->getCode() ?: 500, $ex);
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
        $unit = (string) ($data['u_unit'] ?? $data['unit'] ?? $data['Unit'] ?? $data['uom'] ?? $data['Uom'] ?? $data['UOM'] ?? '');
        if (empty($unit) && !empty($itemCode)) {
            $unit = $this->resolveItemUom($itemCode);
        }
        $rawBomId = $data['production_bom_id'] ?? $data['bom_id'] ?? $data['Bomid'] ?? null;
        $bomId = is_numeric($rawBomId) && (int)$rawBomId > 0 ? (int)$rawBomId : null;

        // 1. Simpan ke database lokal dengan status yang dikirimkan FE
        $localData = [
            'item_code'         => $itemCode,
            'status'            => $status,
            'type'              => $data['type'] ?? 'Standard',
            'series'            => is_numeric($data['series'] ?? $data['Series'] ?? null) ? (int) ($data['series'] ?? $data['Series']) : 15,
            'series_name'       => $data['series_name'] ?? $data['SeriesName'] ?? null,
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
                $response = Http::retry(3, 1000)->timeout(45)->post("{$sapUrl}/api/addpdo", $sapPayload);
                if ($response->successful()) {
                    $body = $response->json();
                    if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                        $message = (string) ($body['Message'] ?? $body['message'] ?? '');
                        $sapDocNum = null;
                        $sapDocEntry = null;

                        // 1. Prioritize explicit Message patterns from SAP
                        if (!empty($message)) {
                            // Pattern 0 (HIGHEST PRIORITY): "DocNum: 260910013 - 6717" or "DocNum: 260910013 - 6717)"
                            // Actual SAP middleware format — no "DocEntry" keyword in string!
                            if (preg_match('/DocNum\s*[:=]\s*([0-9]+)\s*-\s*([0-9]+)/i', $message, $matches)) {
                                $sapDocNum = $matches[1];
                                $sapDocEntry = $matches[2];
                            }
                            // Pattern 1: "DocNum - DocEntry : 260910013 - 6717"
                            elseif (preg_match('/DocNum\s*-\s*DocEntry\s*[:=]?\s*([0-9]+)\s*-\s*([0-9]+)/i', $message, $matches)) {
                                $sapDocNum = $matches[1];
                                $sapDocEntry = $matches[2];
                            }
                            // Pattern 2: "DocNum: 260910013 - DocEntry: 6717" or "DocNum: 260910013, DocEntry: 6717"
                            elseif (preg_match('/DocNum\s*[:=]\s*([0-9]+).*?DocEntry\s*[:=]\s*([0-9]+)/i', $message, $matches)) {
                                $sapDocNum = $matches[1];
                                $sapDocEntry = $matches[2];
                            }
                            // Pattern 3: "DocEntry: 6717 - DocNum: 260910013"
                            elseif (preg_match('/DocEntry\s*[:=]\s*([0-9]+).*?DocNum\s*[:=]\s*([0-9]+)/i', $message, $matches)) {
                                $sapDocEntry = $matches[1];
                                $sapDocNum = $matches[2];
                            }
                        }

                        // 2. Fallback to JSON fields if not matched from Message
                        if (empty($sapDocNum)) {
                            $sapDocNum = $body['DocNum'] ?? $body['doc_num'] ?? $body['Result']['DocNum'] ?? null;
                            if (is_string($sapDocNum) && str_contains($sapDocNum, '-')) {
                                $parts = array_map('trim', explode('-', $sapDocNum));
                                if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                                    $sapDocNum = $parts[0];
                                    if (empty($sapDocEntry)) {
                                        $sapDocEntry = $parts[1];
                                    }
                                }
                            }
                        }

                        if (empty($sapDocEntry)) {
                            $sapDocEntry = $body['DocEntry'] ?? $body['doc_entry'] ?? $body['Result']['DocEntry'] ?? null;
                        }

                        // 3. Fallback regex for individual labels in message
                        if (!empty($message)) {
                            if (empty($sapDocNum) && preg_match('/DocNum\s*[:=]\s*([0-9]+)/i', $message, $matches)) {
                                $sapDocNum = $matches[1];
                            }
                            if (empty($sapDocEntry) && preg_match('/DocEntry\s*[:=]\s*([0-9]+)/i', $message, $matches)) {
                                $sapDocEntry = $matches[1];
                            }
                        }

                        if (empty($sapDocEntry)) {
                            $sapDocEntry = $sapDocNum;
                        }

                        $order->update([
                            'doc_entry'     => $sapDocEntry,
                            'doc_num'       => (string) ($sapDocNum ?: $order->doc_num),
                            'prod_order_no' => (string) ($sapDocNum ?: $order->prod_order_no),
                            'sap_status'    => 'SYNCED',
                            'integrated_at' => now(),
                        ]);
                        $sapResponse = $body;
                    } else {
                        $errorMsg = $body['Message'] ?? $body['ErrorMessage'] ?? 'SAP Error Code ' . ($body['ErrorCode'] ?? 'unknown');
                        $order->update([
                            'status'     => 'PLANNED',
                            'sap_status' => 'FAILED',
                            'sap_error'  => $errorMsg,
                        ]);
                        throw new \Exception("Gagal rilis ke SAP: {$errorMsg}");
                    }
                } else {
                    $errorMsg = 'SAP API Error HTTP ' . $response->status() . ': ' . ($response->body() ?: 'Gagal terhubung ke SAP API');
                    $order->update([
                        'status'     => 'PLANNED',
                        'sap_status' => 'FAILED',
                        'sap_error'  => $errorMsg,
                    ]);
                    throw new \Exception("Gagal rilis ke SAP: {$errorMsg}");
                }
            } catch (\Exception $ex) {
                $order->update([
                    'status'     => 'PLANNED',
                    'sap_status' => 'FAILED',
                    'sap_error'  => $ex->getMessage(),
                ]);
                throw new \Exception($ex->getMessage(), $ex->getCode() ?: 500, $ex);
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

        $rawFrom = $filters['from'] ?? $filters['from_date'] ?? $filters['From'] ?? date('Y-01-01');
        $rawTo = $filters['to'] ?? $filters['to_date'] ?? $filters['To'] ?? date('Y-12-31');

        $fromTime = strtotime((string) $rawFrom);
        $toTime = strtotime((string) $rawTo);

        $fromFormatted = $fromTime ? date('Y-m-d', $fromTime) : (string) $rawFrom;
        $toFormatted = $toTime ? date('Y-m-d', $toTime) : (string) $rawTo;

        $whsCode = (string) ($filters['whs_code'] ?? $filters['warehouse'] ?? $filters['WhsCode'] ?? '');
        $toWhsCode = (string) ($filters['to_whs_code'] ?? $filters['to_warehouse'] ?? $filters['ToWhsCode'] ?? '');

        // Support multiple units (array or comma-separated string)
        $rawUnits = $filters['units'] ?? $filters['unit'] ?? $filters['u_unit'] ?? $filters['Units'] ?? $filters['Unit'] ?? $filters['U_Unit'] ?? null;
        $unitFilterList = [];
        if (is_array($rawUnits)) {
            $unitFilterList = array_values(array_filter(array_map('trim', $rawUnits)));
        } elseif (is_string($rawUnits) && trim($rawUnits) !== '') {
            $unitFilterList = array_values(array_filter(array_map('trim', explode(',', $rawUnits))));
        }

        // Normalize status filter if provided (e.g. RELEASE or RELEASED, ALL, empty)
        $rawStatusFilter = strtoupper(trim((string) ($filters['status'] ?? $filters['Status'] ?? '')));
        $statusFilter = null;
        if (!empty($rawStatusFilter) && !in_array($rawStatusFilter, ['ALL', 'SEMUA', 'ALL STATUS', 'NULL', 'UNDEFINED', ''])) {
            if (str_contains($rawStatusFilter, 'RELEASE') || $rawStatusFilter === 'R') {
                $statusFilter = 'RELEASED';
            } elseif (str_contains($rawStatusFilter, 'PLAN') || $rawStatusFilter === 'P') {
                $statusFilter = 'PLANNED';
            } elseif (str_contains($rawStatusFilter, 'CLOSE') || $rawStatusFilter === 'C') {
                $statusFilter = 'CLOSED';
            } elseif (str_contains($rawStatusFilter, 'CANCEL') || $rawStatusFilter === 'L') {
                $statusFilter = 'CANCELLED';
            } else {
                $statusFilter = $rawStatusFilter;
            }
        }

        $payload = [
            'From' => $fromFormatted,
            'To'   => $toFormatted,
        ];
        if (!empty($whsCode)) {
            $payload['WhsCode'] = $whsCode;
        }
        if (!empty($toWhsCode)) {
            $payload['ToWhsCode'] = $toWhsCode;
        }
        if (!empty($unitFilterList)) {
            $payload['Unit'] = implode(',', $unitFilterList);
            $payload['U_Unit'] = implode(',', $unitFilterList);
        }

        $items = [];

        try {
            $response = Http::retry(2, 500)->timeout(30)->post("{$sapUrl}/api/getListPDO", $payload);

            if ($response->successful()) {
                $body = $response->json();
                if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                    $rawItems = $body['Result'] ?? $body['data'] ?? [];
                    if (is_array($rawItems) && isset($rawItems['Table1'])) {
                        $rawItems = $rawItems['Table1'];
                    } elseif (is_array($rawItems) && isset($rawItems['Items'])) {
                        $rawItems = $rawItems['Items'];
                    } elseif (is_array($rawItems) && isset($rawItems['Rows'])) {
                        $rawItems = $rawItems['Rows'];
                    }

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

        // Normalize Status on SAP items & apply status and unit filter if present
        $normalizedItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $rawS = strtoupper(trim((string) ($item['Status'] ?? $item['status'] ?? $item['ProductionOrderStatus'] ?? $item['DocStatus'] ?? '')));
            if (in_array($rawS, ['R', 'RELEASE', 'RELEASED'])) {
                $item['Status'] = 'RELEASED';
            } elseif (in_array($rawS, ['P', 'PLAN', 'PLANNED'])) {
                $item['Status'] = 'PLANNED';
            } elseif (in_array($rawS, ['C', 'CLOSE', 'CLOSED'])) {
                $item['Status'] = 'CLOSED';
            } elseif (in_array($rawS, ['L', 'CANCEL', 'CANCELLED'])) {
                $item['Status'] = 'CANCELLED';
            } else {
                $item['Status'] = $rawS ?: 'PLANNED';
            }
            $item['status'] = $item['Status'];

            // Filter if status filter is active (if status is empty/ALL, all statuses are included)
            if ($statusFilter && $item['Status'] !== $statusFilter) {
                continue;
            }

            // Filter if unit filter is active (supports multiple units)
            if (!empty($unitFilterList)) {
                $itemUnit = trim((string) ($item['U_Unit'] ?? $item['Unit'] ?? $item['u_unit'] ?? ''));
                $unitMatched = false;
                foreach ($unitFilterList as $u) {
                    if (strcasecmp($itemUnit, $u) === 0) {
                        $unitMatched = true;
                        break;
                    }
                }
                if (!$unitMatched) {
                    continue;
                }
            }

            // Provide BaseEntry, SeriesName, StartDate, and Unit aliases for Frontend convenience
            $docEntryVal = (string) ($item['DocEntry'] ?? $item['doc_entry'] ?? '');
            $item['BaseEntry'] = $docEntryVal;
            $item['base_entry'] = $docEntryVal;
            $sName = (string) ($item['SeriesName'] ?? $item['series_name'] ?? '');
            $item['SeriesName'] = $sName;
            $item['series_name'] = $sName;
            $startDate = $item['StartDate'] ?? $item['start_date'] ?? $item['PostDate'] ?? $item['post_date'] ?? null;
            $item['StartDate'] = $startDate;
            $item['start_date'] = $startDate;

            $unitVal = (string) ($item['U_Unit'] ?? $item['Unit'] ?? $item['u_unit'] ?? '');
            $item['Unit'] = $unitVal;
            $item['U_Unit'] = $unitVal;
            $item['u_unit'] = $unitVal;

            $shiftVal = (string) ($item['U_Shift'] ?? $item['Shift'] ?? $item['u_shift'] ?? $item['shift'] ?? '');
            $item['Shift'] = $shiftVal;
            $item['U_Shift'] = $shiftVal;
            $item['shift'] = $shiftVal;
            $item['u_shift'] = $shiftVal;

            $normalizedItems[] = $item;
        }
        $items = $normalizedItems;

        // Fetch and merge local production orders matching the status & unit filter
        try {
            $localQuery = \App\Models\ProductionOrder::query()
                ->with(['parentItem', 'details']);

            if ($statusFilter) {
                $localQuery->where(function ($q) use ($statusFilter) {
                    $q->where('status', $statusFilter)
                        ->orWhere('status', 'ILIKE', $statusFilter);
                    if ($statusFilter === 'RELEASED') {
                        $q->orWhere('status', 'RELEASE')->orWhere('status', 'ILIKE', 'RELEASE');
                    } elseif ($statusFilter === 'PLANNED') {
                        $q->orWhere('status', 'PLAN')->orWhere('status', 'ILIKE', 'PLAN');
                    } elseif ($statusFilter === 'CLOSED') {
                        $q->orWhere('status', 'CLOSE')->orWhere('status', 'ILIKE', 'CLOSE');
                    } elseif ($statusFilter === 'CANCELLED') {
                        $q->orWhere('status', 'CANCEL')->orWhere('status', 'ILIKE', 'CANCEL');
                    }
                });
            }

            if (!empty($whsCode)) {
                $localQuery->where('warehouse', $whsCode);
            }

            if (!empty($filters['from']) || !empty($filters['from_date']) || !empty($filters['From'])) {
                $fromDate = date('Y-m-d', strtotime((string)$rawFrom));
                $toDate = date('Y-m-d', strtotime((string)$rawTo));
                $localQuery->where(function ($q) use ($fromDate, $toDate) {
                    $q->whereBetween('post_date', [$fromDate, $toDate])
                        ->orWhereBetween('start_date', [$fromDate, $toDate])
                        ->orWhere(function ($sub) use ($fromDate, $toDate) {
                            $sub->whereNull('post_date')->whereNull('start_date')
                                ->whereDate('created_at', '>=', $fromDate)
                                ->whereDate('created_at', '<=', $toDate);
                        });
                });
            }

            if (!empty($unitFilterList)) {
                $localQuery->where(function ($q) use ($unitFilterList) {
                    foreach ($unitFilterList as $idx => $u) {
                        if ($idx === 0) {
                            $q->where('u_unit', $u)->orWhere('u_unit', 'ILIKE', $u);
                        } else {
                            $q->orWhere('u_unit', $u)->orWhere('u_unit', 'ILIKE', $u);
                        }
                    }
                });
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

                $lRawStatus = strtoupper(trim((string) ($lOrder->status ?? '')));
                if (in_array($lRawStatus, ['R', 'RELEASE', 'RELEASED'])) {
                    $normLStatus = 'RELEASED';
                } elseif (in_array($lRawStatus, ['P', 'PLAN', 'PLANNED'])) {
                    $normLStatus = 'PLANNED';
                } elseif (in_array($lRawStatus, ['C', 'CLOSE', 'CLOSED'])) {
                    $normLStatus = 'CLOSED';
                } elseif (in_array($lRawStatus, ['L', 'CANCEL', 'CANCELLED'])) {
                    $normLStatus = 'CANCELLED';
                } else {
                    $normLStatus = $lRawStatus ?: 'PLANNED';
                }

                if ($statusFilter && $normLStatus !== $statusFilter) {
                    continue;
                }

                $docEntryVal = (string) ($lOrder->doc_entry ?: $lOrder->id);
                $localItems[] = [
                    'id'          => $lOrder->id,
                    'DocEntry'    => $docEntryVal,
                    'BaseEntry'   => $docEntryVal,
                    'base_entry'  => $docEntryVal,
                    'DocNum'      => (string) ($lOrder->doc_num ?: $lOrder->prod_order_no),
                    'Series'      => $lOrder->series ?: 15,
                    'SeriesName'  => (string) ($lOrder->series_name ?? ''),
                    'series_name' => (string) ($lOrder->series_name ?? ''),
                    'ItemCode'    => (string) $lOrder->item_code,
                    'ProdName'    => (string) ($lOrder->parentItem?->item_name ?? $this->resolveItemName($lOrder->item_code)),
                    'Status'      => $normLStatus,
                    'status'      => $normLStatus,
                    'Type'        => (string) $lOrder->type,
                    'PlannedQty'  => floatval($lOrder->planned_qty),
                    'CmpltQty'    => floatval($lOrder->cmplt_qty),
                    'RjctQty'     => floatval($lOrder->rjct_qty),
                    'StartDate'   => $lOrder->start_date ? date('Y-m-d\TH:i:s', strtotime($lOrder->start_date)) : ($lOrder->post_date ? date('Y-m-d\TH:i:s', strtotime($lOrder->post_date)) : null),
                    'start_date'  => $lOrder->start_date ? date('Y-m-d\TH:i:s', strtotime($lOrder->start_date)) : ($lOrder->post_date ? date('Y-m-d\TH:i:s', strtotime($lOrder->post_date)) : null),
                    'PostDate'    => $lOrder->post_date ? date('Y-m-d\TH:i:s', strtotime($lOrder->post_date)) : null,
                    'DueDate'     => $lOrder->due_date ? date('Y-m-d\TH:i:s', strtotime($lOrder->due_date)) : null,
                    'WhsCode'     => (string) $lOrder->warehouse,
                    'Unit'        => (string) ($lOrder->u_unit ?? ''),
                    'U_Unit'      => (string) ($lOrder->u_unit ?? ''),
                    'u_unit'      => (string) ($lOrder->u_unit ?? ''),
                    'Shift'       => (string) ($lOrder->u_shift ?? ''),
                    'U_Shift'     => (string) ($lOrder->u_shift ?? ''),
                    'shift'       => (string) ($lOrder->u_shift ?? ''),
                    'u_shift'     => (string) ($lOrder->u_shift ?? ''),
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
                        'status'      => $normStatus,
                        'doc_entry'   => $header['DocEntry'] ?? $localOrder->doc_entry,
                        'doc_num'     => $header['DocNum'] ?? $localOrder->doc_num,
                        'series_name' => $header['SeriesName'] ?? $header['series_name'] ?? $localOrder->series_name,
                        'sap_status'  => 'SYNCED',
                        'cmplt_qty'   => floatval($header['CmpltQty'] ?? $localOrder->cmplt_qty),
                        'rjct_qty'    => floatval($header['RjctQty'] ?? $localOrder->rjct_qty),
                    ]);
                } catch (\Exception $e) {
                    // Ignore DB sync error
                }
            }

            // Normalize header ItemCode, ProdName, SeriesName & UOM
            $hCode = (string) ($header['ItemCode'] ?? $header['item_code'] ?? $header['item'] ?? $localOrder?->item_code ?? '');
            $hName = (string) ($header['ProdName'] ?? $header['prod_name'] ?? $header['ItemName'] ?? $header['item_name'] ?? $localOrder?->parentItem?->item_name ?? '');
            if ((empty($hName) || $hName === $hCode) && !empty($hCode)) {
                $hName = $this->resolveItemName($hCode);
            }
            $hUom = (string) ($header['Uom'] ?? $header['uom'] ?? $header['UOM'] ?? $header['SalUnitMsr'] ?? $header['sal_unit_msr'] ?? $header['UnitMsr'] ?? $localOrder?->parentItem?->sal_unit_msr ?? '');
            if (empty($hUom) && !empty($hCode)) {
                $hUom = $this->resolveItemUom($hCode);
            }
            $sName = (string) ($header['SeriesName'] ?? $header['series_name'] ?? $localOrder?->series_name ?? '');
            $startDate = $header['StartDate'] ?? $header['start_date'] ?? $header['PostDate'] ?? $header['post_date'] ?? null;

            $header['ItemCode'] = $hCode;
            $header['ProdName'] = $hName;
            $header['SeriesName'] = $sName;
            $header['series_name'] = $sName;
            $header['StartDate'] = $startDate;
            $header['start_date'] = $startDate;
            $header['Uom'] = $hUom;
            $header['uom'] = $hUom;
            $header['UOM'] = $hUom;
            $header['SalUnitMsr'] = $hUom;
            $header['sal_unit_msr'] = $hUom;

            // Shift field (return raw value so FE can format/convert as needed)
            $rawShift = (string) ($header['Shift'] ?? $header['U_Shift'] ?? $header['shift'] ?? $header['u_shift'] ?? $localOrder?->u_shift ?? '');
            $header['Shift'] = $rawShift;
            $header['shift'] = $rawShift;
            $header['U_Shift'] = $rawShift;
            $header['u_shift'] = $rawShift;

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
                    $itUom = (string) ($it['Uom'] ?? $it['uom'] ?? $it['UOM'] ?? $it['Unit'] ?? $it['unit'] ?? $it['UnitMsr'] ?? $it['SalUnitMsr'] ?? '');
                    if ((empty($itUom) || strtolower($itUom) === 'manual') && !empty($itCode)) {
                        $resolvedUom = $this->resolveItemUom($itCode);
                        if (!empty($resolvedUom)) {
                            $itUom = $resolvedUom;
                        }
                    }
                    $it['ItemCode'] = $itCode;
                    $it['ItemName'] = $itName;
                    $it['Uom'] = $itUom;
                    $it['uom'] = $itUom;
                    $it['UOM'] = $itUom;
                    $it['Unit'] = $itUom;
                    $it['unit'] = $itUom;
                    unset($it['item_code'], $it['item'], $it['item_name'], $it['prod_name'], $it['ProdName'], $it['Dscription'], $it['dscription']);
                }
                unset($it);
            }
        }

        // 3. Fallback to local DB record if SAP data is not found or empty
        if ((empty($header) && empty($items)) && $localOrder) {
            $hCode = (string) $localOrder->item_code;
            $hName = (string) ($localOrder->parentItem?->item_name ?? $this->resolveItemName($hCode));
            $hUom = (string) ($localOrder->parentItem?->sal_unit_msr ?? $this->resolveItemUom($hCode));

            $rawLocalShift = (string) ($localOrder->u_shift ?? '');

            $header = [
                'id'          => $localOrder->id,
                'DocEntry'    => (string) ($localOrder->doc_entry ?: $localOrder->id),
                'DocNum'      => (string) ($localOrder->doc_num ?: $localOrder->prod_order_no),
                'Series'      => $localOrder->series ?: 15,
                'SeriesName'  => (string) $localOrder->series_name,
                'series_name' => (string) $localOrder->series_name,
                'ItemCode'    => $hCode,
                'ProdName'    => $hName,
                'Uom'         => $hUom,
                'uom'         => $hUom,
                'UOM'         => $hUom,
                'SalUnitMsr'  => $hUom,
                'sal_unit_msr' => $hUom,
                'Status'      => (string) $localOrder->status,
                'Type'        => (string) $localOrder->type,
                'PlannedQty'  => floatval($localOrder->planned_qty),
                'CmpltQty'    => floatval($localOrder->cmplt_qty),
                'RjctQty'     => floatval($localOrder->rjct_qty),
                'StartDate'   => $localOrder->start_date ? date('Y-m-d\TH:i:s', strtotime($localOrder->start_date)) : ($localOrder->post_date ? date('Y-m-d\TH:i:s', strtotime($localOrder->post_date)) : null),
                'start_date'  => $localOrder->start_date ? date('Y-m-d\TH:i:s', strtotime($localOrder->start_date)) : ($localOrder->post_date ? date('Y-m-d\TH:i:s', strtotime($localOrder->post_date)) : null),
                'PostDate'    => $localOrder->post_date ? date('Y-m-d\TH:i:s', strtotime($localOrder->post_date)) : null,
                'DueDate'     => $localOrder->due_date ? date('Y-m-d\TH:i:s', strtotime($localOrder->due_date)) : null,
                'WhsCode'     => (string) $localOrder->warehouse,
                'Remarks'     => (string) $localOrder->comments,
                'Shift'       => $rawLocalShift,
                'shift'       => $rawLocalShift,
                'U_Shift'     => $rawLocalShift,
                'u_shift'     => $rawLocalShift,
                'Unit'        => (string) $localOrder->u_unit,
                'U_Unit'      => (string) $localOrder->u_unit,
                'u_unit'      => (string) $localOrder->u_unit,
                'Bomid'       => (string) $localOrder->production_bom_id,
                'is_local'    => true,
            ];

            $items = [];
            foreach ($localOrder->details as $idx => $line) {
                $lCode = (string) $line->item_code;
                $lName = (string) ($line->item?->item_name ?? $this->resolveItemName($lCode));
                $lUom = (string) ($line->item?->sal_unit_msr ?? $this->resolveItemUom($lCode));

                $items[] = [
                    'LineNum'     => $line->line_num ?? $idx,
                    'ItemType'    => $line->type === 'Resource' ? 'R' : ($line->type === 'Text' ? 'T' : 'I'),
                    'ItemCode'    => $lCode,
                    'ItemName'    => $lName,
                    'Uom'         => $lUom,
                    'uom'         => $lUom,
                    'UOM'         => $lUom,
                    'Unit'        => $lUom,
                    'unit'        => $lUom,
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

        // Support multiple units
        $rawUnits = $filters['units'] ?? $filters['unit'] ?? $filters['u_unit'] ?? $filters['Units'] ?? $filters['Unit'] ?? $filters['U_Unit'] ?? null;
        $unitFilterList = [];
        if (is_array($rawUnits)) {
            $unitFilterList = array_values(array_filter(array_map('trim', $rawUnits)));
        } elseif (is_string($rawUnits) && trim($rawUnits) !== '') {
            $unitFilterList = array_values(array_filter(array_map('trim', explode(',', $rawUnits))));
        }

        $payload = [
            'From'      => $fromFormatted,
            'To'        => $toFormatted,
            'WhsCode'   => $whsCode,
            'ToWhsCode' => $toWhsCode,
        ];
        if (!empty($unitFilterList)) {
            $payload['Unit'] = implode(',', $unitFilterList);
            $payload['U_Unit'] = implode(',', $unitFilterList);
        }

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

        // Filter SAP items by units if provided
        if (!empty($unitFilterList)) {
            $items = array_values(array_filter($items, function ($item) use ($unitFilterList) {
                $itemUnit = trim((string) ($item['U_Unit'] ?? $item['Unit'] ?? $item['u_unit'] ?? ''));
                foreach ($unitFilterList as $u) {
                    if (strcasecmp($itemUnit, $u) === 0) {
                        return true;
                    }
                }
                return false;
            }));
        }

        // Fetch and merge local Production Receipts from Database
        try {
            $localReceiptQuery = \App\Models\ProductionReceipt::query()
                ->with(['items.item', 'productionOrder.parentItem']);

            if (!empty($rawFrom) && !empty($rawTo)) {
                $localReceiptQuery->whereBetween('doc_date', [$rawFrom, $rawTo]);
            }

            if (!empty($unitFilterList)) {
                $localReceiptQuery->where(function ($q) use ($unitFilterList) {
                    foreach ($unitFilterList as $idx => $u) {
                        if ($idx === 0) {
                            $q->where('u_unit', $u)->orWhere('u_unit', 'ILIKE', $u);
                        } else {
                            $q->orWhere('u_unit', $u)->orWhere('u_unit', 'ILIKE', $u);
                        }
                    }
                });
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

        // Enrich any missing ItemCode/ItemName/Comments in list items
        if (!empty($items)) {
            $docEntriesInList = array_filter(array_column($items, 'DocEntry'));
            $docNumsInList = array_filter(array_column($items, 'DocNum'));
            $localReceiptsMap = [];
            if (!empty($docEntriesInList) || !empty($docNumsInList)) {
                try {
                    $localReceiptsQuery = \App\Models\ProductionReceipt::query();
                    if (!empty($docEntriesInList) && !empty($docNumsInList)) {
                        $localReceiptsQuery->whereIn('doc_entry', $docEntriesInList)->orWhereIn('doc_num', $docNumsInList);
                    } elseif (!empty($docEntriesInList)) {
                        $localReceiptsQuery->whereIn('doc_entry', $docEntriesInList);
                    } else {
                        $localReceiptsQuery->whereIn('doc_num', $docNumsInList);
                    }
                    $records = $localReceiptsQuery->get();
                    foreach ($records as $r) {
                        if ($r->doc_entry) $localReceiptsMap[(string)$r->doc_entry] = $r;
                        if ($r->doc_num) $localReceiptsMap[(string)$r->doc_num] = $r;
                    }
                } catch (\Exception $e) {
                }
            }

            foreach ($items as &$it) {
                if (!is_array($it)) continue;
                $c = (string) ($it['ItemCode'] ?? $it['item_code'] ?? $it['item'] ?? $it['Code'] ?? $it['code'] ?? '');
                $n = (string) ($it['ItemName'] ?? $it['item_name'] ?? $it['ProdName'] ?? $it['prod_name'] ?? $it['Dscription'] ?? $it['dscription'] ?? $it['ItemDescription'] ?? $it['item_description'] ?? $it['Description'] ?? '');

                if ((empty($n) || $n === $c) && !empty($c)) {
                    $n = $this->resolveItemName($c);
                }

                $it['ItemCode'] = $c;
                $it['ItemName'] = $n;

                // Fallback Comments from local DB if SAP comments is empty
                $docEntryKey = (string)($it['DocEntry'] ?? '');
                $docNumKey = (string)($it['DocNum'] ?? '');
                $localRec = $localReceiptsMap[$docEntryKey] ?? ($localReceiptsMap[$docNumKey] ?? null);
                if (empty($it['Comments']) && $localRec && !empty($localRec->comments)) {
                    $it['Comments'] = (string) $localRec->comments;
                }

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
                    } catch (\Exception $e) {
                    }
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

            if (empty($header['Comments']) && $localReceipt && !empty($localReceipt->comments)) {
                $header['Comments'] = (string) $localReceipt->comments;
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

        // Support multiple units
        $rawUnits = $filters['units'] ?? $filters['unit'] ?? $filters['u_unit'] ?? $filters['Units'] ?? $filters['Unit'] ?? $filters['U_Unit'] ?? null;
        $unitFilterList = [];
        if (is_array($rawUnits)) {
            $unitFilterList = array_values(array_filter(array_map('trim', $rawUnits)));
        } elseif (is_string($rawUnits) && trim($rawUnits) !== '') {
            $unitFilterList = array_values(array_filter(array_map('trim', explode(',', $rawUnits))));
        }

        $payload = [
            'From'      => $fromFormatted,
            'To'        => $toFormatted,
            'WhsCode'   => $whsCode,
            'ToWhsCode' => $toWhsCode,
        ];
        if (!empty($unitFilterList)) {
            $payload['Unit'] = implode(',', $unitFilterList);
            $payload['U_Unit'] = implode(',', $unitFilterList);
        }

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

        // Filter SAP items by units if provided
        if (!empty($unitFilterList)) {
            $items = array_values(array_filter($items, function ($item) use ($unitFilterList) {
                $itemUnit = trim((string) ($item['U_Unit'] ?? $item['Unit'] ?? $item['u_unit'] ?? ''));
                foreach ($unitFilterList as $u) {
                    if (strcasecmp($itemUnit, $u) === 0) {
                        return true;
                    }
                }
                return false;
            }));
        }

        // Fetch and merge local Production Issues from Database
        try {
            $localIssueQuery = \App\Models\ProductionIssue::query()
                ->with(['items.item', 'productionOrder.parentItem']);

            if (!empty($rawFrom) && !empty($rawTo)) {
                $localIssueQuery->whereBetween('doc_date', [$rawFrom, $rawTo]);
            }

            if (!empty($unitFilterList)) {
                $localIssueQuery->where(function ($q) use ($unitFilterList) {
                    foreach ($unitFilterList as $idx => $u) {
                        if ($idx === 0) {
                            $q->where('u_unit', $u)->orWhere('u_unit', 'ILIKE', $u);
                        } else {
                            $q->orWhere('u_unit', $u)->orWhere('u_unit', 'ILIKE', $u);
                        }
                    }
                });
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

        // Enrich any missing ItemCode/ItemName/Comments in list items
        if (!empty($items)) {
            $docEntriesInList = array_filter(array_column($items, 'DocEntry'));
            $docNumsInList = array_filter(array_column($items, 'DocNum'));
            $localIssuesMap = [];
            if (!empty($docEntriesInList) || !empty($docNumsInList)) {
                try {
                    $localIssuesQuery = \App\Models\ProductionIssue::query();
                    if (!empty($docEntriesInList) && !empty($docNumsInList)) {
                        $localIssuesQuery->whereIn('doc_entry', $docEntriesInList)->orWhereIn('doc_num', $docNumsInList);
                    } elseif (!empty($docEntriesInList)) {
                        $localIssuesQuery->whereIn('doc_entry', $docEntriesInList);
                    } else {
                        $localIssuesQuery->whereIn('doc_num', $docNumsInList);
                    }
                    $records = $localIssuesQuery->get();
                    foreach ($records as $r) {
                        if ($r->doc_entry) $localIssuesMap[(string)$r->doc_entry] = $r;
                        if ($r->doc_num) $localIssuesMap[(string)$r->doc_num] = $r;
                    }
                } catch (\Exception $e) {
                }
            }

            foreach ($items as &$it) {
                if (!is_array($it)) continue;
                $c = (string) ($it['ItemCode'] ?? $it['item_code'] ?? $it['item'] ?? $it['Code'] ?? $it['code'] ?? '');
                $n = (string) ($it['ItemName'] ?? $it['item_name'] ?? $it['ProdName'] ?? $it['prod_name'] ?? $it['Dscription'] ?? $it['dscription'] ?? $it['ItemDescription'] ?? $it['item_description'] ?? $it['Description'] ?? '');

                if ((empty($n) || $n === $c) && !empty($c)) {
                    $n = $this->resolveItemName($c);
                }

                $it['ItemCode'] = $c;
                $it['ItemName'] = $n;

                // Fallback Comments from local DB if SAP comments is empty
                $docEntryKey = (string)($it['DocEntry'] ?? '');
                $docNumKey = (string)($it['DocNum'] ?? '');
                $localRec = $localIssuesMap[$docEntryKey] ?? ($localIssuesMap[$docNumKey] ?? null);
                if (empty($it['Comments']) && $localRec && !empty($localRec->comments)) {
                    $it['Comments'] = (string) $localRec->comments;
                }

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
                    } catch (\Exception $e) {
                    }
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

            if (empty($header['Comments']) && $localIssue && !empty($localIssue->comments)) {
                $header['Comments'] = (string) $localIssue->comments;
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
            $order = \App\Models\ProductionOrder::where('doc_entry', is_numeric($docEntry) ? (int)$docEntry : 0)
                ->orWhere('doc_num', (string) $docEntry)
                ->orWhere('prod_order_no', (string) $docEntry)
                ->orWhere('id', is_numeric($docEntry) ? (int)$docEntry : 0)
                ->first();

            if ($order) {
                $order->update([
                    'status'     => 'CANCELLED',
                    'updated_by' => $userId,
                ]);
            }
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

        $docEntry = (string) ($data['doc_entry'] ?? $data['DocEntry'] ?? $data['base_entry'] ?? $data['BaseEntry'] ?? $data['id'] ?? '');
        if (empty($docEntry)) {
            throw new \Exception('DocEntry atau BaseEntry wajib diisi untuk menutup PDO.');
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

        // Update status di database lokal (production.production_orders)
        try {
            $order = \App\Models\ProductionOrder::where('doc_entry', is_numeric($docEntry) ? (int)$docEntry : 0)
                ->orWhere('doc_num', (string) $docEntry)
                ->orWhere('prod_order_no', (string) $docEntry)
                ->orWhere('id', is_numeric($docEntry) ? (int)$docEntry : 0)
                ->first();

            if ($order) {
                $order->update([
                    'status'         => 'CLOSED',
                    'act_close_date' => date('Y-m-d'),
                    'updated_by'     => $userId,
                ]);
            }
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

        // Normalize Shift to valid SAP values: 'A' (Shift 1), 'B' (Shift 2), 'C' (Shift 3)
        $rawShift = trim((string) ($data['shift'] ?? $data['u_shift'] ?? $data['Shift'] ?? $data['U_Shift'] ?? 'A'));
        $shiftUpper = strtoupper($rawShift);
        if (in_array($shiftUpper, ['1', 'SHIFT 1', 'SHIFT1', 'A', 'X', 'ALL'])) {
            $shift = 'A';
        } elseif (in_array($shiftUpper, ['2', 'SHIFT 2', 'SHIFT2', 'B'])) {
            $shift = 'B';
        } elseif (in_array($shiftUpper, ['3', 'SHIFT 3', 'SHIFT3', 'C'])) {
            $shift = 'C';
        } else {
            $shift = 'A';
        }

        return [
            'DocDate'    => $docDate,
            'DocDueDate' => $docDueDate,
            'Comments'   => (string) ($data['comments'] ?? $data['Comments'] ?? ''),
            'Shift'      => $shift,
            'Unit'       => (string) ($data['unit'] ?? $data['u_unit'] ?? $data['Unit'] ?? $data['U_Unit'] ?? ''),
            'Bomid'      => (string) ($data['bom_id'] ?? $data['bomid'] ?? $data['u_bom_id'] ?? $data['Bomid'] ?? $data['U_BomId'] ?? ''),
            'AddonId'    => 2,
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

        // Cache lookup PDOs by BaseEntry (mendukung multiple PDO dalam 1 issue)
        $pdoCache = [];
        $getPdoByBaseEntry = function ($baseEntry) use (&$pdoCache) {
            if (!$baseEntry) return null;
            $key = (string) $baseEntry;
            if (!array_key_exists($key, $pdoCache)) {
                $pdoCache[$key] = \App\Models\ProductionOrder::where('id', is_numeric($baseEntry) ? (int)$baseEntry : 0)
                    ->orWhere('doc_entry', is_numeric($baseEntry) ? (int)$baseEntry : 0)
                    ->orWhere('prod_order_no', (string)$baseEntry)
                    ->first();
            }
            return $pdoCache[$key];
        };

        $firstPdo = $getPdoByBaseEntry($firstBaseEntry);

        $localIssue = \App\Models\ProductionIssue::create([
            'issue_no'            => $issueNo,
            'production_order_id' => $firstPdo?->id,
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

        $affectedPdos = [];

        foreach ($payload['Lines'] as $idx => $line) {
            $baseLine = $line['BaseLine'];
            $qty = floatval($line['Quantity']);
            $currentLinePdo = $getPdoByBaseEntry($line['BaseEntry'] ?? null);
            if ($currentLinePdo) {
                $affectedPdos[$currentLinePdo->id] = $currentLinePdo;
            }

            // Find matching PDO raw material component item
            $pdoItem = null;
            $itemCode = (string) ($line['ItemCode'] ?? '');

            if ($currentLinePdo) {
                $pdoItem = $currentLinePdo->details()->where('line_num', is_numeric($baseLine) ? (int)$baseLine : 0)->first();
                if (!$pdoItem && !empty($itemCode)) {
                    $pdoItem = $currentLinePdo->details()->where('item_code', $itemCode)->first();
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
                'production_order_id'      => $currentLinePdo?->id,
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

        // Catat referensi nomor issue di tabel PDO Header untuk semua PDO yang terlibat
        foreach ($affectedPdos as $affectedPdo) {
            $existingIssues = array_filter(array_map('trim', explode(',', (string)$affectedPdo->issue_for_production)));
            if (!in_array($issueNo, $existingIssues)) {
                $existingIssues[] = $issueNo;
                $affectedPdo->update(['issue_for_production' => implode(', ', $existingIssues)]);
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
                    // 'UoMEntry'  => is_numeric($l['UoMEntry'] ?? 1) ? (int)($l['UoMEntry'] ?? 1) : 1,
                    'OcrCode'   => (string) ($l['OcrCode'] ?? ''),
                    'OcrCode2'  => (string) ($l['OcrCode2'] ?? ''),
                    'OcrCode3'  => (string) ($l['OcrCode3'] ?? ''),
                ];
            }, $payload['Lines']),
        ];

        try {
            $response = Http::retry(3, 1000)->timeout(45)->post("{$sapUrl}/api/AddIssueForProduction", $sapPayload);
            if ($response->successful()) {
                $body = $response->json();
                if (!isset($body['ErrorCode']) || (int)$body['ErrorCode'] === 0) {
                    $sapDocNum   = $body['DocNum'] ?? $body['doc_num'] ?? null;
                    $sapDocEntry = $body['DocEntry'] ?? $body['doc_entry'] ?? null;

                    // Ekstrak dari $body['Result'] jika tersedia
                    $resultData = $body['Result'] ?? $body['result'] ?? null;
                    if (is_array($resultData)) {
                        $firstResult = isset($resultData[0]) ? $resultData[0] : $resultData;
                        if (is_array($firstResult)) {
                            $sapDocNum   = $sapDocNum ?? ($firstResult['DocNum'] ?? $firstResult['doc_num'] ?? null);
                            $sapDocEntry = $sapDocEntry ?? ($firstResult['DocEntry'] ?? $firstResult['doc_entry'] ?? null);
                        }
                    } elseif (is_numeric($resultData) || is_string($resultData)) {
                        $sapDocNum   = $sapDocNum ?? (string)$resultData;
                        $sapDocEntry = $sapDocEntry ?? (string)$resultData;
                    }

                    // Ekstrak DocNum & DocEntry dari string Message jika tidak disediakan langsung sebagai field terpisah
                    // Contoh format message: "Success - [AddIssueForProduction]. DocNum: 260910001"
                    if (empty($sapDocNum) && !empty($body['Message'])) {
                        if (preg_match('/DocNum[:\s]+([0-9]+)/i', $body['Message'], $matches)) {
                            $sapDocNum = $matches[1];
                        }
                    }
                    if (empty($sapDocEntry) && !empty($body['Message'])) {
                        if (preg_match('/DocEntry[:\s]+([0-9]+)/i', $body['Message'], $matches)) {
                            $sapDocEntry = $matches[1];
                        }
                    }

                    $sapDocEntry = $sapDocEntry ?: $sapDocNum;
                    $sapDocNum   = $sapDocNum ?: $sapDocEntry;

                    $localIssue->update([
                        'doc_entry'     => (string) $sapDocEntry,
                        'doc_num'       => (string) ($sapDocNum ?: $localIssue->doc_num),
                        'issue_no'      => (string) ($sapDocNum ?: $localIssue->issue_no),
                        'sap_status'    => 'SYNCED',
                        'sap_error'     => null,
                        'integrated_at' => now(),
                    ]);

                    // Pastikan DocNum dan DocEntry terisi di sapResponse
                    $body['DocNum']   = (string) $sapDocNum;
                    $body['DocEntry'] = (string) $sapDocEntry;
                    if (empty($body['Result'])) {
                        $body['Result'] = [
                            'DocNum'   => (string) $sapDocNum,
                            'DocEntry' => (string) $sapDocEntry,
                        ];
                    }
                    $sapResponse = $body;

                    // Update referensi nomor issue di tabel PDO Header (replace nomor generate dari BE ke nomor resmi SAP)
                    if (!empty($affectedPdos) && $sapDocNum) {
                        foreach ($affectedPdos as $affectedPdo) {
                            $existingIssues = array_filter(array_map('trim', explode(',', (string)$affectedPdo->issue_for_production)));
                            $existingIssues = array_map(function ($val) use ($issueNo, $sapDocNum) {
                                return $val === $issueNo ? (string)$sapDocNum : $val;
                            }, $existingIssues);
                            $affectedPdo->update(['issue_for_production' => implode(', ', array_unique($existingIssues))]);
                        }
                    }
                } else {
                    $localIssue->update([
                        'sap_status' => 'FAILED',
                        'sap_error'  => $body['Message'] ?? 'Unknown SAP error',
                    ]);
                }
            } else {
                $localIssue->update([
                    'sap_status' => 'FAILED',
                    'sap_error'  => 'HTTP ' . $response->status() . ': ' . $response->body(),
                ]);
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
            $response = Http::retry(3, 1000)->timeout(45)->post("{$sapUrl}/api/addreceiptprod", $sapPayload);
            if ($response->successful()) {
                $body = $response->json();
                if (!isset($body['ErrorCode']) || $body['ErrorCode'] === 0) {
                    $sapDocEntry = $body['DocEntry'] ?? $body['doc_entry'] ?? null;
                    $sapDocNum   = $body['DocNum'] ?? $body['doc_num'] ?? null;

                    // Ekstrak dari $body['Result'] jika tersedia
                    $resultData = $body['Result'] ?? $body['result'] ?? null;
                    if (is_array($resultData)) {
                        $firstResult = isset($resultData[0]) ? $resultData[0] : $resultData;
                        if (is_array($firstResult)) {
                            $sapDocNum   = $sapDocNum ?? ($firstResult['DocNum'] ?? $firstResult['doc_num'] ?? null);
                            $sapDocEntry = $sapDocEntry ?? ($firstResult['DocEntry'] ?? $firstResult['doc_entry'] ?? null);
                        }
                    } elseif (is_numeric($resultData) || is_string($resultData)) {
                        $sapDocNum   = $sapDocNum ?? (string)$resultData;
                        $sapDocEntry = $sapDocEntry ?? (string)$resultData;
                    }

                    // Ekstrak DocNum & DocEntry dari string Message jika belum didapat
                    if (empty($sapDocNum) && !empty($body['Message'])) {
                        if (preg_match('/DocNum:\s*([0-9]+)/i', $body['Message'], $matches)) {
                            $sapDocNum = $matches[1];
                        }
                    }
                    if (empty($sapDocEntry) && !empty($body['Message'])) {
                        if (preg_match('/DocEntry:\s*([0-9]+)/i', $body['Message'], $matches)) {
                            $sapDocEntry = $matches[1];
                        }
                    }

                    $sapDocEntry = $sapDocEntry ?: $sapDocNum;
                    $sapDocNum   = $sapDocNum ?: $sapDocEntry;

                    $localReceipt->update([
                        'doc_entry'     => $sapDocEntry,
                        'doc_num'       => $sapDocNum,
                        'receipt_no'    => $sapDocNum ?? $localReceipt->receipt_no,
                        'sap_status'    => 'SYNCED',
                        'sap_error'     => null,
                        'integrated_at' => now(),
                    ]);
                    $sapResponse = $body;

                    // Update referensi nomor receipt di tabel PDO Header
                    if ($pdo && $sapDocNum) {
                        $existingReceipts = array_filter(array_map('trim', explode(',', (string)$pdo->receipt_from_production)));
                        $existingReceipts = array_map(function ($val) use ($receiptNo, $sapDocNum) {
                            return $val === $receiptNo ? $sapDocNum : $val;
                        }, $existingReceipts);
                        $pdo->update(['receipt_from_production' => implode(', ', array_unique($existingReceipts))]);
                    }
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
    public function getUnits(?int $userId = null, bool $forceRefresh = false): array
    {
        $cacheKey = 'sap_production_units';
        $cacheTtl = (int) config('services.sap.cache_ttl', 1800); // 30 minutes default

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $cacheTtl, function () use ($userId) {
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
        });
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

    /**
     * Helper to resolve item unit of measure (UoM) from Master Items, Production Items, or BOMs.
     *
     * @param string|null $itemCode
     * @return string
     */
    public function resolveItemUom(?string $itemCode): string
    {
        if (empty($itemCode)) {
            return '';
        }

        try {
            $uom = \App\Models\ProductionItem::where('item_code', $itemCode)->value('invntry_uom');
            if (!empty($uom)) {
                return (string) $uom;
            }
        } catch (\Exception $e) {
            // ignore
        }

        try {
            $uom = \App\Models\Item::where('item_code', $itemCode)->value('sal_unit_msr');
            if (!empty($uom)) {
                return (string) $uom;
            }
        } catch (\Exception $e) {
            // ignore
        }

        try {
            $uom = \App\Models\ProductionBom::where('product_code', $itemCode)
                ->orWhere('item_code', $itemCode)
                ->value('u_unit');
            if (!empty($uom)) {
                return (string) $uom;
            }
        } catch (\Exception $e) {
            // ignore
        }

        try {
            $uom = \App\Models\ProductionBomItem::where('item_code', $itemCode)->value('uom');
            if (!empty($uom)) {
                return (string) $uom;
            }
        } catch (\Exception $e) {
            // ignore
        }

        return '';
    }

    /**
     * Parse spreadsheet file (.xlsx, .xls, .csv) into structured array of row objects.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array
     * @throws \Exception
     */
    public function parseBomsFromUploadedFile(\Illuminate\Http\UploadedFile $file): array
    {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (empty($rows)) {
                return [];
            }

            // Extract header columns from row 1
            $headerRow = array_shift($rows);
            $headerMap = [];
            foreach ($headerRow as $colKey => $headerName) {
                if ($headerName !== null && trim((string) $headerName) !== '') {
                    $headerMap[$colKey] = trim((string) $headerName);
                }
            }

            $parsedRows = [];
            foreach ($rows as $r) {
                $rowObj = [];
                $hasData = false;
                foreach ($headerMap as $colKey => $headerName) {
                    $val = $r[$colKey] ?? null;
                    if ($val !== null && trim((string) $val) !== '') {
                        $hasData = true;
                    }
                    $rowObj[$headerName] = $val !== null ? trim((string) $val) : '';
                }
                if ($hasData) {
                    $parsedRows[] = $rowObj;
                }
            }

            return $parsedRows;
        } catch (\Exception $e) {
            throw new \Exception('Gagal membaca file Excel/CSV: ' . $e->getMessage());
        }
    }

    /**
     * Clean and sanitize field value from Excel / JSON rows.
     * Treats '?', '0 ?', '0?', '-', 'null', 'NULL', 'n/a', '#N/A', etc. as null.
     */
    protected function cleanFieldValue(mixed $val): ?string
    {
        if ($val === null) {
            return null;
        }

        $str = trim((string) $val);

        if ($str === '') {
            return null;
        }

        $lower = strtolower($str);
        $nullPlaceholders = ['?', '0 ?', '0?', '-', '--', 'null', 'n/a', 'na', '#n/a', '#value!', '#ref!', 'undefined'];
        if (in_array($lower, $nullPlaceholders, true)) {
            return null;
        }

        return $str;
    }

    /**
     * Helper to extract a value from multiple possible case-insensitive key variants.
     */
    protected function extractField(array $row, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $row)) {
                $cleaned = $this->cleanFieldValue($row[$k]);
                if ($cleaned !== null) {
                    return $cleaned;
                }
            }
        }

        // Case-insensitive fallback
        $lowerKeys = array_map('strtolower', $keys);
        foreach ($row as $rowKey => $rowVal) {
            $cleanKey = strtolower(str_replace([' ', '_', '-'], '', (string) $rowKey));
            foreach ($lowerKeys as $targetKey) {
                $cleanTarget = str_replace([' ', '_', '-'], '', $targetKey);
                if ($cleanKey === $cleanTarget) {
                    $cleaned = $this->cleanFieldValue($rowVal);
                    if ($cleaned !== null) {
                        return $cleaned;
                    }
                }
            }
        }

        return $default;
    }

    /**
     * Import BOMs from flat tabular rows (Excel / JSON Array), grouping rows by BOM ID / Prod ItemCode into Header & Detail.
     *
     * @param array $rows
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function importBomsFromFlatArray(array $rows, ?int $userId = null): array
    {
        if (empty($rows)) {
            throw new \Exception('Data rows Excel kosong, tidak ada data yang dapat diimpor.');
        }

        $groupedBoms = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            // Extract Header fields
            $bomId = $this->extractField($row, ['BOM ID', 'bom_id', 'BOMID', 'bomid', 'BomId', 'id_bom']);
            $prodItemCode = $this->extractField($row, ['Prod ItemCode', 'prod_itemcode', 'product_code', 'code', 'ItemCode', 'item_code', 'Father', 'Parent ItemCode']);
            $prodItemName = $this->extractField($row, ['Prod ItemName', 'prod_itemname', 'product_name', 'ItemName', 'item_name']);
            $alternate = (int) ($this->extractField($row, ['Alternative BOM', 'alternative_bom', 'alternate', 'Alternate', 'version', 'AlternativeBOM'], 1));
            $headerQty = (float) ($this->extractField($row, ['BOM Header Qty', 'bom_header_qty', 'qty', 'Quantity', 'Header Qty', 'BOM Qty'], 1.0));
            $prodUom = $this->extractField($row, ['Prod UoM', 'prod_uom', 'uom', 'UoM', 'Header UoM', 'unit', 'u_unit']);
            $prodWhs = $this->extractField($row, ['Prod Warehouse', 'prod_warehouse', 'to_whs', 'ToWhs', 'whs_code', 'Warehouse']);
            $bomRemarks = $this->extractField($row, ['BOM Remarks', 'bom_remarks', 'comments', 'Comments', 'remarks', 'Remarks']);
            $headerCabang = $this->extractField($row, ['Header Cabang', 'header_cabang', 'ocr_code', 'OcrCode', 'Cabang']);
            $headerBusinessUnit = $this->extractField($row, ['Header Business Unit', 'header_business_unit', 'ocr_code2', 'OcrCode2', 'Business Unit']);
            $headerDepartment = $this->extractField($row, ['Header Department', 'header_department', 'ocr_code3', 'OcrCode3', 'Department']);

            if (empty($prodItemCode)) {
                // If row has no parent item code, skip or check if we can group by previous BOM ID
                continue;
            }

            // Define grouping key: Priority on BOM ID, then combination of Prod ItemCode + Alternate
            $groupKey = !empty($bomId) ? 'BOM_' . $bomId : $prodItemCode . '_ALT_' . $alternate;

            if (!isset($groupedBoms[$groupKey])) {
                $groupedBoms[$groupKey] = [
                    'header' => [
                        'code' => $prodItemCode,
                        'qty' => $headerQty > 0 ? $headerQty : 1.0000,
                        'to_whs' => $prodWhs ?: null,
                        'type' => 'P',
                        'alternate' => $alternate > 0 ? $alternate : 1,
                        'u_unit' => $prodUom ?: null,
                        'ocr_code' => $headerCabang ?: null,
                        'ocr_code2' => $headerBusinessUnit ?: null,
                        'ocr_code3' => $headerDepartment ?: null,
                        'comments' => $bomRemarks ?: null,
                        'sap_doc_num' => !empty($bomId) ? (string) $bomId : null,
                        'is_active' => true,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ],
                    'prod_item_name' => $prodItemName,
                    'details' => [],
                ];
            }

            // Extract Detail (Component) fields
            $lineNo = (int) ($this->extractField($row, ['Line No', 'line_no', 'LineNo', 'child_num', 'ChildNum', 'No', 'Line'], count($groupedBoms[$groupKey]['details']) + 1));
            $compTypeRaw = $this->extractField($row, ['Component Type', 'component_type', 'type', 'Type', 'Component Type Code'], 'Item');
            $compType = (in_array(strtolower((string) $compTypeRaw), ['resource', '290', 'res', 'r']) ? 'Resource' : 'Item');
            $compItemCode = $this->extractField($row, ['Component ItemCode', 'component_itemcode', 'comp_code', 'Component Code', 'Child Code']);
            $compQty = (float) ($this->extractField($row, ['Component Qty BOM', 'component_qty_bom', 'quantity', 'Quantity', 'Component Qty', 'Qty BOM', 'Qty BOM per 1 FG'], 1.0));
            $compWhs = $this->extractField($row, ['Component Warehouse', 'component_warehouse', 'warehouse', 'WhsCode', 'Comp Whs']);
            $issueMethodRaw = $this->extractField($row, ['Issue Method', 'issue_method', 'issue_mthd', 'IssueMthd'], 'B');
            $issueMethod = (strtoupper(substr((string) $issueMethodRaw, 0, 1)) === 'M' ? 'M' : 'B');
            $compCabang = $this->extractField($row, ['Component Cabang', 'component_cabang', 'comp_ocr_code', 'Component Distribution Rule 1']);
            $compBusinessUnit = $this->extractField($row, ['Component Business Unit', 'component_business_unit', 'comp_ocr_code2', 'Component Distribution Rule 2']);
            $compDepartment = $this->extractField($row, ['Component Department', 'component_department', 'comp_ocr_code3', 'Component Distribution Rule 3']);
            $compComments = $this->extractField($row, ['Component Remarks', 'component_remarks', 'comp_comments', 'Line Remarks']);

            if (!empty($compItemCode)) {
                $groupedBoms[$groupKey]['details'][] = [
                    'father' => $prodItemCode,
                    'child_num' => $lineNo > 0 ? $lineNo : (count($groupedBoms[$groupKey]['details']) + 1),
                    'type' => $compType,
                    'code' => $compItemCode,
                    'quantity' => $compQty,
                    'warehouse' => $compWhs ?: null,
                    'issue_mthd' => $issueMethod,
                    'ocr_code' => $compCabang ?: null,
                    'ocr_code2' => $compBusinessUnit ?: null,
                    'ocr_code3' => $compDepartment ?: null,
                    'comments' => $compComments ?: null,
                ];
            }
        }

        if (empty($groupedBoms)) {
            throw new \Exception('Tidak ada data BOM valid yang dapat diekstrak dari baris data yang diberikan.');
        }

        $createdBoms = [];
        $updatedBoms = [];
        $totalItemsCreated = 0;

        // Perform Database Transaction
        \Illuminate\Support\Facades\DB::connection('pgsql_production')->transaction(function () use (
            &$createdBoms,
            &$updatedBoms,
            &$totalItemsCreated,
            $groupedBoms,
            $userId
        ) {
            foreach ($groupedBoms as $groupKey => $group) {
                $headerData = $group['header'];
                $detailsData = $group['details'];

                // Check existing BOM by code and alternate
                $bom = \App\Models\ProductionBom::where('code', $headerData['code'])
                    ->where('alternate', $headerData['alternate'])
                    ->first();

                if ($bom) {
                    $bom->update(array_merge($headerData, ['updated_by' => $userId]));
                    // Replace existing details with newly uploaded details
                    $bom->details()->delete();
                    $updatedBoms[] = $bom;
                } else {
                    $bom = \App\Models\ProductionBom::create($headerData);
                    $createdBoms[] = $bom;
                }

                // Insert detail items
                foreach ($detailsData as $detail) {
                    $detail['production_bom_id'] = $bom->id;
                    \App\Models\ProductionBomItem::create($detail);
                    $totalItemsCreated++;
                }
            }
        });

        // Audit Log
        if ($userId && $this->auditLogService) {
            try {
                $this->auditLogService->log(
                    $userId,
                    'IMPORT_BOM_EXCEL',
                    sprintf(
                        'Imported %d BOM headers (%d created, %d updated) with %d components.',
                        count($createdBoms) + count($updatedBoms),
                        count($createdBoms),
                        count($updatedBoms),
                        $totalItemsCreated
                    )
                );
            } catch (\Throwable $e) {
                // Ignore audit log error
            }
        }

        return [
            'total_boms' => count($createdBoms) + count($updatedBoms),
            'total_boms_created' => count($createdBoms),
            'total_boms_updated' => count($updatedBoms),
            'total_items_created' => $totalItemsCreated,
            'boms' => array_map(function ($b) {
                return [
                    'id' => $b->id,
                    'code' => $b->code,
                    'alternate' => $b->alternate,
                    'qty' => (float) $b->qty,
                    'to_whs' => $b->to_whs,
                    'sap_doc_num' => $b->sap_doc_num,
                    'details_count' => $b->details()->count(),
                ];
            }, array_merge($createdBoms, $updatedBoms)),
        ];
    }

    /**
     * Get stock by item in warehouse from SAP API (/api/getstokbyitem).
     *
     * @param array $params
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function getStockByItem(array $params, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');
        $whsCode = (string) ($params['WhsCode'] ?? $params['whs_code'] ?? $params['warehouse'] ?? '');
        $rawQuery = $params['CustomQuery'] ?? $params['custom_query'] ?? $params['item_codes'] ?? $params['item_code'] ?? $params['items'] ?? '';

        if (empty($whsCode)) {
            throw new \Exception("Parameter 'WhsCode' (kode gudang) wajib diisi.");
        }

        // Format CustomQuery to SQL IN format: e.g. "'B12.B','B26'"
        $formattedQuery = $this->formatStockCustomQuery($rawQuery);
        if (empty($formattedQuery)) {
            throw new \Exception("Parameter 'CustomQuery' atau 'item_codes' wajib diisi.");
        }

        // Format WhsCode with single quotes: e.g. "'FG01'"
        $formattedWhsCode = $this->formatStockWhsCode($whsCode);

        $sapPayload = [
            'CustomQuery' => $formattedQuery,
            'WhsCode'     => $formattedWhsCode,
        ];

        $response = \Illuminate\Support\Facades\Http::timeout(30)->post("{$sapUrl}/api/getstokbyitem", $sapPayload);

        if (!$response->successful()) {
            throw new \Exception("Gagal menghubungi API SAP getstokbyitem (HTTP {$response->status()}).");
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && (int)$body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        $items = $body['Result'] ?? $body['data'] ?? $body['Items'] ?? (is_array($body) && array_is_list($body) ? $body : []);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'GET_STOCK_BY_ITEM_SAP',
                "Checked stock in SAP for Warehouse [{$formattedWhsCode}] with query: {$formattedQuery}"
            );
        }

        return [
            'whs_code'     => $formattedWhsCode,
            'custom_query' => $formattedQuery,
            'total_items'  => count($items),
            'items'        => $items,
            'raw'          => $body,
        ];
    }

    /**
     * Format raw input item codes into SAP CustomQuery format: "'ITEM1','ITEM2'".
     */
    protected function formatStockCustomQuery(mixed $raw): string
    {
        if (is_array($raw)) {
            $cleaned = array_filter(array_map(function ($item) {
                if (is_array($item)) {
                    $val = $item['ItemCode'] ?? $item['item_code'] ?? $item['code'] ?? $item['value'] ?? '';
                    return trim((string)$val, " '\"");
                }
                return trim((string)$item, " '\"");
            }, $raw));

            if (empty($cleaned)) {
                return '';
            }

            return "'" . implode("','", array_unique($cleaned)) . "'";
        }

        $str = trim((string) $raw);
        if (empty($str)) {
            return '';
        }

        if (str_contains($str, "'")) {
            return $str;
        }

        $parts = array_filter(array_map(fn($p) => trim($p, " '\""), explode(',', $str)));
        if (empty($parts)) {
            return '';
        }

        return "'" . implode("','", array_unique($parts)) . "'";
    }

    /**
     * Format raw warehouse code into SAP single-quoted format: e.g. "'FG01'".
     */
    protected function formatStockWhsCode(mixed $raw): string
    {
        if (is_array($raw)) {
            $cleaned = array_filter(array_map(fn($w) => trim((string)$w, " '\""), $raw));
            if (empty($cleaned)) {
                return "''";
            }
            return "'" . implode("','", array_unique($cleaned)) . "'";
        }

        $str = trim((string) $raw);
        if (empty($str)) {
            return "''";
        }

        if (str_contains($str, "'")) {
            return $str;
        }

        $parts = array_filter(array_map(fn($p) => trim($p, " '\""), explode(',', $str)));
        if (empty($parts)) {
            return "'{$str}'";
        }

        return "'" . implode("','", array_unique($parts)) . "'";
    }

    /**
     * Format shift code/value into human-readable label:
     * - 'X' / 'x' / 'ALL' / 'All' -> 'All'
     * - '1' / 'Shift 1' -> 'Shift 1'
     * - '2' / 'Shift 2' -> 'Shift 2'
     * - '3' / 'Shift 3' -> 'Shift 3'
     *
     * @param mixed $rawShift
     * @return string
     */
    public function formatShiftLabel(mixed $rawShift): string
    {
        $shift = trim((string) $rawShift);
        if ($shift === '') {
            return '';
        }

        $upper = strtoupper($shift);

        if ($upper === 'X' || $upper === 'ALL') {
            return 'All';
        }
        if ($shift === '1' || $upper === 'SHIFT 1' || $upper === 'SHIFT1' || $upper === 'SHIFT-1') {
            return 'Shift 1';
        }
        if ($shift === '2' || $upper === 'SHIFT 2' || $upper === 'SHIFT2' || $upper === 'SHIFT-2') {
            return 'Shift 2';
        }
        if ($shift === '3' || $upper === 'SHIFT 3' || $upper === 'SHIFT3' || $upper === 'SHIFT-3') {
            return 'Shift 3';
        }

        return $shift;
    }

    /**
     * Map shift value for Change Product SAP integration.
     *
     * SAP B1 U_Shift valid values are strictly:
     * - 'A' = Shift 1
     * - 'B' = Shift 2
     * - 'C' = Shift 3
     *
     * If FE sends 'All', 'all', or empty, fallback to 'A' (Shift 1) unless SAP adds 'X'.
     *
     * @param mixed $shiftValue
     * @return string
     */
    public function mapChangeProductShift(mixed $shiftValue): string
    {
        $val = trim((string) $shiftValue);
        $upper = strtoupper($val);

        if ($upper === 'SHIFT 2' || $upper === 'SHIFT2' || $upper === 'SHIFT-2' || $upper === '2' || $upper === 'B') {
            return 'B';
        }
        if ($upper === 'SHIFT 3' || $upper === 'SHIFT3' || $upper === 'SHIFT-3' || $upper === '3' || $upper === 'C') {
            return 'C';
        }

        // Shift 1, All, '1', 'A', or fallback default to 'A'
        return 'A';
    }

    /**
     * Get all Change Products.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllChangeProducts(array $filters = []): Collection
    {
        return $this->productionRepository->getAllChangeProducts($filters);
    }

    /**
     * Get Change Product by ID.
     *
     * @param int $id
     * @return \App\Models\ProductionChangeProduct
     * @throws \Exception
     */
    public function getChangeProductById(int $id): \App\Models\ProductionChangeProduct
    {
        $cp = $this->productionRepository->getChangeProductById($id);
        if (!$cp) {
            throw new \Exception('Transaksi Change Product tidak ditemukan.');
        }
        return $cp;
    }

    /**
     * Create a new Change Product.
     *
     * @param array $data
     * @param int|null $userId
     * @return \App\Models\ProductionChangeProduct
     */
    public function createChangeProduct(array $data, ?int $userId = null): \App\Models\ProductionChangeProduct
    {
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        $data['status'] = $data['status'] ?? 'DRAFT';
        $data['sap_status'] = $data['sap_status'] ?? 'PENDING';

        $cp = $this->productionRepository->createChangeProduct($data);

        if ($userId) {
            $oldLineCount = $cp->oldLines ? $cp->oldLines->count() : 0;
            $newLineCount = $cp->newLines ? $cp->newLines->count() : 0;
            $this->auditLogService->log(
                $userId,
                'CREATE_CHANGE_PRODUCT',
                "Membuat draft Change Product {$cp->cp_no} dengan {$oldLineCount} old lines dan {$newLineCount} new lines."
            );
        }

        return $cp;
    }

    /**
     * Update an existing Change Product.
     *
     * @param int $id
     * @param array $data
     * @param int|null $userId
     * @return \App\Models\ProductionChangeProduct
     * @throws \Exception
     */
    public function updateChangeProduct(int $id, array $data, ?int $userId = null): \App\Models\ProductionChangeProduct
    {
        $cp = $this->getChangeProductById($id);

        if ($cp->status === 'COMPLETE') {
            throw new \Exception('Transaksi Change Product yang sudah COMPLETE tidak dapat diubah.');
        }

        $data['updated_by'] = $userId;

        $updatedCp = $this->productionRepository->updateChangeProduct($cp, $data);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'UPDATE_CHANGE_PRODUCT',
                "Memperbarui Change Product {$updatedCp->cp_no}."
            );
        }

        return $updatedCp;
    }

    /**
     * Delete a Change Product.
     *
     * @param int $id
     * @param int|null $userId
     * @return bool
     * @throws \Exception
     */
    public function deleteChangeProduct(int $id, ?int $userId = null): bool
    {
        $cp = $this->getChangeProductById($id);

        if ($cp->status === 'COMPLETE') {
            throw new \Exception('Transaksi Change Product yang sudah COMPLETE tidak dapat dihapus.');
        }

        $cpNo = $cp->cp_no;
        $deleted = $this->productionRepository->deleteChangeProduct($cp);

        if ($userId && $deleted) {
            $this->auditLogService->log(
                $userId,
                'DELETE_CHANGE_PRODUCT',
                "Menghapus Change Product {$cpNo}."
            );
        }

        return $deleted;
    }

    /**
     * Post Change Product transaction to SAP B1 (/api/AddCP).
     *
     * @param int|\App\Models\ProductionChangeProduct $cpOrId
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function postChangeProductToSap(int|\App\Models\ProductionChangeProduct $cpOrId, ?int $userId = null): array
    {
        $cp = is_numeric($cpOrId) ? $this->getChangeProductById((int)$cpOrId) : $cpOrId;
        $cp->loadMissing(['oldLines', 'newLines']);

        if ($cp->oldLines->isEmpty() || $cp->newLines->isEmpty()) {
            throw new \Exception('Transaksi Change Product wajib memiliki OldLines dan NewLines.');
        }

        $docDate = $cp->doc_date ? $cp->doc_date->format('Y-m-d\TH:i:s') : now()->format('Y-m-d\TH:i:s');
        $docDueDate = $cp->doc_due_date ? $cp->doc_due_date->format('Y-m-d\TH:i:s') : $docDate;

        $oldLines = [];
        foreach ($cp->oldLines as $item) {
            $oldLines[] = [
                'itemCode'    => (string)$item->item_code,
                'quantity'    => floatval($item->quantity),
                'fromWhsCode' => (string)$item->from_whs_code,
                'ocrCode'     => (string)($item->ocr_code ?? ''),
                'ocrCode2'    => (string)($item->ocr_code2 ?? ''),
                'ocrCode3'    => (string)($item->ocr_code3 ?? ''),
            ];
        }

        $newLines = [];
        foreach ($cp->newLines as $item) {
            $newLines[] = [
                'itemCode'               => (string)$item->item_code,
                'quantity'               => floatval($item->quantity),
                'toWhsCode'              => (string)$item->to_whs_code,
                'ocrCode'                => (string)($item->ocr_code ?? ''),
                'ocrCode2'               => (string)($item->ocr_code2 ?? ''),
                'ocrCode3'               => (string)($item->ocr_code3 ?? ''),
                'valueAllocationPercent' => floatval($item->value_allocation_percent ?? 0),
            ];
        }

        $sapPayload = [
            'docDate'    => $docDate,
            'docDueDate' => $docDueDate,
            'comments'   => (string)($cp->comments ?? "Change Product {$cp->cp_no}"),
            'shift'      => $this->mapChangeProductShift($cp->shift),
            'unit'       => (string)($cp->unit ?? ''),
            'addonId'    => '2',
            'userId'     => (string)($cp->user_id ?? ($userId ? (string)$userId : '1')),
            'oldLines'   => $oldLines,
            'newLines'   => $newLines,
        ];

        $sapUrl = config('services.sap.url');
        if (empty($sapUrl)) {
            throw new \Exception('Konfigurasi URL SAP (services.sap.url) belum diatur.');
        }

        try {
            $response = Http::retry(3, 1000)->timeout(45)->post("{$sapUrl}/api/AddCP", $sapPayload);
        } catch (\Exception $ex) {
            $cp->update([
                'sap_status' => 'FAILED',
                'sap_error'  => 'Koneksi ke SAP gagal: ' . $ex->getMessage(),
            ]);
            throw new \Exception('Gagal menghubungi API SAP AddCP: ' . $ex->getMessage());
        }

        if (!$response->successful()) {
            $errorMsg = "HTTP error {$response->status()} dari SAP: " . $response->body();
            $cp->update([
                'sap_status' => 'FAILED',
                'sap_error'  => $errorMsg,
            ]);
            throw new \Exception($errorMsg);
        }

        $body = $response->json();
        $errorCode = $body['errorCode'] ?? $body['ErrorCode'] ?? 0;
        $message = $body['message'] ?? $body['Message'] ?? '';

        if ($errorCode !== 0) {
            $cp->update([
                'sap_status' => 'FAILED',
                'sap_error'  => $message ?: 'SAP mengembalikan error tanpa pesan.',
            ]);
            throw new \Exception('SAP AddCP Error: ' . ($message ?: 'Unknown error'));
        }

        // Extract Issue Entry (GI) & Receipt Entry (GR)
        $issueEntry = $body['issue_entry'] ?? $body['IssueEntry'] ?? $body['gi_entry'] ?? null;
        $receiptEntry = $body['receipt_entry'] ?? $body['ReceiptEntry'] ?? $body['gr_entry'] ?? null;

        if (!$issueEntry && preg_match('/Issue\s*Entry:\s*(\d+)/i', $message, $giMatch)) {
            $issueEntry = (int)$giMatch[1];
        }
        if (!$receiptEntry && preg_match('/Receipt\s*Entry:\s*(\d+)/i', $message, $grMatch)) {
            $receiptEntry = (int)$grMatch[1];
        }

        $cp->update([
            'gi_entry'      => $issueEntry ? (int)$issueEntry : null,
            'gr_entry'      => $receiptEntry ? (int)$receiptEntry : null,
            'status'        => 'COMPLETE',
            'sap_status'    => 'SYNCED',
            'sap_message'   => $message,
            'sap_error'     => null,
            'integrated_at' => now(),
            'updated_by'    => $userId,
        ]);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'POST_CHANGE_PRODUCT',
                "Posting Change Product {$cp->cp_no} ke SAP berhasil. Issue Entry: {$issueEntry}, Receipt Entry: {$receiptEntry}"
            );
        }

        return [
            'change_product' => $cp->fresh(['oldLines', 'newLines']),
            'gi_entry'       => $issueEntry,
            'gr_entry'       => $receiptEntry,
            'sap_response'   => $body,
        ];
    }

    /**
     * Edit Comment on Issue for Production in SAP (/api/EditCommentIssueForProduction).
     *
     * @param array $data
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function editCommentIssueSap(array $data, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        $docEntry = (string) ($data['DocEntry'] ?? $data['doc_entry'] ?? $data['docEntry'] ?? $data['id'] ?? '');
        if (empty($docEntry)) {
            throw new \Exception('DocEntry wajib diisi untuk mengubah komentar Issue for Production.');
        }

        $comment = (string) ($data['Comment'] ?? $data['comment'] ?? $data['remarks'] ?? $data['Remarks'] ?? $data['comments'] ?? $data['Comments'] ?? '');

        $payload = [
            'DocEntry' => $docEntry,
            'Comment'  => $comment,
        ];

        $response = Http::timeout(30)->post("{$sapUrl}/api/EditCommentIssueForProduction", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP EditCommentIssueForProduction. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && (int)$body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP EditCommentIssueForProduction error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        // Update local database record if exists
        try {
            \App\Models\ProductionIssue::where('doc_entry', $docEntry)
                ->orWhere('id', is_numeric($docEntry) ? (int)$docEntry : 0)
                ->update([
                    'comments'   => $comment,
                    'updated_by' => $userId,
                ]);
        } catch (\Exception $e) {
            // Ignore local DB update failure if table does not exist or record not found
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'EDIT_COMMENT_ISSUE_SAP',
                "Edited comment on Issue for Production for DocEntry {$docEntry}."
            );
        }

        return [
            'doc_entry'    => $docEntry,
            'comment'      => $comment,
            'sap_response' => $body,
        ];
    }

    /**
     * Edit Remarks on Production Receipt in SAP (/api/EditReceiptRemarks).
     *
     * @param array $data
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function editRemarksReceiptSap(array $data, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        $docEntry = (string) ($data['DocEntry'] ?? $data['doc_entry'] ?? $data['docEntry'] ?? $data['id'] ?? '');
        if (empty($docEntry)) {
            throw new \Exception('DocEntry wajib diisi untuk mengubah remarks Production Receipt.');
        }

        $comment = (string) ($data['Comment'] ?? $data['comment'] ?? $data['remarks'] ?? $data['Remarks'] ?? $data['comments'] ?? $data['Comments'] ?? '');

        $payload = [
            'DocEntry' => $docEntry,
            'Comment'  => $comment,
        ];

        $response = Http::timeout(30)->post("{$sapUrl}/api/EditReceiptRemarks", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP EditReceiptRemarks. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && (int)$body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP EditReceiptRemarks error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        // Update local database record if exists
        try {
            \App\Models\ProductionReceipt::where('doc_entry', $docEntry)
                ->orWhere('id', is_numeric($docEntry) ? (int)$docEntry : 0)
                ->update([
                    'comments'   => $comment,
                    'updated_by' => $userId,
                ]);
        } catch (\Exception $e) {
            // Ignore local DB update failure if table does not exist or record not found
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'EDIT_REMARKS_RECEIPT_SAP',
                "Edited remarks on Production Receipt for DocEntry {$docEntry}."
            );
        }

        return [
            'doc_entry'    => $docEntry,
            'comment'      => $comment,
            'sap_response' => $body,
        ];
    }
}
