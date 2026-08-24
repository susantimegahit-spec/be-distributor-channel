<?php

namespace App\Modules\SalesOrder\Services;

use App\Models\SalesOrder;
use App\Models\Distributor;
use App\Models\SalesOrderDetail;
use App\Models\SapDiscountHeader;
use App\Models\SapDiscountDetail;
use App\Modules\SalesOrder\Repositories\SalesOrderRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * @param  string|null  $cardCode
     * @return Collection
     */
    public function getAllOrders(
        int|array|null $distributorId = null,
        ?string $status = null,
        ?string $cardCode = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): Collection {
        $filters = [];
        if ($distributorId) {
            $filters['distributor_id'] = $distributorId;
        }
        if ($status) {
            $filters['status'] = $status;
        }
        if ($cardCode) {
            $filters['card_code'] = $cardCode;
        }
        if ($startDate) {
            $filters['start_date'] = $startDate;
        }
        if ($endDate) {
            $filters['end_date'] = $endDate;
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
    public function getOrderById(int $id, $distributorId = null): ?SalesOrder
    {
        $salesOrder = $this->salesOrderRepository->getById($id);

        if ($salesOrder && $distributorId) {
            if (is_array($distributorId)) {
                if (!in_array($salesOrder->distributor_id, $distributorId)) {
                    return null;
                }
            } elseif ($salesOrder->distributor_id !== $distributorId) {
                return null;
            }
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

        // Extract attachment(s)
        $rawAttachments = $data['attachment'] ?? $data['attachments'] ?? null;
        unset($data['attachment'], $data['attachments']);

        // Auto-generate order number
        $data['order_no'] = $this->generateOrderNumber();
        $data['distributor_id'] = $distributorId;
        $data['customer_name'] = $distributor ? $distributor->name : 'Unknown Customer';
        $data['status'] = $data['status'] ?? 'DRAFT';
        $data['approval_id'] = 1; // 1 = DRAFT
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        // Calculate doc_total as sum of line_total
        $docTotal = 0;
        foreach ($data['lines'] as $line) {
            $docTotal += $line['line_total'];
        }
        $data['doc_total'] = $docTotal;

        $salesOrder = $this->salesOrderRepository->create($data);

        // Store attachment(s) if present
        $attachmentsList = is_array($rawAttachments) ? $rawAttachments : ($rawAttachments ? [$rawAttachments] : []);
        foreach ($attachmentsList as $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                try {
                    $this->storeAttachment($salesOrder, $file, $userId);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to store Sales Order attachment: " . $e->getMessage());
                }
            }
        }

        $this->auditLogService->log(
            $userId,
            'SalesOrder',
            $salesOrder->id,
            'CREATE',
            null,
            $salesOrder->toArray()
        );

        try {
            event(new \App\Events\OrderCreated($salesOrder));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to broadcast OrderCreated event: " . $e->getMessage());
        }

        return $salesOrder->load('attachments');
    }

    /**
     * Update an existing Sales Order draft.
     *
     * @param  int  $id
     * @param  array  $data
     * @param  int  $userId
     * @param  int  $distributorId
     * @return SalesOrder
     * @throws ValidationException
     */
    public function updateOrder(int $id, array $data, int $userId, int $distributorId): SalesOrder
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

        // Extract attachment(s)
        $rawAttachments = $data['attachment'] ?? $data['attachments'] ?? null;
        unset($data['attachment'], $data['attachments']);

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

        // Store attachment(s) if present
        $attachmentsList = is_array($rawAttachments) ? $rawAttachments : ($rawAttachments ? [$rawAttachments] : []);
        if (!empty($attachmentsList)) {
            // Delete old files from storage and database
            $oldAttachments = $updatedOrder->attachments;
            foreach ($oldAttachments as $oldAttachment) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($oldAttachment->file_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldAttachment->file_path);
                }
                $oldAttachment->delete();
            }

            foreach ($attachmentsList as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    try {
                        $this->storeAttachment($updatedOrder, $file, $userId);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to update Sales Order attachment: " . $e->getMessage());
                    }
                }
            }
        }

        $this->auditLogService->log(
            $userId,
            'UPDATE_SALES_ORDER_DRAFT',
            "Updated Sales Order draft {$updatedOrder->order_no}."
        );

        return $updatedOrder->load('attachments');
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
     * Normalize incoming request payload to snake_case database schema
     *
     * @param  array  $payload
     * @return array
     */
    public function normalizePayload(array $payload): array
    {
        $normalized = [];

        // Header mapping
        $headerMap = [
            'card_code' => 'card_code',
            'CardCode' => 'card_code',
            'po_number' => 'po_number',
            'NumAtCard' => 'po_number',
            'doc_date' => 'doc_date',
            'DocDate' => 'doc_date',
            'doc_due_date' => 'doc_due_date',
            'DocDueDate' => 'doc_due_date',
            'slp_code' => 'slp_code',
            'SlpCode' => 'slp_code',
            'cntct_code' => 'cntct_code',
            'CntctCode' => 'cntct_code',
            'pay_to_code' => 'pay_to_code',
            'PayToCode' => 'pay_to_code',
            'address' => 'address',
            'Address' => 'address',
            'ship_to_code' => 'ship_to_code',
            'ShipToCode' => 'ship_to_code',
            'address2' => 'address2',
            'Address2' => 'address2',
            'comments' => 'comments',
            'Comments' => 'comments',
            'id_discount' => 'id_discount',
            'IdDiskon' => 'id_discount',
            'series' => 'series',
            'Series' => 'series',
            'series_name' => 'series_name',
            'SeriesName' => 'series_name',
            'status' => 'status',
            'Status' => 'status',
        ];

        foreach ($headerMap as $incomingKey => $targetKey) {
            if (array_key_exists($incomingKey, $payload)) {
                $value = $payload[$incomingKey];
                // Treat empty string as null for nullable/numeric fields
                if (in_array($targetKey, ['slp_code', 'cntct_code', 'id_discount', 'series']) && $value === '') {
                    $value = null;
                }
                $normalized[$targetKey] = $value;
            }
        }

        // Lines mapping
        $incomingLines = $payload['lines'] ?? $payload['Lines'] ?? null;
        if (is_array($incomingLines)) {
            $normalized['lines'] = [];
            $lineMap = [
                'item_code' => 'item_code',
                'ItemCode' => 'item_code',
                'quantity' => 'quantity',
                'Quantity' => 'quantity',
                'unit_msr' => 'unit_msr',
                'UnitMsr' => 'unit_msr',
                'uom_entry' => 'uom_entry',
                'UomEntry' => 'uom_entry',
                'whs_code' => 'whs_code',
                'WhsCode' => 'whs_code',
                'unit_price' => 'unit_price',
                'UnitPrice' => 'unit_price',
                'disc_percent' => 'disc_percent',
                'DiscPrcnt' => 'disc_percent',
                'vat_group' => 'vat_group',
                'VatGroup' => 'vat_group',
                'line_total' => 'line_total',
                'LineTotal' => 'line_total',
                'free_text' => 'free_text',
                'FreeTxt' => 'free_text',
                'ocr_code' => 'ocr_code',
                'OcrCode' => 'ocr_code',
                'ocr_code2' => 'ocr_code2',
                'OcrCode2' => 'ocr_code2',
                'ocr_code3' => 'ocr_code3',
                'OcrCode3' => 'ocr_code3',
            ];

            foreach ($incomingLines as $line) {
                if (!is_array($line)) continue;
                $normalizedLine = [];
                foreach ($lineMap as $incomingKey => $targetKey) {
                    if (array_key_exists($incomingKey, $line)) {
                        $value = $line[$incomingKey];
                        // Treat empty string as null for nullable/numeric fields
                        if (in_array($targetKey, ['uom_entry', 'whs_code', 'vat_group', 'ocr_code', 'ocr_code2', 'ocr_code3']) && $value === '') {
                            $value = null;
                        }
                        $normalizedLine[$targetKey] = $value;
                    }
                }
                $normalized['lines'][] = $normalizedLine;
            }
        }

        return $normalized;
    }

    /**
     * Create a new Sales Order and post it to SAP.
     *
     * @param  array  $payload
     * @param  int  $userId
     * @return array
     * @throws Exception
     */
    public function postNewToSap(array $payload, int $userId): array
    {
        $normalized = $this->normalizePayload($payload);

        // Find distributor by card_code
        $cardCode = $normalized['card_code'] ?? null;
        if (!$cardCode) {
            throw new Exception('CardCode wajib diisi.');
        }

        $distributor = \App\Models\Distributor::where('code_customer', $cardCode)->first();
        if (!$distributor) {
            throw new Exception("Data distributor dengan CardCode {$cardCode} tidak terdaftar.");
        }

        // Extract attachment
        $attachment = $payload['attachment'] ?? null;

        // Set draft values but will post immediately
        $normalized['order_no'] = $this->generateOrderNumber();
        $normalized['distributor_id'] = $distributor->id;
        $normalized['customer_name'] = $distributor->name;
        $normalized['status'] = 'WAITING_APPROVAL'; // Initial status before SAP
        $normalized['created_by'] = $userId;
        $normalized['updated_by'] = $userId;

        // Calculate doc_total
        $docTotal = 0;
        if (isset($normalized['lines']) && is_array($normalized['lines'])) {
            foreach ($normalized['lines'] as $line) {
                $docTotal += $line['line_total'] ?? 0;
            }
        }
        $normalized['doc_total'] = $docTotal;

        // Create in local DB
        $salesOrder = $this->salesOrderRepository->create($normalized);

        // Store attachment if present
        if ($attachment instanceof \Illuminate\Http\UploadedFile) {
            try {
                $this->storeAttachment($salesOrder, $attachment, $userId);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to store Sales Order attachment: " . $e->getMessage());
            }
        }

        try {
            event(new \App\Events\OrderCreated($salesOrder));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to broadcast OrderCreated event: " . $e->getMessage());
        }

        // Post to SAP
        return $this->postToSap($salesOrder->id, $userId);
    }

    /**
     * Post a Sales Order to SAP.
     *
     * @param  int  $id
     * @param  int|null  $userId
     * @param  array|null  $updateData
     * @return array
     * @throws Exception
     */
    public function postToSap(int $id, ?int $userId = null, ?array $updateData = null): array
    {
        $salesOrder = $this->getOrderById($id);

        if (!$salesOrder) {
            throw new Exception('Sales order tidak ditemukan.');
        }

        // Apply update data locally if passed
        if ($updateData) {
            $normalized = $this->normalizePayload($updateData);

            // Ignore status from update body
            if (isset($normalized['status'])) {
                unset($normalized['status']);
            }

            if (isset($normalized['lines']) && is_array($normalized['lines'])) {
                $docTotal = 0;
                foreach ($normalized['lines'] as $line) {
                    $docTotal += $line['line_total'] ?? 0;
                }
                $normalized['doc_total'] = $docTotal;

                // Update using repository (Cascades details delete & recreate)
                $salesOrder = $this->salesOrderRepository->update($salesOrder, $normalized);
            } else {
                // Update header properties
                $salesOrder->update($normalized);
            }
        }

        // Prepare SAP payload mapping
        $payload = [
            'CardCode' => $salesOrder->card_code,
            'NumAtCard' => $salesOrder->po_number,
            'DocDate' => $salesOrder->doc_date ? $salesOrder->doc_date->format('Y-m-d') : null,
            'DocDueDate' => $salesOrder->doc_due_date ? $salesOrder->doc_due_date->format('Y-m-d') : null,
            'SlpCode' => (int)$salesOrder->slp_code,
            'CntctCode' => (int)$salesOrder->cntct_code,
            'PayToCode' => $salesOrder->pay_to_code,
            'Address' => $salesOrder->address,
            'ShipToCode' => $salesOrder->ship_to_code,
            'Address2' => $salesOrder->address2,
            'Comments' => $salesOrder->comments,
            'IdDiskon' => $salesOrder->id_discount,
            'UserId' => $salesOrder->sales_pic_id ? (int)$salesOrder->sales_pic_id : null,
            'AddonId' => 2,
            'Series' => $salesOrder->series ? (int)$salesOrder->series : null,
            'DocTotal' => $salesOrder->doc_total_after_discount,
            'Lines' => $salesOrder->details->map(function ($line) {
                return [
                    'ItemCode' => $line->item_code,
                    'Quantity' => (float)$line->quantity,
                    'UomEntry' => $line->uom_entry ? (int)$line->uom_entry : null,
                    'DiscPrcnt' => (float)$line->disc_percent,
                    'WhsCode' => $line->whs_code,
                    'UnitMsr' => $line->unit_msr,
                    'UnitPrice' => (float)$line->unit_price,
                    'VatGroup' => $line->vat_group,
                    'LineTotal' => (float)$line->line_total,
                    'FreeTxt' => $line->free_text,
                    'OcrCode' => $line->ocr_code,
                    'OcrCode2' => $line->ocr_code2,
                    'OcrCode3' => $line->ocr_code3,
                ];
            })->toArray()
        ];

        $requestJson = json_encode($payload);
        $responseJson = null;
        $status = 'FAILED';
        $errorMessage = null;

        try {
            // Integrate UDO Discount to SAP if id_discount is set
            if ($salesOrder->id_discount) {
                $sapDiscount = \App\Models\SapDiscountHeader::with('details')
                    ->where('discount_code', $salesOrder->id_discount)
                    ->first();

                if (!$sapDiscount) {
                    throw new Exception("Data diskon dengan ID {$salesOrder->id_discount} tidak ditemukan di database.");
                }

                $discountLines = [];
                if ($sapDiscount->details) {
                    foreach ($sapDiscount->details as $detail) {
                        $discountLines[] = [
                            'TypeDiscount' => $detail->type_discount,
                            'Persentase' => (float)$detail->percentage,
                            'TotalDiskon' => (float)$detail->total_discount,
                            'Remarks' => $detail->remarks ?? '',
                        ];
                    }
                }

                $discountPayload = [
                    'Code' => $salesOrder->id_discount,
                    'Name' => '',
                    'CardCode' => $salesOrder->card_code,
                    'CardName' => $salesOrder->customer_name,
                    'TotalSO' => 0,
                    'Lines' => $discountLines
                ];
                $sapUrl = config('services.sap.url');
                $discountResponse = Http::timeout(15)->post("{$sapUrl}/api/addudodiskon", $discountPayload);

                if (!$discountResponse->successful()) {
                    throw new Exception('Gagal menghubungi API SAP addudodiskon untuk sinkronisasi diskon.');
                }

                $discountBody = $discountResponse->json();
                if (isset($discountBody['ErrorCode']) && $discountBody['ErrorCode'] !== 0) {
                    throw new Exception('API SAP addudodiskon mengembalikan error: ' . ($discountBody['Message'] ?? 'Unknown error'));
                }
            }
            $sapUrl = config('services.sap.url');
            $response = Http::timeout(15)->post("{$sapUrl}/api/addso", $payload);
            $responseJson = $response->body();

            if (!$response->successful()) {
                throw new Exception('Gagal menghubungi API SAP untuk membuat Sales Order.');
            }

            $body = $response->json();
            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                throw new Exception('API SAP addso mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
            }

            $status = 'SUCCESS';

            // Get DocEntry and DocNum with fallbacks for different key casings
            $result = $body['Result'][0] ?? $body['result'][0] ?? null;
            $sapDocEntry = null;
            $sapDocNum = null;

            if ($result) {
                $sapDocEntry = $result['DocEntry'] ?? $result['docEntry'] ?? $result['doc_entry'] ?? $result['docentry'] ?? null;
                $sapDocNum = $result['DocNum'] ?? $result['docNum'] ?? $result['doc_num'] ?? $result['docnum'] ?? null;
            }

            // Fallback: extract from Message if not found in Result
            $message = $body['Message'] ?? $body['message'] ?? '';
            if (empty($sapDocNum) && !empty($message)) {
                if (preg_match('/DocNum:\s*([A-Za-z0-9_-]+)/i', $message, $matches)) {
                    $sapDocNum = $matches[1];
                }
            }
            if (empty($sapDocEntry) && !empty($message)) {
                if (preg_match('/DocEntry:\s*(\d+)/i', $message, $matches)) {
                    $sapDocEntry = (int)$matches[1];
                }
            }

            // Update Sales Order on Success
            $salesOrder->update([
                'status' => 'ORDER_APPROVED',
                'approval_id' => 6, // 6 = COMPLETED (ORDER_APPROVED)
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
                'sap_response' => $body,
                'sap_payload' => $payload
            ];
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            // Update Sales Order on Failure (status remains unchanged, only record the error)
            $salesOrder->update([
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

    /**
     * Store the sales order attachment file and save database record.
     *
     * @param  \App\Models\SalesOrder  $salesOrder
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  int  $userId
     * @return \App\Models\SalesOrderAttachment
     */
    protected function storeAttachment(\App\Models\SalesOrder $salesOrder, \Illuminate\Http\UploadedFile $file, int $userId): \App\Models\SalesOrderAttachment
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $timestamp = time();
        $random = \Illuminate\Support\Str::random(6);

        // Rename format: {YYYYMMDD}_{timestamp}_{random}_{order_no}.{extension}
        $sanitizedOrderNo = str_replace(['/', '\\', ' '], '_', $salesOrder->order_no);
        $fileName = date('Ymd') . '_' . $timestamp . '_' . $random . '_' . $sanitizedOrderNo . '.' . $extension;

        // Store file in 'public/attachments/order' directory
        $path = $file->storeAs('attachments/order', $fileName, 'public');

        $soAttachment = \App\Models\SalesOrderAttachment::create([
            'sales_order_id' => $salesOrder->id,
            'file_name' => $originalName,
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);

        // Link attachment to associated CMO if exists
        if ($salesOrder->order_no) {
            $cmo = \App\Models\CustomerMonthlyOrder::where('order_no', $salesOrder->order_no)->first();
            if ($cmo) {
                $soAttachment->update([
                    'customer_monthly_order_id' => $cmo->id,
                ]);
            }
        }

        return $soAttachment;
    }

    /**
     * Submit a draft sales order to OM.
     */
    public function submitOrder(int $id, int $userId): SalesOrder
    {
        $salesOrder = $this->getOrderById($id);

        if (!$salesOrder) {
            throw new Exception('Sales order tidak ditemukan.');
        }

        if ($salesOrder->approval_id !== SalesOrder::STAGE_DRAFT) {
            throw new Exception('Hanya sales order draft yang dapat dikirim.');
        }

        $salesOrder->update([
            'status' => 'WAITING_OM',
            'approval_id' => SalesOrder::STAGE_WAITING_OM,
            'submitted_at' => now(),
        ]);

        \App\Models\SalesOrderApprovalHistory::create([
            'sales_order_id' => $salesOrder->id,
            'approval_id_before' => SalesOrder::STAGE_DRAFT,
            'approval_id_after' => SalesOrder::STAGE_WAITING_OM,
            'action' => 'SUBMIT',
            'user_id' => $userId,
        ]);

        $this->auditLogService->log(
            $userId,
            'SUBMIT_SALES_ORDER',
            "Submitted Sales Order {$salesOrder->order_no} to OM."
        );

        $this->sendStageNotification($salesOrder, SalesOrder::STAGE_WAITING_OM);

        return $salesOrder->load('approval', 'approvalHistories.user');
    }

    /**
     * Approve a sales order to the next stage.
     */
    public function approveOrder(int $id, int $userId, ?string $notes = null, ?array $payload = null): SalesOrder
    {
        $salesOrder = $this->getOrderById($id);

        if (!$salesOrder) {
            throw new Exception('Sales order tidak ditemukan.');
        }

        if ($salesOrder->approval_id === SalesOrder::STAGE_COMPLETED) {
            throw new Exception('Sales order sudah selesai diproses (Completed).');
        }

        $user = \App\Models\User::findOrFail($userId);
        $roleMenu = \App\Models\RoleMenu::where('role_id', $user->role_id)->first();
        $allowedApprovalId = $roleMenu?->approval_id;

        if (!$allowedApprovalId || $allowedApprovalId !== $salesOrder->approval_id) {
            throw new Exception('Anda tidak memiliki akses untuk menyetujui sales order pada tahap ini.');
        }

        $currentStage = $salesOrder->approval_id;
        $nextStage = $currentStage + 1;

        // If current stage is WAITING_ADMIN_SALES and payload is provided, process updates to lines & header
        if ($currentStage === SalesOrder::STAGE_WAITING_ADMIN_SALES && $payload) {
            $normalizedData = $this->normalizePayload($payload);

            if (!empty($normalizedData['lines']) && is_array($normalizedData['lines'])) {
                $docTotal = 0;
                foreach ($normalizedData['lines'] as $line) {
                    $orderDetail = $salesOrder->details()->where('item_code', $line['item_code'])->first();
                    if ($orderDetail) {
                        $discPercent = $line['disc_percent'] ?? 0.00;
                        $quantity = (float)$orderDetail->quantity;
                        $unitPrice = (float)$orderDetail->unit_price;

                        $vatGroup = array_key_exists('vat_group', $line) ? $line['vat_group'] : $orderDetail->vat_group;
                        $vatRate = 0.00;
                        if ($vatGroup) {
                            $vat = \App\Models\Vat::where('code', $vatGroup)->first();
                            if ($vat) {
                                $vatRate = (float)$vat->rate;
                            }
                        }

                        // Recalculate line total based on discount percentage (without tax)
                        $lineTotalBeforeVat = ($quantity * $unitPrice) * (1 - ($discPercent / 100));
                        $lineTotal = (float)($line['line_total'] ?? $lineTotalBeforeVat);

                        $updateData = [
                            'disc_percent' => $discPercent,
                            'line_total' => $lineTotal,
                        ];

                        // Also update whs_code, vat_group, and ocr_codes if provided in the input payload
                        if (array_key_exists('whs_code', $line)) {
                            $updateData['whs_code'] = $line['whs_code'];
                        }
                        if (array_key_exists('vat_group', $line)) {
                            $updateData['vat_group'] = $line['vat_group'];
                        }
                        if (array_key_exists('ocr_code', $line)) {
                            $updateData['ocr_code'] = $line['ocr_code'];
                        }
                        if (array_key_exists('ocr_code2', $line)) {
                            $updateData['ocr_code2'] = $line['ocr_code2'];
                        }
                        if (array_key_exists('ocr_code3', $line)) {
                            $updateData['ocr_code3'] = $line['ocr_code3'];
                        }

                        $orderDetail->update($updateData);

                        $vatTotal = $lineTotal * ($vatRate / 100);
                        $docTotal += ($lineTotal + $vatTotal);
                    }
                }

                $salesOrder->update([
                    'doc_total' => $docTotal,
                ]);
            }

            if (array_key_exists('id_discount', $normalizedData)) {
                $salesOrder->update([
                    'id_discount' => $normalizedData['id_discount'] ?? $salesOrder->id_discount,
                ]);
            }

            if (array_key_exists('series', $normalizedData)) {
                $salesOrder->update([
                    'series' => $normalizedData['series'] ?? $salesOrder->series,
                ]);
            }

            if (array_key_exists('series_name', $normalizedData)) {
                $salesOrder->update([
                    'series_name' => $normalizedData['series_name'] ?? $salesOrder->series_name,
                ]);
            }

            if (array_key_exists('slp_code', $normalizedData)) {
                $salesOrder->update([
                    'slp_code' => $normalizedData['slp_code'] ?? $salesOrder->slp_code,
                ]);
            }

            if (array_key_exists('cntct_code', $normalizedData)) {
                $salesOrder->update([
                    'cntct_code' => $normalizedData['cntct_code'] ?? $salesOrder->cntct_code,
                ]);
            }
        }

        $statusMap = [
            SalesOrder::STAGE_WAITING_ASM => 'WAITING_ASM',
            SalesOrder::STAGE_WAITING_ADMIN_SALES => 'WAITING_ADMIN_SALES',
            SalesOrder::STAGE_WAITING_FINANCE => 'WAITING_FINANCE',
            SalesOrder::STAGE_COMPLETED => 'ORDER_APPROVED',
        ];

        $nextStatus = $statusMap[$nextStage] ?? 'ORDER_APPROVED';

        $updateAttributes = [
            'status' => $nextStatus,
            'approval_id' => $nextStage,
            'reject_reason' => null, // Clear reject reason on approval
        ];

        if ($currentStage === SalesOrder::STAGE_WAITING_ADMIN_SALES) {
            $updateAttributes['sales_pic_id'] = $userId;
        }

        if ($currentStage === SalesOrder::STAGE_WAITING_FINANCE) {
            $vatRate = 11.00;
            $vat = \App\Models\Vat::where('code', 'S4')->first();
            if ($vat) {
                $vatRate = (float)$vat->rate;
            }

            $docTotal = 0;
            foreach ($salesOrder->details as $orderDetail) {
                $lineTotal = (float)$orderDetail->line_total;
                $vatTotal = $lineTotal * ($vatRate / 100);
                $docTotal += ($lineTotal + $vatTotal);

                $orderDetail->update([
                    'vat_group' => 'S4',
                ]);
            }

            $updateAttributes['doc_total'] = $docTotal;
        }

        $salesOrder->update($updateAttributes);

        \App\Models\SalesOrderApprovalHistory::create([
            'sales_order_id' => $salesOrder->id,
            'approval_id_before' => $currentStage,
            'approval_id_after' => $nextStage,
            'action' => 'APPROVE',
            'user_id' => $userId,
            'notes' => $notes,
        ]);

        $this->auditLogService->log(
            $userId,
            'APPROVE_SALES_ORDER',
            "Approved Sales Order {$salesOrder->order_no} from stage {$currentStage} to {$nextStage}."
        );

        // Stage-specific actions
        try {
            if ($nextStage === SalesOrder::STAGE_COMPLETED) {
                // Integrate to SAP automatically when approved by Finance
                $sapResult = $this->postToSap($salesOrder->id, $userId);
                $salesOrder->setAttribute('sap_payload', $sapResult['sap_payload'] ?? null);

                // Refresh model to get the updated sap_doc_num from database
                $salesOrder->refresh();

                // Record Claim Reward usage to Balance Ledger
                if ($salesOrder->id_discount) {
                    $sapDiscount = \App\Models\SapDiscountHeader::where('discount_code', $salesOrder->id_discount)->first();
                    if ($sapDiscount && $sapDiscount->details) {
                        $ledgerRepository = app(\App\Modules\Claim\Repositories\TrxClaimBalanceLedgerRepositoryInterface::class);

                        foreach ($sapDiscount->details as $detail) {
                            $tempRecord = \Illuminate\Support\Facades\DB::table('trade_promo_temp')
                                ->where('id', $detail->id)
                                ->first();

                            if ($tempRecord) {
                                $batchId = $tempRecord->batch_id;
                                $batch = \App\Models\TrxProgramUploadBatch::find($batchId);
                                $batchNo = $batch ? $batch->batch_no : "-";
                                $actualBatchId = $batch ? $batchId : null;

                                $ledgerRepository->recordTransaction([
                                    'customer_code' => $salesOrder->card_code,
                                    'ref_number' => $salesOrder->sap_doc_num ?: $salesOrder->order_no,
                                    'batch_id' => $actualBatchId,
                                    'transaction_date' => now()->toDateString(),
                                    'type' => 'WITHDRAW',
                                    'debit' => 0.00,
                                    'credit' => (float)$detail->total_discount,
                                    'description' => "Penggunaan Reward (Trade Promo) - Batch " . $batchNo . " pada SO " . ($salesOrder->sap_doc_num ?: $salesOrder->order_no),
                                    'referenceable_id' => $salesOrder->id,
                                    'referenceable_type' => SalesOrder::class,
                                    'created_by' => $user->username ?? 'admin',
                                ]);
                            }
                        }
                    }
                }

                // Send email to the creator (submitter) of the order
                try {
                    $salesOrder->load('attachments');
                    $creator = \App\Models\User::find($salesOrder->created_by);
                    if ($creator && $creator->email) {
                        \Illuminate\Support\Facades\Mail::to($creator->email)
                            ->send(new \App\Mail\FinanceApprovedNotificationMail($salesOrder));
                    }
                } catch (\Exception $eMail) {
                    \Illuminate\Support\Facades\Log::error("Failed to send Finance Approved email notification: " . $eMail->getMessage());
                }
            } else {
                $this->sendStageNotification($salesOrder, $nextStage);
            }
        } catch (\Exception $e) {
            if ($nextStage === SalesOrder::STAGE_COMPLETED) {
                // Revert status and stage back to WAITING_FINANCE so they can try again
                $salesOrder->update([
                    'status' => 'WAITING_FINANCE',
                    'approval_id' => SalesOrder::STAGE_WAITING_FINANCE,
                ]);
            }
            throw $e;
        }

        return $salesOrder->load('approval', 'approvalHistories.user');
    }

    public function rejectOrder(int $id, int $userId, ?string $notes = null): SalesOrder
    {
        if (empty($notes)) {
            $notes = 'Rejected';
        }

        $salesOrder = $this->getOrderById($id);

        if (!$salesOrder) {
            throw new Exception('Sales order tidak ditemukan.');
        }

        if ($salesOrder->approval_id === SalesOrder::STAGE_COMPLETED || $salesOrder->approval_id === SalesOrder::STAGE_DRAFT) {

            throw new Exception('Sales order draft atau completed tidak dapat ditolak.');
        }

        $user = \App\Models\User::findOrFail($userId);
        $roleMenu = \App\Models\RoleMenu::where('role_id', $user->role_id)->first();
        $allowedApprovalId = $roleMenu?->approval_id;

        if (!$allowedApprovalId || $allowedApprovalId !== $salesOrder->approval_id) {
            throw new Exception('Anda tidak memiliki akses untuk menolak sales order pada tahap ini.');
        }

        $currentStage = $salesOrder->approval_id;

        // Determine rollback destination
        if ($currentStage === SalesOrder::STAGE_WAITING_OM || $currentStage === SalesOrder::STAGE_WAITING_ASM) {
            $rollbackStage = SalesOrder::STAGE_DRAFT;
            $rollbackStatus = 'DRAFT';
        } else { // WAITING_FINANCE (5) -> rollback to WAITING_ADMIN_SALES (4)
            $rollbackStage = SalesOrder::STAGE_WAITING_ADMIN_SALES;
            $rollbackStatus = 'WAITING_ADMIN_SALES';
        }

        $salesOrder->update([
            'status' => $rollbackStatus,
            'approval_id' => $rollbackStage,
            'reject_reason' => $notes,
        ]);

        \App\Models\SalesOrderApprovalHistory::create([
            'sales_order_id' => $salesOrder->id,
            'approval_id_before' => $currentStage,
            'approval_id_after' => $rollbackStage,
            'action' => 'REJECT',
            'user_id' => $userId,
            'notes' => $notes,
        ]);

        $this->auditLogService->log(
            $userId,
            'REJECT_SALES_ORDER',
            "Rejected Sales Order {$salesOrder->order_no} from stage {$currentStage} back to {$rollbackStage}."
        );

        $this->sendStageNotification($salesOrder, $rollbackStage);

        return $salesOrder->load('approval', 'approvalHistories.user');
    }

    /**
     * Save discounts by Admin Sales and submit to Finance.
     */
    public function saveDiscounts(int $id, array $data, int $userId): SalesOrder
    {
        $salesOrder = $this->getOrderById($id);

        if (!$salesOrder) {
            throw new Exception('Sales order tidak ditemukan.');
        }

        if ($salesOrder->approval_id !== SalesOrder::STAGE_WAITING_ADMIN_SALES) {
            throw new Exception('Sales order tidak sedang berada di tahap pengisian diskon.');
        }

        $user = \App\Models\User::findOrFail($userId);
        $roleMenu = \App\Models\RoleMenu::where('role_id', $user->role_id)->first();
        $allowedApprovalId = $roleMenu?->approval_id;

        if (!$allowedApprovalId || $allowedApprovalId !== SalesOrder::STAGE_WAITING_ADMIN_SALES) {
            throw new Exception('Anda tidak memiliki akses untuk mengisi diskon sales order.');
        }

        // Normalize incoming request payload
        $normalizedData = $this->normalizePayload($data);

        // Validate structure of lines for discount
        if (empty($normalizedData['lines']) || !is_array($normalizedData['lines'])) {
            throw new Exception('Data item lines wajib diisi.');
        }

        // Recalculate totals with discounts
        $lines = $normalizedData['lines'];
        $docTotal = 0;

        foreach ($lines as $line) {
            $orderDetail = $salesOrder->details()->where('item_code', $line['item_code'])->first();
            if ($orderDetail) {
                $discPercent = $line['disc_percent'] ?? 0.00;
                $quantity = (float)$orderDetail->quantity;
                $unitPrice = (float)$orderDetail->unit_price;

                $vatGroup = array_key_exists('vat_group', $line) ? $line['vat_group'] : $orderDetail->vat_group;
                $vatRate = 0.00;
                if ($vatGroup) {
                    $vat = \App\Models\Vat::where('code', $vatGroup)->first();
                    if ($vat) {
                        $vatRate = (float)$vat->rate;
                    }
                }

                // Recalculate line total based on discount percentage (without tax)
                $lineTotalBeforeVat = ($quantity * $unitPrice) * (1 - ($discPercent / 100));
                $lineTotal = (float)($line['line_total'] ?? $lineTotalBeforeVat);

                $updateData = [
                    'disc_percent' => $discPercent,
                    'line_total' => $lineTotal,
                ];

                // Also update whs_code, vat_group, and ocr_codes if provided in the input payload
                if (array_key_exists('whs_code', $line)) {
                    $updateData['whs_code'] = $line['whs_code'];
                }
                if (array_key_exists('vat_group', $line)) {
                    $updateData['vat_group'] = $line['vat_group'];
                }
                if (array_key_exists('ocr_code', $line)) {
                    $updateData['ocr_code'] = $line['ocr_code'];
                }
                if (array_key_exists('ocr_code2', $line)) {
                    $updateData['ocr_code2'] = $line['ocr_code2'];
                }
                if (array_key_exists('ocr_code3', $line)) {
                    $updateData['ocr_code3'] = $line['ocr_code3'];
                }

                $orderDetail->update($updateData);

                $vatTotal = $lineTotal * ($vatRate / 100);
                $docTotal += ($lineTotal + $vatTotal);
            }
        }

        // Update header discounts & status
        $salesOrder->update([
            'id_discount' => $normalizedData['id_discount'] ?? $salesOrder->id_discount,
            'doc_total' => $docTotal,
            'status' => 'WAITING_FINANCE',
            'approval_id' => SalesOrder::STAGE_WAITING_FINANCE,
            'reject_reason' => null, // Clear reject reason
            'sales_pic_id' => $userId,
        ]);

        \App\Models\SalesOrderApprovalHistory::create([
            'sales_order_id' => $salesOrder->id,
            'approval_id_before' => SalesOrder::STAGE_WAITING_ADMIN_SALES,
            'approval_id_after' => SalesOrder::STAGE_WAITING_FINANCE,
            'action' => 'APPROVE',
            'user_id' => $userId,
            'notes' => 'Admin Sales mengisi diskon dan meneruskan ke Finance.',
        ]);

        $this->auditLogService->log(
            $userId,
            'SAVE_DISCOUNTS_SALES_ORDER',
            "Admin Sales {$user->name} saved discounts and submitted Sales Order {$salesOrder->order_no} to Finance."
        );

        $this->sendStageNotification($salesOrder, SalesOrder::STAGE_WAITING_FINANCE);

        return $salesOrder->load('details', 'approval', 'approvalHistories.user');
    }

    /**
     * Send notification for a specific stage based on its notification_type.
     */
    protected function sendStageNotification(SalesOrder $salesOrder, int $targetStageId): void
    {
        $stage = \App\Models\MasterApproval::find($targetStageId);
        if (!$stage) {
            return;
        }

        $notificationType = $stage->notification_type;

        if ($notificationType && str_contains($notificationType, 'email')) {
            // Find target users for email (usually ASM / OM / Admin Sales / Finance)
            $targetUsers = \App\Models\User::whereHas('role.roleMenu', function ($q) use ($targetStageId) {
                $q->where('approval_id', $targetStageId);
            })->get();

            // If it's rolling back to DRAFT (Stage 1), notify the creator of the order
            if ($targetStageId === SalesOrder::STAGE_DRAFT) {
                $creator = \App\Models\User::find($salesOrder->created_by);
                if ($creator) {
                    $targetUsers = collect([$creator]);
                }
            }

            if ($targetUsers->isEmpty()) {
                $creator = \App\Models\User::find($salesOrder->created_by);
                if ($creator) {
                    $targetUsers = collect([$creator]);
                }
            }

            foreach ($targetUsers as $targetUser) {
                if ($targetUser->email) {
                    try {
                        \Illuminate\Support\Facades\Mail::to($targetUser->email)
                            ->send(new \App\Mail\AsmApprovalNotificationMail($salesOrder, $targetUser->id));
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to send stage notification email to {$targetUser->email}: " . $e->getMessage());
                    }
                }
            }
        }

        if ($notificationType && str_contains($notificationType, 'web')) {
            // Find all users who are allowed to approve/handle this target stage
            $targetUsers = \App\Models\User::whereHas('role.roleMenu', function ($q) use ($targetStageId) {
                $q->where('approval_id', $targetStageId);
            })->get();

            // If it's rolling back to DRAFT (Stage 1), notify the creator of the order
            if ($targetStageId === SalesOrder::STAGE_DRAFT) {
                $creator = \App\Models\User::find($salesOrder->created_by);
                if ($creator) {
                    $targetUsers = collect([$creator]);
                }
            }

            if ($targetUsers->isNotEmpty()) {
                try {
                    $notificationService = app(\App\Modules\Notification\Services\NotificationService::class);
                    $notificationService->sendToUsers($targetUsers, [
                        'title' => 'Persetujuan Sales Order',
                        'message' => "Sales Order {$salesOrder->order_no} membutuhkan tindakan Anda (Status: {$stage->label}).",
                        'type' => 'info',
                        'url' => "/sales-orders/{$salesOrder->id}",
                        'data' => [
                            'sales_order_id' => $salesOrder->id,
                            'order_no' => $salesOrder->order_no,
                            'stage_id' => $targetStageId,
                        ],
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to send Reverb push notification: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Sync status of a single Sales Order from SAP.
     *
     * @param int $salesOrderId
     * @param int|null $distributorId
     * @return array
     * @throws Exception
     */
    public function syncOrderStatusFromSap(int $salesOrderId, ?int $distributorId = null): array
    {
        $salesOrder = $this->getOrderById($salesOrderId, $distributorId);

        if (!$salesOrder) {
            throw new Exception('Sales order not found.');
        }

        if (empty($salesOrder->sap_doc_num)) {
            throw new Exception('Sales order has not been integrated with SAP.');
        }

        try {
            $sapUrl = config('services.sap.url');
            $response = Http::timeout(15)->post("{$sapUrl}/api/Status", [
                'CustomQuery' => $salesOrder->sap_doc_num
            ]);

            if (!$response->successful()) {
                throw new Exception('Failed to connect to SAP Status API.');
            }

            $body = $response->json();
            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                throw new Exception('SAP Status API returned an error: ' . ($body['Message'] ?? 'Unknown error'));
            }

            $results = $body['Result'] ?? [];
            $sapData = collect($results)->firstWhere('NOSO', $salesOrder->sap_doc_num);

            if (!$sapData) {
                return [
                    'success' => false,
                    'message' => 'SO status not found in SAP.',
                ];
            }

            $sapStatus = $sapData['StatusOrder'] ?? '';
            $docType = $sapData['Doc'] ?? '';
            $docNum = $sapData['Nomor'] ?? '';
            $tanggalRaw = $sapData['Tanggal'] ?? null;

            $parsedDate = now();
            if ($tanggalRaw) {
                try {
                    $parsedDate = \Carbon\Carbon::createFromFormat('Ymd', $tanggalRaw)->startOfDay();
                } catch (\Exception $e) {
                    try {
                        $parsedDate = \Carbon\Carbon::parse($tanggalRaw)->startOfDay();
                    } catch (\Exception $ex) {
                        $parsedDate = now();
                    }
                }
            }

            $updateData = [
                'sap_status' => $sapStatus,
                'sap_last_doc_type' => $docType,
                'sap_last_doc_num' => $docNum,
                'sap_last_synced_at' => now(),
            ];

            // Local status mapping logic (DO: Surat Jalan or AR: Faktur/Invoice)
            if ((strcasecmp($docType, 'DO') === 0 && strcasecmp($sapStatus, 'open') === 0) || strcasecmp($docType, 'AR') === 0) {
                $updateData['status'] = 'DELIVERY';
                if (empty($salesOrder->delivery_date)) {
                    $updateData['delivery_date'] = $parsedDate;
                }
            }

            $salesOrder->update($updateData);

            return [
                'success' => true,
                'message' => 'SAP status synchronized successfully.',
                'data' => $salesOrder
            ];

        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to sync manual status for SO ID {$salesOrderId} from SAP: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Batch sync all pending Sales Orders from SAP.
     *
     * @return array
     */
    public function syncAllPendingOrders(): array
    {
        // Get up to 1000 orders that are integrated, not ARRIVED, and do not have delivery_date filled yet, from the past 1 month
        $orders = SalesOrder::whereNotNull('sap_doc_num')
            ->where('status', '!=', 'ARRIVED')
            ->whereNull('delivery_date')
            ->where('created_at', '>=', now()->subMonth())
            ->limit(1000)
            ->get();

        if ($orders->isEmpty()) {
            return [
                'success' => true,
                'message' => 'No orders need synchronization.',
                'updated_count' => 0
            ];
        }

        $docNums = $orders->pluck('sap_doc_num')->toArray();
        $commaSeparatedDocNums = implode(',', $docNums);

        try {
            $sapUrl = config('services.sap.url');
            $response = Http::timeout(30)->post("{$sapUrl}/api/Status", [
                'CustomQuery' => $commaSeparatedDocNums
            ]);

            if (!$response->successful()) {
                throw new Exception('Failed to connect to SAP Status API.');
            }

            $body = $response->json();
            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                throw new Exception('SAP Status API returned an error: ' . ($body['Message'] ?? 'Unknown error'));
            }

            $results = $body['Result'] ?? [];
            $updatedCount = 0;

            foreach ($results as $sapData) {
                $noso = $sapData['NOSO'] ?? null;
                if (!$noso) continue;

                $order = $orders->firstWhere('sap_doc_num', $noso);
                if ($order) {
                    $sapStatus = $sapData['StatusOrder'] ?? '';
                    $docType = $sapData['Doc'] ?? '';
                    $docNum = $sapData['Nomor'] ?? '';
                    $tanggalRaw = $sapData['Tanggal'] ?? null;

                    $parsedDate = now();
                    if ($tanggalRaw) {
                        try {
                            $parsedDate = \Carbon\Carbon::createFromFormat('Ymd', $tanggalRaw)->startOfDay();
                        } catch (\Exception $e) {
                            try {
                                $parsedDate = \Carbon\Carbon::parse($tanggalRaw)->startOfDay();
                            } catch (\Exception $ex) {
                                $parsedDate = now();
                            }
                        }
                    }

                    $updateData = [
                        'sap_status' => $sapStatus,
                        'sap_last_doc_type' => $docType,
                        'sap_last_doc_num' => $docNum,
                        'sap_last_synced_at' => now(),
                    ];

                    // Local status mapping logic (DO: Surat Jalan or AR: Faktur/Invoice)
                    if ((strcasecmp($docType, 'DO') === 0 && strcasecmp($sapStatus, 'open') === 0) || strcasecmp($docType, 'AR') === 0) {
                        $updateData['status'] = 'DELIVERY';
                        if (empty($order->delivery_date)) {
                            $updateData['delivery_date'] = $parsedDate;
                        }
                    }

                    $order->update($updateData);
                    $updatedCount++;
                }
            }

            return [
                'success' => true,
                'message' => 'Batch status synchronization completed.',
                'updated_count' => $updatedCount
            ];

        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to batch sync SO statuses from SAP: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to run batch synchronization: ' . $e->getMessage(),
                'updated_count' => 0
            ];
        }
    }

    /**
     * Mark a sales order status as ARRIVED.
     *
     * @param  int  $salesOrderId
     * @param  int|null  $distributorId
     * @return array
     * @throws Exception
     */
    public function markAsArrived(int $salesOrderId, ?int $distributorId = null): array
    {
        $salesOrder = $this->getOrderById($salesOrderId, $distributorId);

        if (!$salesOrder) {
            throw new Exception('Sales order not found.');
        }

        if ($salesOrder->status !== 'DELIVERY') {
            throw new Exception('Order status must be DELIVERY to be updated to ARRIVED.');
        }

        $salesOrder->update([
            'status' => 'ARRIVED',
            'arrived_date' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Order status marked as arrived successfully.',
            'data' => $salesOrder
        ];
    }

    /**
     * Get dashboard summary statistics.
     *
     * @param  int|null  $distributorId
     * @return array
     */
    public function getDashboardSummary(int|array|null $distributorId = null): array
    {
        return $this->salesOrderRepository->getDashboardSummary($distributorId);
    }

    /**
     * Get invoices from SAP for a given sales order.
     *
     * @param  int  $id
     * @param  int|null  $distributorId
     * @return array
     * @throws Exception
     */
    public function getInvoicesFromSap(int $id, ?int $distributorId = null): array
    {
        $salesOrder = $this->getOrderById($id, $distributorId);

        if (!$salesOrder) {
            throw new Exception('Sales order tidak ditemukan.');
        }

        // Use sap_doc_num if available, otherwise fallback to order_no
        $queryValue = $salesOrder->sap_doc_num ?: $salesOrder->order_no;

        $sapUrl = config('services.sap.url');
        $response = Http::timeout(15)->post("{$sapUrl}/api/ListInvoice", [
            'CustomQuery' => $queryValue,
        ]);

        if (!$response->successful()) {
            throw new Exception('Gagal menghubungi API SAP untuk mengambil daftar invoice.');
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new Exception('API SAP ListInvoice mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        return $body['Result'] ?? [];
    }

    /**
     * Cancel Sales Order and sync to SAP.
     *
     * @param  int  $id
     * @param  int|null  $distributorId
     * @param  int|null  $userId
     * @return SalesOrder
     * @throws Exception
     */
    public function cancelSalesOrder(int $id, ?int $distributorId = null, ?int $userId = null): SalesOrder
    {
        $salesOrder = $this->getOrderById($id, $distributorId);

        if (!$salesOrder) {
            throw new Exception('Sales order tidak ditemukan.');
        }

        // Rule check: Must have sap_doc_num (integrated/approved)
        if (empty($salesOrder->sap_doc_num)) {
            throw new Exception('Sales Order belum di-approve oleh finance atau belum di-integrasikan dengan SAP B1.');
        }

        // Rule check: Cannot cancel if already delivery, arrived or cancelled
        $currentStatus = strtoupper($salesOrder->status);
        if (in_array($currentStatus, ['DELIVERY', 'ARRIVED', 'CANCELLED', 'CANCEL'])) {
            throw new Exception('Sales Order tidak dapat di-cancel karena status saat ini adalah ' . $salesOrder->status);
        }

        // Call SAP API
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(20)->post("{$sapUrl}/api/CancelSO", [
            'DocNum' => (string) $salesOrder->sap_doc_num,
            'UserId' => $userId ? (int) $userId : 1,
            'AddonId' => 2,
        ]);

        if (!$response->successful()) {
            throw new Exception('Gagal menghubungi API SAP untuk pembatalan SO.');
        }

        $body = $response->json();

        // Handle SAP error code
        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new Exception('API SAP CancelSO mengembalikan error: ' . ($body['Message'] ?? $body['ErrorDesc'] ?? 'Unknown error'));
        }

        // Update sales order status to CANCELLED locally
        $salesOrder->update([
            'status' => 'CANCELLED',
        ]);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'CANCEL_SALES_ORDER',
                "Cancelled Sales Order {$salesOrder->order_no} (SAP DocNum: {$salesOrder->sap_doc_num})."
            );
        }

        return $salesOrder;
    }

    /**
     * Synchronize sales orders and discounts from SAP using GetDataSO and getDiscountSO.
     *
     * @param  array  $cardCodes
     * @param  int|null  $userId
     * @return array
     * @throws Exception
     */
    public function syncSalesOrdersFromSap(array $cardCodes = [], ?int $userId = null): array
    {
        if (empty($cardCodes)) {
            $cardCodes = Distributor::whereNotNull('code_customer')
                ->where('code_customer', '!=', '')
                ->pluck('code_customer')
                ->unique()
                ->values()
                ->toArray();
        }

        if (empty($cardCodes)) {
            return [
                'total_synced' => 0,
                'total_created' => 0,
                'total_updated' => 0,
                'message' => 'Tidak ada card code distributor yang disinkronisasi.',
            ];
        }

        $sapUrl = config('services.sap.url', 'http://103.18.133.187:3100');

        // Chunk card codes to prevent oversized query payloads
        $chunks = array_chunk($cardCodes, 50);
        $allSoItems = [];

        foreach ($chunks as $chunk) {
            $quotedCodes = implode(',', array_map(function ($code) {
                return "'" . trim($code) . "'";
            }, $chunk));

            $response = Http::timeout(30)->post("{$sapUrl}/api/GetDataSO", [
                'CustomQuery' => $quotedCodes,
            ]);

            if (!$response->successful()) {
                throw new Exception('Gagal menghubungi API SAP GetDataSO.');
            }

            $body = $response->json();
            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                throw new Exception('API SAP GetDataSO mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
            }

            $result = $body['Result'] ?? [];
            if (!empty($result)) {
                $allSoItems = array_merge($allSoItems, $result);
            }
        }

        if (empty($allSoItems)) {
            return [
                'total_synced' => 0,
                'total_created' => 0,
                'total_updated' => 0,
                'message' => 'Tidak ada data Sales Order yang ditemukan dari SAP untuk periode ini.',
            ];
        }

        // Group rows by sap_doc_num / sap_doc_entry
        $groupedOrders = [];
        $uniqueDiscountIds = [];

        foreach ($allSoItems as $item) {
            $docKey = (string) ($item['sap_doc_num'] ?? $item['sap_doc_entry']);
            if (!isset($groupedOrders[$docKey])) {
                $groupedOrders[$docKey] = [
                    'header' => $item,
                    'lines' => [],
                ];
            }
            $groupedOrders[$docKey]['lines'][] = $item;

            if (!empty($item['id_discount'])) {
                $uniqueDiscountIds[$item['id_discount']] = true;
            }
        }

        // Sync Discounts from SAP
        foreach (array_keys($uniqueDiscountIds) as $idDiscount) {
            try {
                $discResponse = Http::timeout(15)->post("{$sapUrl}/api/getDiscountSO", [
                    'CustomQuery' => "'{$idDiscount}'",
                ]);

                if ($discResponse->successful()) {
                    $discBody = $discResponse->json();
                    if (($discBody['ErrorCode'] ?? 0) === 0 && !empty($discBody['Result'])) {
                        $discResult = $discBody['Result'];
                        $firstDisc = $discResult[0];

                        $header = SapDiscountHeader::updateOrCreate(
                            ['discount_code' => $idDiscount],
                            [
                                'card_code' => $firstDisc['U_IDCustomer'] ?? '',
                                'card_name' => $firstDisc['U_NamaCustomer'] ?? '',
                                'total_so' => (float) ($firstDisc['U_TotalDocument'] ?? 0),
                                'user_id' => $userId,
                            ]
                        );

                        // Sync details
                        SapDiscountDetail::where('sap_discount_header_id', $header->id)->delete();
                        foreach ($discResult as $discRow) {
                            SapDiscountDetail::create([
                                'sap_discount_header_id' => $header->id,
                                'type_discount' => $discRow['U_TypeDiscount'] ?? '',
                                'percentage' => (float) ($discRow['U_Persentase'] ?? 0),
                                'total_discount' => (float) ($discRow['U_TotalDiskon'] ?? 0),
                                'remarks' => $discRow['U_Remarks'] ?? '',
                            ]);
                        }
                    }
                }
            } catch (Exception $e) {
                Log::warning("Failed to sync discount {$idDiscount}: " . $e->getMessage());
            }
        }

        // Preload distributors by card_code
        $distributorMap = Distributor::whereIn('code_customer', array_column($allSoItems, 'card_code'))
            ->get()
            ->keyBy('code_customer');

        $createdCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($groupedOrders, $distributorMap, &$createdCount, &$updatedCount) {
            foreach ($groupedOrders as $docKey => $orderData) {
                $h = $orderData['header'];
                $cardCode = $h['card_code'] ?? '';
                $distributor = $distributorMap->get($cardCode);
                $distributorId = $distributor?->id ?? 0;

                $sapDocEntry = !empty($h['sap_doc_entry']) ? (int) $h['sap_doc_entry'] : null;
                $sapDocNum = (string) ($h['sap_doc_num'] ?? '');

                // Status mapping
                $cancelStatus = strtoupper(trim((string) ($h['Cancel_status'] ?? 'N')));
                $docStatus = strtoupper(trim((string) ($h['document_status'] ?? 'O')));

                if ($cancelStatus === 'Y') {
                    $status = 'CANCELLED';
                    $sapStatus = 'CANCELED';
                } elseif ($docStatus === 'C') {
                    $status = 'APPROVE_FINANCE';
                    $sapStatus = 'CLOSED';
                } else {
                    $status = 'APPROVE_FINANCE';
                    $sapStatus = 'OPEN';
                }

                // Check existing SO
                $salesOrder = SalesOrder::where('sap_doc_num', $sapDocNum)
                    ->orWhere(function ($q) use ($sapDocEntry) {
                        if ($sapDocEntry) {
                            $q->where('sap_doc_entry', $sapDocEntry);
                        }
                    })
                    ->first();

                $isNew = false;
                if (!$salesOrder) {
                    $isNew = true;
                    $salesOrder = new SalesOrder();
                    $salesOrder->order_no = $sapDocNum ?: ('SO-SAP-' . ($sapDocEntry ?: uniqid()));
                }

                $salesOrder->distributor_id = $distributorId;
                $salesOrder->card_code = $cardCode;
                $salesOrder->customer_name = $h['customer_name'] ?? ($distributor?->name ?? $cardCode);
                $salesOrder->po_number = $h['po_number'] ?? null;
                $salesOrder->doc_date = $h['doc_date'] ?? now()->toDateString();
                $salesOrder->doc_due_date = $h['doc_due_date'] ?? null;
                $salesOrder->slp_code = !empty($h['slp_code']) ? (int) $h['slp_code'] : null;
                $salesOrder->cntct_code = isset($h['cntct_code']) ? (int) $h['cntct_code'] : -1;
                $salesOrder->pay_to_code = $h['pay_to_code'] ?? null;
                $salesOrder->address = $h['address'] ?? null;
                $salesOrder->ship_to_code = $h['ship_to_code'] ?? null;
                $salesOrder->address2 = $h['address2'] ?? null;
                $salesOrder->disc_percent = (float) ($h['disc_percent'] ?? 0);
                $salesOrder->doc_total = (float) ($h['doc_total'] ?? 0);
                $salesOrder->comments = $h['comments'] ?? null;
                $salesOrder->id_discount = $h['id_discount'] ?? null;
                $salesOrder->sap_discount_code = $h['id_discount'] ?? null;
                $salesOrder->series = !empty($h['series']) ? (int) $h['series'] : null;
                $salesOrder->status = $status;
                $salesOrder->sap_doc_entry = $sapDocEntry;
                $salesOrder->sap_doc_num = $sapDocNum;
                $salesOrder->sap_status = $sapStatus;
                $salesOrder->sap_last_synced_at = now();
                $salesOrder->integrated_at = $salesOrder->integrated_at ?? now();

                $salesOrder->save();

                if ($isNew) {
                    $createdCount++;
                } else {
                    $updatedCount++;
                }

                // Sync line items
                SalesOrderDetail::where('sales_order_id', $salesOrder->id)->delete();

                foreach ($orderData['lines'] as $line) {
                    SalesOrderDetail::create([
                        'sales_order_id' => $salesOrder->id,
                        'item_code' => $line['item_code'] ?? '',
                        'quantity' => (float) ($line['quantity'] ?? 0),
                        'unit_msr' => $line['unit_msr'] ?? null,
                        'uom_entry' => !empty($line['uom_entry']) ? (int) $line['uom_entry'] : null,
                        'whs_code' => $line['whs_code'] ?? null,
                        'unit_price' => (float) ($line['unit_price'] ?? 0),
                        'disc_percent' => (float) ($line['line_disc_percent'] ?? 0),
                        'vat_group' => $line['vat_group'] ?? null,
                        'line_total' => (float) ($line['line_total'] ?? 0),
                        'free_text' => $line['free_text'] ?? null,
                        'ocr_code' => $line['ocr_code'] ?? null,
                        'ocr_code2' => $line['ocr_code2'] ?? null,
                        'ocr_code3' => $line['ocr_code3'] ?? null,
                    ]);
                }
            }
        });

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'SYNC_SALES_ORDERS_FROM_SAP',
                "Synchronized {$createdCount} new and updated {$updatedCount} Sales Orders from SAP."
            );
        }

        $totalItems = count($allSoItems);

        return [
            'total_synced' => $createdCount + $updatedCount,
            'total_orders' => $createdCount + $updatedCount,
            'total_items' => $totalItems,
            'total_created' => $createdCount,
            'total_updated' => $updatedCount,
            'message' => "Berhasil sinkronisasi " . ($createdCount + $updatedCount) . " Sales Order ({$totalItems} item barang) dari SAP (Baru: {$createdCount}, Diperbarui: {$updatedCount}).",
        ];
    }
}
