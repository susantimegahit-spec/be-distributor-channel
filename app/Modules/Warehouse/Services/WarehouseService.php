<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Warehouse\Repositories\WarehouseRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

class WarehouseService
{
    protected WarehouseRepositoryInterface $warehouseRepository;
    protected AuditLogService $auditLogService;

    /**
     * WarehouseService constructor.
     *
     * @param  WarehouseRepositoryInterface  $warehouseRepository
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(
        WarehouseRepositoryInterface $warehouseRepository,
        AuditLogService $auditLogService
    ) {
        $this->warehouseRepository = $warehouseRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get all warehouses.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->warehouseRepository->getAll($filters);
    }

    /**
     * Get warehouse by ID.
     *
     * @param  int  $id
     * @return Warehouse|null
     */
    public function getById(int $id): ?\App\Models\Warehouse
    {
        return $this->warehouseRepository->findById($id);
    }

    /**
     * Create a new warehouse manually.
     *
     * @param  array  $data
     * @param  int|null  $userId
     * @return \App\Models\Warehouse
     */
    public function create(array $data, ?int $userId = null): \App\Models\Warehouse
    {
        $warehouse = $this->warehouseRepository->create($data);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'CREATE_WAREHOUSE',
                "Created warehouse: {$warehouse->whs_code} - {$warehouse->whs_name}"
            );
        }

        return $warehouse;
    }

    /**
     * Update an existing warehouse.
     *
     * @param  int  $id
     * @param  array  $data
     * @param  int|null  $userId
     * @return \App\Models\Warehouse
     */
    public function update(int $id, array $data, ?int $userId = null): \App\Models\Warehouse
    {
        $warehouse = $this->warehouseRepository->update($id, $data);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'UPDATE_WAREHOUSE',
                "Updated warehouse ID {$id}: {$warehouse->whs_code} - {$warehouse->whs_name} (Unit ID: " . ($warehouse->master_unit_id ?? 'None') . ")"
            );
        }

        return $warehouse;
    }

    /**
     * Delete a warehouse.
     *
     * @param  int  $id
     * @param  int|null  $userId
     * @return bool
     */
    public function delete(int $id, ?int $userId = null): bool
    {
        $warehouse = $this->warehouseRepository->findById($id);
        if (!$warehouse) {
            throw new \Exception("Gudang dengan ID {$id} tidak ditemukan.");
        }

        $code = $warehouse->whs_code;
        $name = $warehouse->whs_name;

        $result = $this->warehouseRepository->delete($id);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'DELETE_WAREHOUSE',
                "Deleted warehouse ID {$id}: {$code} - {$name}"
            );
        }

        return $result;
    }

    /**
     * Synchronize warehouses from SAP.
     *
     * @param  int|null  $userId
     * @return array
     */
    public function syncFromSap(?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(15)->post("{$sapUrl}/api/SearchWH");

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk sinkronisasi master gudang.');
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        $sapData = $body['Result'] ?? [];
        $synced = [];

        foreach ($sapData as $item) {
            $synced[] = $this->warehouseRepository->upsertByCode([
                'whs_code' => $item['WhsCode'],
                'whs_name' => $item['WhsName'],
                'status' => 'ACTIVE',
            ]);
        }

        // Log to Audit Log if user is authenticated
        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'SYNC_WAREHOUSES',
                'Synchronized ' . count($synced) . ' warehouses from SAP.'
            );
        }

        return $synced;
    }
}
