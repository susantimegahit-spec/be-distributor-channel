<?php

namespace App\Modules\Item\Services;

use App\Modules\Item\Repositories\ItemRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

class ItemService
{
    protected ItemRepositoryInterface $itemRepository;
    protected AuditLogService $auditLogService;

    /**
     * ItemService constructor.
     *
     * @param  ItemRepositoryInterface  $itemRepository
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(
        ItemRepositoryInterface $itemRepository,
        AuditLogService $auditLogService
    ) {
        $this->itemRepository = $itemRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get all items.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->itemRepository->getAll($filters);
    }

    /**
     * Synchronize items from SAP.
     *
     * @param  int|null  $userId
     * @return array
     */
    public function syncFromSap(?int $userId = null): array
    {
        $response = Http::timeout(15)->post('http://103.18.133.187:3100/api/ListItem');

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk sinkronisasi barang.');
        }

        $body = $response->json();
        
        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        $sapData = $body['Result'] ?? [];
        $synced = [];

        foreach ($sapData as $item) {
            $synced[] = $this->itemRepository->upsertByCode([
                'item_code' => $item['ItemCode'],
                'item_name' => $item['ItemName'],
                'suom_entry' => $item['SUoMEntry'] ?? null,
                'sal_unit_msr' => $item['SalUnitMsr'] ?? null,
                'per_kg' => $item['Perkg'] ?? null,
                'status' => 1,
            ]);
        }

        // Log to Audit Log if user is authenticated
        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'SYNC_ITEMS',
                'Synchronized ' . count($synced) . ' items from SAP.'
            );
        }

        return $synced;
    }
}
