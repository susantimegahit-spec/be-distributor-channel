<?php

namespace App\Modules\SalesOrder\Services;

use App\Models\SalesOrder;
use App\Modules\SalesOrder\Repositories\SalesOrderRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Exception;

class SalesOrderService
{
    protected SalesOrderRepositoryInterface $salesOrderRepository;
    protected AuditLogService $auditLogService;

    /**
     * SalesOrderService constructor.
     *
     * @param  SalesOrderRepositoryInterface  $salesOrderRepository
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(
        SalesOrderRepositoryInterface $salesOrderRepository,
        AuditLogService $auditLogService
    ) {
        $this->salesOrderRepository = $salesOrderRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get all sales orders.
     *
     * @param  int|null  $distributorId
     * @param  string|null  $status
     * @return Collection
     */
    public function getAllOrders(?int $distributorId = null, ?string $status = null): Collection
    {
        $filters = [];
        if ($distributorId) {
            $filters['distributor_id'] = $distributorId;
        }
        if ($status) {
            $filters['status'] = $status;
        }

        return $this->salesOrderRepository->getAll($filters);
    }

    /**
     * Get sales order by ID.
     *
     * @param  int  $id
     * @param  int|null  $distributorId
     * @return SalesOrder|null
     */
    public function getOrderById(int $id, ?int $distributorId = null): ?SalesOrder
    {
        $salesOrder = $this->salesOrderRepository->getById($id);

        if ($salesOrder && $distributorId && $salesOrder->distributor_id !== $distributorId) {
            return null;
        }

        return $salesOrder;
    }

    /**
     * Create a new sales order draft.
     *
     * @param  array  $data
     * @param  int  $userId
     * @param  int  $distributorId
     * @return SalesOrder
     */
    public function createDraft(array $data, int $userId, int $distributorId): SalesOrder
    {
        $distributor = \App\Models\Distributor::find($distributorId);
        
        // Auto-generate order number
        $data['order_no'] = $this->generateOrderNumber();
        $data['distributor_id'] = $distributorId;
        $data['customer_name'] = $distributor ? $distributor->name : 'Unknown Customer';
        $data['status'] = $data['status'] ?? 'DRAFT';
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        // Calculate doc_total as sum of line_total
        $docTotal = 0;
        foreach ($data['lines'] as $line) {
            $docTotal += $line['line_total'];
        }
        $data['doc_total'] = $docTotal;

        $salesOrder = $this->salesOrderRepository->create($data);

        $this->auditLogService->log(
            $userId,
            'CREATE_SALES_ORDER_DRAFT',
            "Created Sales Order draft {$salesOrder->order_no}."
        );

        return $salesOrder;
    }

