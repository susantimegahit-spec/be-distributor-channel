<?php

namespace App\Modules\SalesOrder\Services;

use App\Models\SalesOrder;
use App\Modules\SalesOrder\Repositories\SalesOrderRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

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
        $data['status'] = 'DRAFT';
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
}
