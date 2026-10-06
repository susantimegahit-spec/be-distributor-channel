<?php

namespace App\Modules\PurchasingRequest\Services;

use App\Exceptions\SapException;
use App\Models\ProductionBom;
use App\Models\PurchaseRequest;
use App\Modules\PurchasingRequest\Repositories\PurchaseRequestRepositoryInterface;
use Illuminate\Support\Facades\Http;

class PurchaseRequestService
{
    protected PurchaseRequestRepositoryInterface $repository;

    public function __construct(PurchaseRequestRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getList(array $filters = [], int $perPage = 15)
    {
        return $this->repository->getAll($filters, $perPage);
    }

    public function getDetail(int $id): ?PurchaseRequest
    {
        return $this->repository->findById($id);
    }

    /**
     * Get available Document Series from SAP B1 for Purchase Request.
     */
    public function getSeries(?string $customQuery = null): array
    {
        $customQuery = $customQuery ?: date('Ymd');
        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        try {
            $response = Http::timeout(15)->post("{$sapUrl}/api/getSeries", [
                'CustomQuery' => $customQuery,
                'Document'    => '1470000113', // SAP Object Type for Purchase Request
            ]);

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['Result']) && is_array($body['Result'])) {
                    return $body['Result'];
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch PR series from SAP: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Get item list for Purchase Request with default UoM parameters.
     */
    public function getItems(array $filters = []): array
    {
        $search = $filters['search'] ?? null;
        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        try {
            $response = Http::timeout(15)->post("{$sapUrl}/api/ListItem", [
                'CustomQuery' => $search ?: '',
            ]);

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['Result']) && is_array($body['Result'])) {
                    $items = [];
                    foreach ($body['Result'] as $item) {
                        $items[] = [
                            'item_code'  => $item['ItemCode'] ?? '',
                            'item_name'  => $item['ItemName'] ?? '',
                            'uom_entry'  => '-1',
                            'uom_code'   => '-1',
                            'uom'        => $item['SalUnitMsr'] ?? 'Pcs',
                            'per_kg'     => $item['Perkg'] ?? null,
                            'suom_entry' => $item['SUoMEntry'] ?? null,
                        ];
                    }
                    return $items;
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch items from SAP: ' . $e->getMessage());
        }

        // Fallback to local items table
        $localItems = \App\Models\Item::query();
        if ($search) {
            $localItems->where(function ($q) use ($search) {
                $q->where('item_code', 'like', "%{$search}%")
                  ->orWhere('item_name', 'like', "%{$search}%");
            });
        }

        return $localItems->limit(50)->get()->map(function ($it) {
            return [
                'item_code'  => $it->item_code,
                'item_name'  => $it->item_name,
                'uom_entry'  => '-1',
                'uom_code'   => '-1',
                'uom'        => $it->sal_unit_msr ?: 'Pcs',
                'per_kg'     => $it->per_kg,
                'suom_entry' => $it->suom_entry ? (string)$it->suom_entry : null,
            ];
        })->toArray();
    }

    /**
     * Build SAP payload for addpr API endpoint.
     */
    public function buildSapPayload(array $payload, ?int $userId = null): array
    {
        $user = auth()->user();
        $series = (string) ($payload['Series'] ?? $payload['series'] ?? $payload['pr_number'] ?? '4876');
        $reqType = (string) ($payload['ReqType'] ?? $payload['req_type'] ?? '12'); // hardcode 12
        $requester = (string) ($payload['Requester'] ?? $payload['requester'] ?? $payload['requester_code'] ?? ($user?->username ?: ($user?->employee_code ?: 'IND01')));
        $requesterName = (string) ($payload['RequesterName'] ?? $payload['requester_name'] ?? ($user?->name ?: 'Purchasing Balaraja'));
        $department = (string) ($payload['Department'] ?? $payload['department'] ?? '9');

        $docDateInput = $payload['DocDate'] ?? $payload['doc_date'] ?? date('Y-m-d');
        $docDate = date('Y-m-d', strtotime($docDateInput));

        $docDueDateInput = $payload['DocDueDate'] ?? $payload['doc_due_date'] ?? $payload['required_date'] ?? $docDate;
        $docDueDate = date('Y-m-d', strtotime($docDueDateInput));

        $comments = (string) ($payload['Comments'] ?? $payload['comments'] ?? $payload['remarks'] ?? '');
        $userIdVal = (string) ($payload['UserId'] ?? $payload['user_id'] ?? ($userId ? (string)$userId : ($user?->id ? (string)$user->id : '19')));
        $addonId = (string) ($payload['AddonId'] ?? $payload['AddOnId'] ?? $payload['addon_id'] ?? '2'); // hardcode 2

        $details = $payload['Lines'] ?? $payload['lines'] ?? $payload['details'] ?? [];
        $lines = [];

        foreach ($details as $item) {
            $itemCode = (string) ($item['ItemCode'] ?? $item['item_code'] ?? '');

            // Check BOM lookup if itemCode is empty
            if ($itemCode === '') {
                $bomId = $item['bom_id'] ?? $item['production_bom_id'] ?? $item['Bomid'] ?? null;
                if ($bomId) {
                    $bom = ProductionBom::find($bomId);
                    if ($bom && !empty($bom->code)) {
                        $itemCode = $bom->code;
                    }
                }
            }

            if ($itemCode === '') {
                $itemCode = 'JS000009'; // Fallback code
            }

            $pqtReqDateInput = $item['PQTReqDate'] ?? $item['pqt_req_date'] ?? $item['required_date'] ?? $docDueDate;
            $pqtReqDate = date('Y-m-d', strtotime($pqtReqDateInput));

            $quantity = floatval($item['Quantity'] ?? $item['quantity'] ?? 1.0);
            $uomEntry = (string) ($item['UomEntry'] ?? $item['uom_entry'] ?? '-1');
            $uomCode = (string) ($item['UomCode'] ?? $item['uom_code'] ?? '-1');
            $whsCode = (string) ($item['WhsCode'] ?? $item['whs_code'] ?? $item['warehouse_code'] ?? '01');
            $unitMsr = (string) ($item['UnitMsr'] ?? $item['unit_msr'] ?? $item['uom'] ?? 'Pcs');
            $freeTxt = (string) ($item['FreeTxt'] ?? $item['free_txt'] ?? $item['remarks'] ?? 'untuk upgrade');
            $ocrCode = (string) ($item['OcrCode'] ?? $item['ocr_code'] ?? $payload['cost_center'] ?? 'BLR');
            $ocrCode2 = (string) ($item['OcrCode2'] ?? $item['ocr_code2'] ?? $item['ocr_code_2'] ?? 'GRM');
            $ocrCode3 = (string) ($item['OcrCode3'] ?? $item['ocr_code3'] ?? $item['ocr_code_3'] ?? 'PCG');

            $lines[] = [
                'ItemCode' => $itemCode,
                'PQTReqDate' => $pqtReqDate,
                'Quantity' => $quantity,
                'UomEntry' => $uomEntry,
                'UomCode' => $uomCode,
                'WhsCode' => $whsCode,
                'UnitMsr' => $unitMsr,
                'FreeTxt' => $freeTxt,
                'OcrCode' => $ocrCode,
                'OcrCode2' => $ocrCode2,
                'OcrCode3' => $ocrCode3,
            ];
        }

        return [
            'Series' => $series,
            'ReqType' => $reqType,
            'Requester' => $requester,
            'RequesterName' => $requesterName,
            'Department' => $department,
            'DocDate' => $docDate,
            'DocDueDate' => $docDueDate,
            'Comments' => $comments,
            'UserId' => $userIdVal,
            'AddonId' => $addonId,
            'AddOnId' => $addonId,
            'Lines' => $lines,
        ];
    }

    public function create(array $payload, ?int $userId = null): PurchaseRequest
    {
        $sapPayload = $this->buildSapPayload($payload, $userId);

        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');
        $response = Http::timeout(30)->post("{$sapUrl}/api/addpr", $sapPayload);

        if (!$response->successful()) {
            throw new SapException(
                'Gagal menghubungi API SAP addpr. HTTP Status: ' . $response->status(),
                ['sap_payload' => $sapPayload, 'http_status' => $response->status()],
                400
            );
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            $msg = $body['Message'] ?? 'Failed - [AddPurchaseRequest] Error from SAP';
            throw new SapException($msg, $body, 400);
        }

        // Set SAP returned values if present
        if (isset($body['Result'])) {
            if (is_numeric($body['Result'])) {
                $payload['sap_doc_entry'] = (int)$body['Result'];
            } elseif (is_array($body['Result'])) {
                $payload['sap_doc_entry'] = $body['Result']['DocEntry'] ?? null;
                $payload['sap_doc_num'] = $body['Result']['DocNum'] ?? null;
            }
        }

        // Regex fallback from Message if Result was empty
        if (empty($payload['sap_doc_entry']) && !empty($body['Message'])) {
            if (preg_match('/DocEntr(?:y|ies):\s*([0-9]+)/i', $body['Message'], $mEntry)) {
                $payload['sap_doc_entry'] = (int)$mEntry[1];
            }
            if (preg_match('/DocNum(?:s)?:\s*([A-Za-z0-9_-]+)/i', $body['Message'], $mNum)) {
                $payload['sap_doc_num'] = (string)$mNum[1];
            }
        }

        if (empty($payload['pr_number'])) {
            $payload['pr_number'] = $payload['sap_doc_num'] ?? $payload['series'] ?? $sapPayload['Series'] ?? ('PR-' . date('YmdHis'));
        }
        if (empty($payload['department'])) {
            $payload['department'] = $sapPayload['Department'];
        }
        if (empty($payload['cost_center'])) {
            $payload['cost_center'] = $sapPayload['Lines'][0]['OcrCode'] ?? 'BLR';
        }
        if (empty($payload['doc_date'])) {
            $payload['doc_date'] = $sapPayload['DocDate'];
        }
        if (empty($payload['doc_due_date'])) {
            $payload['doc_due_date'] = $sapPayload['DocDueDate'];
        }
        if (empty($payload['status'])) {
            $payload['status'] = 'SUBMITTED';
        }
        $payload['series'] = $sapPayload['Series'];
        $payload['req_type'] = $sapPayload['ReqType'];
        $payload['requester'] = $sapPayload['Requester'];
        $payload['requester_name'] = $sapPayload['RequesterName'];
        $payload['comments'] = $sapPayload['Comments'];
        $payload['user_id'] = $sapPayload['UserId'];
        $payload['addon_id'] = $sapPayload['AddonId'];

        $details = $payload['details'] ?? $payload['lines'] ?? $payload['Lines'] ?? [];
        unset($payload['details'], $payload['lines'], $payload['Lines']);

        $normalizedDetails = [];
        foreach ($details as $idx => $item) {
            $sapLine = $sapPayload['Lines'][$idx] ?? [];
            $itemCode = $sapLine['ItemCode'] ?? ($item['item_code'] ?? $item['ItemCode'] ?? null);
            $qty = floatval($sapLine['Quantity'] ?? ($item['quantity'] ?? $item['Quantity'] ?? 1));
            $unitPrice = floatval($item['unit_price'] ?? $item['UnitPrice'] ?? 0);

            $normalizedDetails[] = [
                'master_budget_id' => $item['master_budget_id'] ?? null,
                'bom_id' => $item['bom_id'] ?? $item['production_bom_id'] ?? $item['Bomid'] ?? null,
                'item_code' => $itemCode,
                'item_description' => $item['item_description'] ?? $item['ItemDescription'] ?? $itemCode,
                'pqt_req_date' => $sapLine['PQTReqDate'] ?? null,
                'quantity' => $qty,
                'uom' => $sapLine['UnitMsr'] ?? ($item['uom'] ?? null),
                'uom_entry' => $sapLine['UomEntry'] ?? null,
                'uom_code' => $sapLine['UomCode'] ?? null,
                'whs_code' => $sapLine['WhsCode'] ?? null,
                'unit_msr' => $sapLine['UnitMsr'] ?? null,
                'unit_price' => $unitPrice,
                'free_txt' => $sapLine['FreeTxt'] ?? null,
                'ocr_code' => $sapLine['OcrCode'] ?? null,
                'ocr_code2' => $sapLine['OcrCode2'] ?? null,
                'ocr_code3' => $sapLine['OcrCode3'] ?? null,
                'remarks' => $item['remarks'] ?? null,
            ];
        }

        if ($userId) {
            $payload['created_by'] = $userId;
            $payload['updated_by'] = $userId;
            $payload['requester_id'] = $payload['requester_id'] ?? $userId;
        }

        return $this->repository->create($payload, $normalizedDetails);
    }

    public function update(int $id, array $payload, ?int $userId = null): ?PurchaseRequest
    {
        $details = $payload['details'] ?? null;
        unset($payload['details']);

        if ($userId) {
            $payload['updated_by'] = $userId;
        }

        return $this->repository->update($id, $payload, $details);
    }

    public function updateStatus(int $id, string $status, ?int $userId = null): ?PurchaseRequest
    {
        return $this->repository->updateStatus($id, $status, $userId);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
