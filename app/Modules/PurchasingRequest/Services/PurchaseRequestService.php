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
     * Build SAP payload for addpr API endpoint.
     */
    public function buildSapPayload(array $payload, ?int $userId = null): array
    {
        $series = (string) ($payload['series'] ?? $payload['Series'] ?? $payload['pr_number'] ?? '4876');
        $reqType = '12'; // hardcode 12
        $requester = (string) ($payload['requester'] ?? $payload['Requester'] ?? $payload['requester_code'] ?? 'IND01');
        $requesterName = (string) ($payload['requester_name'] ?? $payload['RequesterName'] ?? 'Purchasing Balaraja');
        $department = (string) ($payload['department'] ?? $payload['Department'] ?? '9');

        $docDateInput = $payload['doc_date'] ?? $payload['DocDate'] ?? date('Y-m-d');
        $docDate = date('Y-m-d', strtotime($docDateInput));

        $docDueDateInput = $payload['doc_due_date'] ?? $payload['DocDueDate'] ?? $payload['required_date'] ?? $docDate;
        $docDueDate = date('Y-m-d', strtotime($docDueDateInput));

        $comments = (string) ($payload['comments'] ?? $payload['Comments'] ?? $payload['remarks'] ?? '');
        $userIdVal = (string) ($payload['user_id'] ?? $payload['UserId'] ?? ($userId ? (string)$userId : '19'));
        $addonId = '2'; // hardcode 2

        $details = $payload['details'] ?? $payload['lines'] ?? $payload['Lines'] ?? [];
        $lines = [];

        foreach ($details as $item) {
            $itemCode = (string) ($item['item_code'] ?? $item['ItemCode'] ?? '');

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

            $pqtReqDateInput = $item['pqt_req_date'] ?? $item['PQTReqDate'] ?? $item['required_date'] ?? $docDueDate;
            $pqtReqDate = date('Y-m-d', strtotime($pqtReqDateInput));

            $quantity = floatval($item['quantity'] ?? $item['Quantity'] ?? 1.0);
            $uomEntry = (string) ($item['uom_entry'] ?? $item['UomEntry'] ?? '-1');
            $uomCode = (string) ($item['uom_code'] ?? $item['UomCode'] ?? '-1');
            $whsCode = (string) ($item['whs_code'] ?? $item['WhsCode'] ?? $item['warehouse_code'] ?? '01');
            $unitMsr = (string) ($item['unit_msr'] ?? $item['UnitMsr'] ?? $item['uom'] ?? 'Pcs');
            $freeTxt = (string) ($item['free_txt'] ?? $item['FreeTxt'] ?? $item['remarks'] ?? 'untuk upgrade');
            $ocrCode = (string) ($item['ocr_code'] ?? $item['OcrCode'] ?? $payload['cost_center'] ?? 'BLR');
            $ocrCode2 = (string) ($item['ocr_code_2'] ?? $item['ocr_code2'] ?? $item['OcrCode2'] ?? 'GRM');
            $ocrCode3 = (string) ($item['ocr_code_3'] ?? $item['ocr_code3'] ?? $item['OcrCode3'] ?? 'PCG');

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
            'Lines' => $lines,
        ];
    }

    public function create(array $payload, ?int $userId = null): PurchaseRequest
    {
        $sapPayload = $this->buildSapPayload($payload, $userId);

        $sapUrl = config('services.sap.url');
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

        if (empty($payload['pr_number'])) {
            $payload['pr_number'] = $payload['series'] ?? $sapPayload['Series'] ?? ('PR-' . date('YmdHis'));
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