    /**
     * Update an existing draft sales order.
     *
     * @param  int  $id
     * @param  array  $data
     * @param  int  $userId
     * @param  int  $distributorId
     * @return SalesOrder
     *
     * @throws ValidationException
     */
    public function updateDraft(int $id, array $data, int $userId, int $distributorId): SalesOrder
    {
        $salesOrder = $this->getOrderById($id, $distributorId);

        if (!$salesOrder) {
            throw ValidationException::withMessages([
                'order' => ['Sales order tidak ditemukan.'],
            ]);
        }

        if ($salesOrder->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'order' => ['Hanya sales order dengan status DRAFT yang dapat diedit.'],
            ]);
        }

        $distributor = \App\Models\Distributor::find($distributorId);
        $data['customer_name'] = $distributor ? $distributor->name : $salesOrder->customer_name;
        $data['status'] = $data['status'] ?? $salesOrder->status;
        $data['updated_by'] = $userId;

        // Recalculate doc_total
        $docTotal = 0;
        foreach ($data['lines'] as $line) {
            $docTotal += $line['line_total'];
        }
        $data['doc_total'] = $docTotal;

        $updatedOrder = $this->salesOrderRepository->update($salesOrder, $data);

        $this->auditLogService->log(
            $userId,
            'UPDATE_SALES_ORDER_DRAFT',
            "Updated Sales Order draft {$updatedOrder->order_no}."
        );

        return $updatedOrder;
    }

    /**
     * Delete a draft sales order.
     *
     * @param  int  $id
     * @param  int  $userId
     * @param  int  $distributorId
     * @return bool
     *
     * @throws ValidationException
     */
    public function deleteDraft(int $id, int $userId, int $distributorId): bool
    {
        $salesOrder = $this->getOrderById($id, $distributorId);

        if (!$salesOrder) {
            throw ValidationException::withMessages([
                'order' => ['Sales order tidak ditemukan.'],
            ]);
        }

        if ($salesOrder->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'order' => ['Hanya sales order dengan status DRAFT yang dapat dihapus.'],
            ]);
        }

        $orderNo = $salesOrder->order_no;
        $deleted = $this->salesOrderRepository->delete($salesOrder);

        if ($deleted) {
            $this->auditLogService->log(
                $userId,
                'DELETE_SALES_ORDER_DRAFT',
                "Deleted Sales Order draft {$orderNo}."
            );
        }

        return $deleted;
    }

    /**
     * Generate unique sales order number.
     *
     * @return string
     */
    protected function generateOrderNumber(): string
    {
        $prefix = 'SO-' . date('Ymd') . '-';
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Ensure uniqueness
        while (SalesOrder::where('order_no', $prefix . $random)->exists()) {
            $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        return $prefix . $random;
    }

    /**
     * Post a Sales Order to SAP.
     *
     * @param  int  $id
     * @param  int|null  $userId
     * @return array
     * @throws Exception
     */
    public function postToSap(int $id, ?int $userId = null): array
    {
        $salesOrder = $this->getOrderById($id);

        if (!$salesOrder) {
            throw new Exception('Sales order tidak ditemukan.');
        }

        // Prepare SAP payload mapping
        $payload = [
            'CardCode' => $salesOrder->card_code,
            'CardName' => $salesOrder->customer_name,
            'DocDate' => $salesOrder->doc_date ? $salesOrder->doc_date->format('Y-m-d') : null,
            'DocDueDate' => $salesOrder->doc_due_date ? $salesOrder->doc_due_date->format('Y-m-d') : null,
            'NumAtCard' => $salesOrder->po_number,
            'SalesPersonCode' => (int)$salesOrder->slp_code,
            'ContactPersonCode' => (int)$salesOrder->cntct_code,
            'ShipToCode' => $salesOrder->ship_to_code,
            'PayToCode' => $salesOrder->pay_to_code,
            'Address' => $salesOrder->address,
            'Address2' => $salesOrder->address2,
            'Comments' => $salesOrder->comments,
            'U_DiskonCode' => $salesOrder->id_discount,
            'Lines' => $salesOrder->details->map(function ($line) {
                return [
                    'ItemCode' => $line->item_code,
                    'Quantity' => (float)$line->quantity,
                    'UnitPrice' => (float)$line->unit_price,
                    'WarehouseCode' => $line->whs_code,
                    'VatGroup' => $line->vat_group,
                    'DiscountPercent' => (float)$line->disc_percent,
                    'FreeText' => $line->free_text,
                    'CostingCode' => $line->ocr_code,
                    'CostingCode2' => $line->ocr_code2,
                    'CostingCode3' => $line->ocr_code3,
                    'UoMEntry' => $line->uom_entry ? (int)$line->uom_entry : null,
                ];
            })->toArray()
        ];

        $requestJson = json_encode($payload);
        $responseJson = null;
        $status = 'FAILED';
        $errorMessage = null;

        try {
            $response = Http::timeout(15)->post('http://103.18.133.187:3100/api/addso', $payload);
            $responseJson = $response->body();

            if (!$response->successful()) {
                throw new Exception('Gagal menghubungi API SAP untuk membuat Sales Order.');
            }

            $body = $response->json();
            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                throw new Exception('API SAP addso mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
            }

            $status = 'SUCCESS';

            // Get DocEntry and DocNum if returned in Result
            $sapDocEntry = $body['Result'][0]['DocEntry'] ?? null;
            $sapDocNum = $body['Result'][0]['DocNum'] ?? null;

            // Update Sales Order on Success
            $salesOrder->update([
                'status' => 'APPROVED',
                'sap_doc_entry' => $sapDocEntry,
                'sap_doc_num' => $sapDocNum,
                'integrated_at' => now(),
                'sap_error' => null,
            ]);

            if ($userId) {
                $this->auditLogService->log(
                    $userId,
                    'POST_SALES_ORDER_SAP_SUCCESS',
                    "Successfully integrated Sales Order {$salesOrder->order_no} to SAP."
                );
            }

            // Save integration log
            \App\Models\SalesOrderIntegrationLog::create([
                'sales_order_id' => $salesOrder->id,
                'request_json' => $requestJson,
                'response_json' => $responseJson,
                'status' => 'SUCCESS',
                'error_message' => null,
            ]);

            return [
                'success' => true,
                'message' => 'Sales Order berhasil dikirim ke SAP.',
                'sap_response' => $body
            ];

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            // Update Sales Order on Failure
            $salesOrder->update([
                'status' => 'FAILED',
                'sap_error' => $errorMessage,
            ]);

            if ($userId) {
                $this->auditLogService->log(
                    $userId,
                    'POST_SALES_ORDER_SAP_FAILED',
                    "Failed to integrate Sales Order {$salesOrder->order_no} to SAP. Error: {$errorMessage}"
                );
            }

            // Save integration log
            \App\Models\SalesOrderIntegrationLog::create([
                'sales_order_id' => $salesOrder->id,
                'request_json' => $requestJson,
                'response_json' => $responseJson,
                'status' => 'FAILED',
                'error_message' => $errorMessage,
            ]);

            throw $e;
        }
    }
}
