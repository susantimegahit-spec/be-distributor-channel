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

        $updatedOrder = $this->productionRepository->updateOrder($order, $data);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'UPDATE_PRODUCTION_ORDER',
                "Updated production order with number {$updatedOrder->prod_order_no}."
            );
        }

        return $updatedOrder;
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
     * Post/Add Production Order (PDO) to SAP via API endpoint /api/addpdo
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

        // Check if raw SAP payload keys are provided directly
        if (isset($data['ItemCode']) && isset($data['Lines'])) {
            $payload = [
                'ItemCode'    => (string) ($data['ItemCode'] ?? ''),
                'Series'      => is_numeric($data['Series'] ?? null) ? (int) $data['Series'] : ($data['Series'] ?? 15),
                'PlannedQty'  => floatval($data['PlannedQty'] ?? 0),
                'PostingDate' => isset($data['PostingDate']) ? date('Y-m-d\TH:i:s', strtotime($data['PostingDate'])) : date('Y-m-d\TH:i:s'),
                'DueDate'     => isset($data['DueDate']) ? date('Y-m-d\TH:i:s', strtotime($data['DueDate'])) : date('Y-m-d\TH:i:s'),
                'WhsCode'     => (string) ($data['WhsCode'] ?? ''),
                'Remarks'     => (string) ($data['Remarks'] ?? ''),
                'Shift'       => $mapShift($data['Shift'] ?? ''),
                'Unit'        => (string) ($data['Unit'] ?? ''),
                'Bomid'       => (string) ($data['Bomid'] ?? ''),
                'UserId'      => (string) ($data['UserId'] ?? ($userId ? (string)$userId : '1')),
                'AddonId'     => (string) ($data['AddonId'] ?? '2'),
                'Lines'       => [],
            ];

            foreach ($data['Lines'] as $line) {
                $payload['Lines'][] = [
                    'ItemType'    => (string) ($line['ItemType'] ?? 'I'),
                    'ItemCode'    => (string) ($line['ItemCode'] ?? ''),
                    'BaseQty'     => floatval($line['BaseQty'] ?? 0),
                    'WhsCode'     => (string) ($line['WhsCode'] ?? ''),
                    'IssueMethod' => (string) ($line['IssueMethod'] ?? 'M'),
                    'OcrCode'     => (string) ($line['OcrCode'] ?? ''),
                    'OcrCode2'    => (string) ($line['OcrCode2'] ?? ''),
                    'OcrCode3'    => (string) ($line['OcrCode3'] ?? ''),
                ];
            }
        } else {
            // Map from local Production Order request/model structure
            $lines = [];
            $rawDetails = $data['details'] ?? $data['lines'] ?? [];
            foreach ($rawDetails as $detail) {
                $type = $detail['type'] ?? $detail['ItemType'] ?? 'Item';
                if ($type === 'Item' || $type === '4' || $type === 4 || $type === 'I') {
                    $itemType = 'I';
                } elseif ($type === 'Resource' || $type === '290' || $type === 290 || $type === 'R') {
                    $itemType = 'R';
                } elseif ($type === 'Text' || $type === 'T') {
                    $itemType = 'T';
                } else {
                    $itemType = 'I';
                }

                $lines[] = [
                    'ItemType'    => $itemType,
                    'ItemCode'    => (string) ($detail['item_code'] ?? $detail['ItemCode'] ?? $detail['code'] ?? ''),
                    'BaseQty'     => floatval($detail['base_qty'] ?? $detail['BaseQty'] ?? $detail['quantity'] ?? 0),
                    'WhsCode'     => (string) ($detail['warehouse'] ?? $detail['whs_code'] ?? $detail['WhsCode'] ?? ''),
                    'IssueMethod' => (string) ($detail['issue_mthd'] ?? $detail['issueMethod'] ?? $detail['IssueMethod'] ?? 'M'),
                    'OcrCode'     => (string) ($detail['ocr_code'] ?? $detail['OcrCode'] ?? ''),
                    'OcrCode2'    => (string) ($detail['ocr_code2'] ?? $detail['OcrCode2'] ?? ''),
                    'OcrCode3'    => (string) ($detail['ocr_code3'] ?? $detail['OcrCode3'] ?? ''),
                ];
            }

            $payload = [
                'ItemCode'    => (string) ($data['item_code'] ?? $data['product_code'] ?? $data['ItemCode'] ?? ''),
                'Series'      => is_numeric($data['series'] ?? null) ? (int) $data['series'] : ($data['Series'] ?? 15),
                'PlannedQty'  => floatval($data['planned_qty'] ?? $data['planned_quantity'] ?? $data['PlannedQty'] ?? 0),
                'PostingDate' => isset($data['post_date']) ? date('Y-m-d\TH:i:s', strtotime($data['post_date'])) : (isset($data['PostingDate']) ? date('Y-m-d\TH:i:s', strtotime($data['PostingDate'])) : date('Y-m-d\TH:i:s')),
                'DueDate'     => isset($data['due_date']) ? date('Y-m-d\TH:i:s', strtotime($data['due_date'])) : (isset($data['DueDate']) ? date('Y-m-d\TH:i:s', strtotime($data['DueDate'])) : date('Y-m-d\TH:i:s')),
                'WhsCode'     => (string) ($data['warehouse'] ?? $data['whs_code'] ?? $data['to_whs'] ?? $data['WhsCode'] ?? ''),
                'Remarks'     => (string) ($data['comments'] ?? $data['remarks'] ?? $data['Remarks'] ?? ''),
                'Shift'       => $mapShift($data['u_shift'] ?? $data['shift'] ?? $data['Shift'] ?? ''),
                'Unit'        => (string) ($data['u_unit'] ?? $data['unit'] ?? $data['Unit'] ?? ''),
                'Bomid'       => (string) ($data['production_bom_id'] ?? $data['bom_id'] ?? $data['Bomid'] ?? ''),
                'UserId'      => (string) ($data['user_id'] ?? $data['UserId'] ?? ($userId ? (string)$userId : '1')),
                'AddonId'     => (string) ($data['addon_id'] ?? $data['AddonId'] ?? '2'),
                'Lines'       => $lines,
            ];
        }

        $response = Http::timeout(30)->post("{$sapUrl}/api/addpdo", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP addpdo. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP addpdo error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'ADD_PDO_SAP',
                "Submitted Production Order (PDO) to SAP for ItemCode {$payload['ItemCode']}."
            );
        }

        return [
            'payload' => $payload,
            'sap_response' => $body,
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

        $response = Http::timeout(30)->post("{$sapUrl}/api/getListPDO", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP getListPDO. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP getListPDO error: ' . ($body['Message'] ?? 'Unknown SAP error'));
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
                'GET_LIST_PDO_SAP',
                "Fetched Production Orders list from SAP (From: {$fromFormatted}, To: {$toFormatted})."
            );
        }

        return [
            'filters' => $payload,
            'items'   => $items,
        ];
    }

    /**
     * Get detail of Production Order (PDO) from SAP API endpoint (/api/getPDObyId).
     *
     * @param string|int $customQuery
     * @param int|null $userId
     * @return array
     */
    public function getPdoById(string|int $customQuery, ?int $userId = null): array
    {
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
     * Add Goods Issue for Production to SAP API (/api/addissueprod).
     *
     * @param array $data
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function addIssueProdSap(array $data, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');
        $payload = $this->prepareProdTransactionPayload($data, $userId, 'Issue for Production');

        $response = Http::timeout(45)->post("{$sapUrl}/api/addissueprod", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP addissueprod. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP addissueprod error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'ADD_ISSUE_PROD_SAP',
                "Submitted Goods Issue for Production to SAP: " . ($body['Message'] ?? json_encode($body))
            );
        }

        return [
            'payload'      => $payload,
            'sap_response' => $body,
        ];
    }

    /**
     * Add Receipt for Production to SAP API (/api/addreceiptprod).
     *
     * @param array $data
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function addReceiptProdSap(array $data, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');
        $payload = $this->prepareProdTransactionPayload($data, $userId, 'Receipt for Production');

        $response = Http::timeout(45)->post("{$sapUrl}/api/addreceiptprod", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP addreceiptprod. HTTP Status: ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP addreceiptprod error: ' . ($body['Message'] ?? 'Unknown SAP error'));
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'ADD_RECEIPT_PROD_SAP',
                "Submitted Receipt for Production to SAP: " . ($body['Message'] ?? json_encode($body))
            );
        }

        return [
            'payload'      => $payload,
            'sap_response' => $body,
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
