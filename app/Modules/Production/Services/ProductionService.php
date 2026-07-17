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
}
