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
     * Synchronize warehouses from SAP.
     *
     * @param  int|null  $userId
     * @return array
     */
    public function syncFromSap(?int $userId = null): array
    {
        $response = Http::timeout(15)->post('http://103.18.133.187:3100/api/SearchWHFG');

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
                'status' => 1,
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
