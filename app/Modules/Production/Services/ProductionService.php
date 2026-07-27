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
        $response = Http::timeout(15)->post('http://103.18.133.187:3100/api/GetResource');

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
        $response = Http::timeout(15)->post('http://103.18.133.187:3100/api/ListItemProd');

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
}
