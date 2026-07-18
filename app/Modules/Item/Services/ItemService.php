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
            $brand = $this->determineBrand($item['ItemCode'], $item['ItemName']);
            $synced[] = $this->itemRepository->upsertByCode([
                'item_code' => $item['ItemCode'],
                'item_name' => $item['ItemName'],
                'suom_entry' => $item['SUoMEntry'] ?? null,
                'sal_unit_msr' => $item['SalUnitMsr'] ?? null,
                'per_kg' => $item['Perkg'] ?? null,
                'brand' => $brand,
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

    /**
     * Determine item brand dynamically based on code and name.
     *
     * @param  string  $itemCode
     * @param  string  $itemName
     * @return string
     */
    public function determineBrand(string $itemCode, string $itemName): string
    {
        $itemNameLower = mb_strtolower(trim($itemName));
        $itemCodeLower = mb_strtolower(trim($itemCode));

        if (str_starts_with($itemCodeLower, 'a02') || str_contains($itemNameLower, 'garami')) {
            return 'GARAMI';
        }
        if (str_starts_with($itemCodeLower, 'a26') || str_contains($itemNameLower, 'top 250') || str_contains($itemNameLower, 'tangan')) {
            return 'TANGAN';
        }
        if (str_contains($itemNameLower, 'dolpin') || str_contains($itemNameLower, 'dolphin')) {
            return 'DOLPIN';
        }
        if (
            str_contains($itemNameLower, 'kop') ||
            str_contains($itemNameLower, 'kapal') ||
            str_starts_with($itemCodeLower, 'b1') ||
            str_starts_with($itemCodeLower, 'b2') ||
            str_starts_with($itemCodeLower, 'b5') ||
            str_starts_with($itemCodeLower, 'c56') ||
            str_starts_with($itemCodeLower, 'c58') ||
            str_starts_with($itemCodeLower, 'fb2') ||
            str_starts_with($itemCodeLower, 'fb5') ||
            str_starts_with($itemCodeLower, 'fc5') ||
            str_starts_with($itemCodeLower, 'hb2') ||
            str_starts_with($itemCodeLower, 'hc5')
        ) {
            return 'KAPAL';
        }
        if (str_contains($itemNameLower, 'perahu layar') || str_starts_with($itemCodeLower, 'd05') || str_starts_with($itemCodeLower, 'd06')) {
            return 'PERAHU LAYAR';
        }
        if (
            str_contains($itemNameLower, 'jempol') ||
            str_contains($itemNameLower, 'jop') ||
            str_starts_with($itemCodeLower, 'd10') ||
            str_starts_with($itemCodeLower, 'd22') ||
            str_starts_with($itemCodeLower, 'd25') ||
            str_starts_with($itemCodeLower, 'd30') ||
            str_starts_with($itemCodeLower, 'fd2') ||
            str_starts_with($itemCodeLower, 'fd3') ||
            str_starts_with($itemCodeLower, 'hd2')
        ) {
            return 'JEMPOL';
        }
        if (str_contains($itemNameLower, 'garamku')) {
            return 'GARAMKU';
        }
        if (str_contains($itemNameLower, 'legies') || str_starts_with($itemCodeLower, 'l26') || str_starts_with($itemCodeLower, 'l5m')) {
            return 'LEGIES';
        }

        // Default / Industrial / rakyat / etc.
        if (
            str_contains($itemNameLower, 'cycl') ||
            str_contains($itemNameLower, 'cyclone') ||
            str_contains($itemNameLower, 'industri') ||
            str_contains($itemNameLower, 'k i ') ||
            str_contains($itemNameLower, 'ki ') ||
            str_contains($itemNameLower, 'k ii') ||
            str_contains($itemNameLower, 'kii') ||
            str_contains($itemNameLower, 'susanti megah') ||
            str_contains($itemNameLower, 'biru') ||
            str_contains($itemNameLower, 'merah') ||
            str_contains($itemNameLower, 'non iod') ||
            str_contains($itemNameLower, 'non-iod') ||
            str_contains($itemNameLower, 'rakyat') ||
            str_contains($itemNameLower, 'kristal') ||
            str_contains($itemNameLower, 'k-iii') ||
            str_contains($itemNameLower, 'timban') ||
            str_starts_with($itemCodeLower, 'e') ||
            str_starts_with($itemCodeLower, 'c25') ||
            str_starts_with($itemCodeLower, 'i25') ||
            str_starts_with($itemCodeLower, 'i50') ||
            str_starts_with($itemCodeLower, 'k50') ||
            str_starts_with($itemCodeLower, 'k54') ||
            str_starts_with($itemCodeLower, 'k55') ||
            str_starts_with($itemCodeLower, 'n25') ||
            str_starts_with($itemCodeLower, 'nr25') ||
            str_starts_with($itemCodeLower, 'r0') ||
            str_starts_with($itemCodeLower, 'r1') ||
            str_starts_with($itemCodeLower, 'rm-') ||
            str_starts_with($itemCodeLower, 'rfsi') ||
            str_starts_with($itemCodeLower, 'g25') ||
            str_starts_with($itemCodeLower, 'g26') ||
            str_starts_with($itemCodeLower, 'g27')
        ) {
            return 'INDUSTRI';
        }

        return 'INDUSTRI';
    }
}
