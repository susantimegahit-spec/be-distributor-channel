<?php

namespace App\Modules\CustomerMonthlyOrder\Services;

use App\Models\CustomerMonthlyOrder;
use App\Models\SalesOrder;
use App\Modules\CustomerMonthlyOrder\Repositories\CustomerMonthlyOrderRepositoryInterface;
use App\Modules\SalesOrder\Repositories\SalesOrderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CustomerMonthlyOrderService
{
    protected CustomerMonthlyOrderRepositoryInterface $repository;
    protected SalesOrderRepositoryInterface $salesOrderRepository;

    public function __construct(
        CustomerMonthlyOrderRepositoryInterface $repository,
        SalesOrderRepositoryInterface $salesOrderRepository
    ) {
        $this->repository = $repository;
        $this->salesOrderRepository = $salesOrderRepository;
    }

    /**
     * Get all orders with filters.
     *
     * @param  int|null  $distributorId
     * @param  string|null  $status
     * @param  string|null  $cardCode
     * @param  string|null  $startDate
     * @param  string|null  $endDate
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

        return $this->repository->getAll($filters);
    }

    /**
     * Get order by ID.
     *
     * @param  int  $id
     * @param  int|null  $distributorId
     * @return CustomerMonthlyOrder|null
     */
    public function getOrderById(int $id, $distributorId = null): ?CustomerMonthlyOrder
    {
        $order = $this->repository->getById($id);

        if ($order && $distributorId) {
            if (is_array($distributorId)) {
                if (!in_array($order->distributor_id, $distributorId)) {
                    return null;
                }
            } elseif ($order->distributor_id !== $distributorId) {
                return null;
            }
        }

        return $order;
    }

    /**
     * Create a new Customer Monthly Order.
     *
     * @param  array  $data
     * @param  int  $userId
     * @param  int  $distributorId
     * @return CustomerMonthlyOrder
     */
    public function createOrder(array $data, int $userId, int $distributorId): CustomerMonthlyOrder
    {
        $distributor = \App\Models\Distributor::find($distributorId);

        $data['order_no'] = $this->generateCmoNumber();
        $data['distributor_id'] = $distributorId;
        $data['card_code'] = $distributor ? $distributor->code_customer : 'Unknown';
        $data['customer_name'] = $distributor ? $distributor->name : 'Unknown Customer';
        $data['status'] = 'DRAFT';
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        // Calculate doc_total as sum of line_total
        $docTotal = 0;
        foreach ($data['lines'] as $line) {
            $docTotal += $line['line_total'];
        }
        $data['doc_total'] = $docTotal;

        // Extract attachment(s)
        $rawAttachments = $data['attachment'] ?? $data['attachments'] ?? null;
        unset($data['attachment'], $data['attachments']);

        // Remove non-db columns
        unset($data['customer_code']);
        unset($data['code_customer']);

        $cmo = $this->repository->create($data);

        $attachmentsList = is_array($rawAttachments) ? $rawAttachments : ($rawAttachments ? [$rawAttachments] : []);
        foreach ($attachmentsList as $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                try {
                    $this->storeAttachment($cmo->id, $file, $userId);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to store CMO attachment: " . $e->getMessage());
                }
            }
        }
        if (!empty($attachmentsList)) {
            $cmo->load('attachments');
        }

        return $cmo;
    }

    /**
     * Update an existing Customer Monthly Order.
     *
     * @param  int  $id
     * @param  array  $data
     * @param  int  $userId
     * @param  int|null  $distributorId
     * @return CustomerMonthlyOrder
     * @throws \Exception
     */
    public function updateOrder(int $id, array $data, int $userId, ?int $distributorId = null): CustomerMonthlyOrder
    {
        $order = $this->getOrderById($id, $distributorId);
        if (!$order) {
            throw new \Exception('Customer monthly order tidak ditemukan.');
        }

        if (strtoupper($order->status) === 'POSTED') {
            throw new \Exception('Customer monthly order yang sudah diposting tidak dapat diubah.');
        }

        $data['updated_by'] = $userId;

        // Recalculate doc_total
        if (isset($data['lines'])) {
            $docTotal = 0;
            foreach ($data['lines'] as $line) {
                $docTotal += $line['line_total'];
            }
            $data['doc_total'] = $docTotal;
        }

        // Extract attachment(s)
        $rawAttachments = $data['attachment'] ?? $data['attachments'] ?? null;
        unset($data['attachment'], $data['attachments']);

        // Remove non-db columns
        unset($data['customer_code']);
        unset($data['code_customer']);

        $updatedOrder = $this->repository->update($order, $data);

        $attachmentsList = is_array($rawAttachments) ? $rawAttachments : ($rawAttachments ? [$rawAttachments] : []);
        foreach ($attachmentsList as $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                try {
                    $this->storeAttachment($updatedOrder->id, $file, $userId);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to update CMO attachment: " . $e->getMessage());
                }
            }
        }
        if (!empty($attachmentsList)) {
            $updatedOrder->load('attachments');
        }

        return $updatedOrder;
    }

    /**
     * Delete a Customer Monthly Order.
     *
     * @param  int  $id
     * @param  int|null  $distributorId
     * @return bool
     * @throws \Exception
     */
    public function deleteOrder(int $id, ?int $distributorId = null): bool
    {
        $order = $this->getOrderById($id, $distributorId);
        if (!$order) {
            throw new \Exception('Customer monthly order tidak ditemukan.');
        }

        if (strtoupper($order->status) === 'POSTED') {
            throw new \Exception('Customer monthly order yang sudah diposting tidak dapat dihapus.');
        }

        return $this->repository->delete($id);
    }

    /**
     * Post a Customer Monthly Order to Sales Order (status = WAITING_OM).
     *
     * @param  int  $id
     * @param  int  $userId
     * @param  int|null  $distributorId
     * @return SalesOrder
     * @throws \Exception
     */
    public function postToSalesOrder(int $id, int $userId, ?int $distributorId = null): SalesOrder
    {
        $order = $this->getOrderById($id, $distributorId);
        if (!$order) {
            throw new \Exception('Customer monthly order tidak ditemukan.');
        }

        if (strtoupper($order->status) === 'POSTED') {
            throw new \Exception('Customer monthly order ini sudah pernah diposting sebelumnya.');
        }

        return DB::transaction(function () use ($order, $userId) {
            // 1. Prepare Sales Order Header Data
            $cmoAttributes = $order->getAttributes();
            $soColumns = \Illuminate\Support\Facades\Schema::getColumnListing('sales_orders');
            
            $soData = [];
            foreach ($soColumns as $column) {
                if (array_key_exists($column, $cmoAttributes)) {
                    $soData[$column] = $cmoAttributes[$column];
                }
            }
            
            // Remove identity keys
            unset($soData['id']);
            unset($soData['created_at']);
            unset($soData['updated_at']);

            // Set new values for SO
            $soData['order_no'] = $this->generateSoNumber();
            $soData['status'] = 'WAITING_OM';
            $soData['approval_id'] = 2; // STAGE_WAITING_OM = 2
            $soData['created_by'] = $userId;
            $soData['updated_by'] = $userId;

            // 2. Prepare Sales Order Details
            $soLineColumns = \Illuminate\Support\Facades\Schema::getColumnListing('sales_order_details');
            $soLines = [];
            foreach ($order->details as $line) {
                $lineAttributes = $line->getAttributes();
                
                $lineData = [];
                foreach ($soLineColumns as $col) {
                    if (array_key_exists($col, $lineAttributes)) {
                        $lineData[$col] = $lineAttributes[$col];
                    }
                }
                
                unset($lineData['id']);
                unset($lineData['sales_order_id']);
                unset($lineData['created_at']);
                unset($lineData['updated_at']);
                
                $soLines[] = $lineData;
            }
            $soData['lines'] = $soLines;

            // 3. Create the Sales Order
            $salesOrder = $this->salesOrderRepository->create($soData);

            // 3b. Carry over attachments from CMO to newly created Sales Order
            if ($order->attachments && $order->attachments->count() > 0) {
                foreach ($order->attachments as $attachment) {
                    $attachment->update([
                        'sales_order_id' => $salesOrder->id,
                    ]);
                }
            }

            // 4. Update the CMO Status to POSTED
            $order->update(['status' => 'POSTED']);

            return $salesOrder->load(['details.item', 'details.warehouse', 'details.vat', 'details.ocr', 'details.ocr2', 'details.ocr3', 'salesEmployee', 'sapDiscount.details', 'attachments']);
        });
    }

    /**
     * Store the attachment file for CMO/Sales Order.
     *
     * @param  int  $cmoId
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  int  $userId
     * @return \App\Models\SalesOrderAttachment
     */
    protected function storeAttachment(int $cmoId, \Illuminate\Http\UploadedFile $file, int $userId): \App\Models\SalesOrderAttachment
    {
        $extension = $file->getClientOriginalExtension();
        $fileName = $file->getClientOriginalName();
        $dateStr = now()->format('Ymd');
        $randomStr = \Illuminate\Support\Str::random(6);

        $storedFileName = "{$dateStr}_CMO_{$cmoId}_{$randomStr}.{$extension}";
        $path = $file->storeAs('attachments/order', $storedFileName, 'public');

        $attachmentData = [
            'file_name' => $fileName,
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $userId,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_order_attachments', 'customer_monthly_order_id')) {
            $attachmentData['customer_monthly_order_id'] = $cmoId;
        } else {
            $attachmentData['sales_order_id'] = $cmoId;
        }

        return \App\Models\SalesOrderAttachment::create($attachmentData);
    }

    /**
     * Generate unique CMO number.
     *
     * @return string
     */
    protected function generateCmoNumber(): string
    {
        $prefix = 'CMO-' . date('Ymd') . '-';
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        while (CustomerMonthlyOrder::where('order_no', $prefix . $random)->exists()) {
            $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        return $prefix . $random;
    }

    /**
     * Generate unique SO number.
     *
     * @return string
     */
    protected function generateSoNumber(): string
    {
        $prefix = 'SO-' . date('Ymd') . '-';
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        while (SalesOrder::where('order_no', $prefix . $random)->exists()) {
            $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        return $prefix . $random;
    }
}
