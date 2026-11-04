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
        $sapUrl = rtrim((string)(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100')), '/');

        // 1. Fetch ListItemProd from SAP
        $prodData = [];
        $prodResponse = null;
        try {
            $prodResponse = Http::timeout(60)->post("{$sapUrl}/api/ListItemProd");
            if ($prodResponse->successful()) {
                $body = $prodResponse->json();
                if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                    throw new \Exception('API SAP ListItemProd mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
                }
                $prodData = $body['Result'] ?? [];
            } else {
                throw new \Exception('Gagal menghubungi API SAP ListItemProd: HTTP ' . $prodResponse->status());
            }
        } catch (\Throwable $e) {
            // If ListItemProd failed and not mocked, try fallback to ListItem if available
            if (empty($prodData)) {
                $fallbackResponse = Http::timeout(15)->post("{$sapUrl}/api/ListItem");
                if (!$fallbackResponse->successful()) {
                    throw new \Exception('Gagal menghubungi API SAP untuk sinkronisasi barang: ' . $e->getMessage());
                }
                $fallbackBody = $fallbackResponse->json();
                if (isset($fallbackBody['ErrorCode']) && $fallbackBody['ErrorCode'] !== 0) {
                    throw new \Exception('API SAP mengembalikan error: ' . ($fallbackBody['Message'] ?? 'Unknown error'));
                }
                $prodData = $fallbackBody['Result'] ?? [];
            }
        }

        // 2. Fetch sales items from ListItem to enrich SUoMEntry, SalUnitMsr, and Perkg
        $salesMap = [];
        try {
            $salesResponse = Http::timeout(15)->post("{$sapUrl}/api/ListItem");
            if ($salesResponse->successful()) {
                $salesBody = $salesResponse->json();
                if (!isset($salesBody['ErrorCode']) || $salesBody['ErrorCode'] === 0) {
                    foreach ($salesBody['Result'] ?? [] as $si) {
                        $code = trim($si['ItemCode'] ?? '');
                        if ($code !== '') {
                            $salesMap[$code] = $si;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal, proceed with prodData alone
        }

        $now = now();
        $rows = [];
        $processedCodes = [];

        foreach ($prodData as $item) {
            $itemCode = trim($item['ItemCode'] ?? '');
            if ($itemCode === '') {
                continue;
            }
            $processedCodes[$itemCode] = true;
            $itemName = trim($item['ItemName'] ?? '');
            $brand = $this->determineBrand($itemCode, $itemName);
            $salesItem = $salesMap[$itemCode] ?? null;

            $suomEntry = isset($item['SUoMEntry']) && $item['SUoMEntry'] !== ''
                ? (int)$item['SUoMEntry']
                : (isset($salesItem['SUoMEntry']) && $salesItem['SUoMEntry'] !== ''
                    ? (int)$salesItem['SUoMEntry']
                    : (isset($item['UoMEntry']) && $item['UoMEntry'] !== '' ? (int)$item['UoMEntry'] : null));

            $salUnitMsr = $item['SalUnitMsr'] ?? ($salesItem['SalUnitMsr'] ?? null);
            $perKg = isset($item['Perkg']) && $item['Perkg'] !== ''
                ? (float)$item['Perkg']
                : (isset($salesItem['Perkg']) && $salesItem['Perkg'] !== '' ? (float)$salesItem['Perkg'] : null);

            $iuomEntry = isset($item['IUoMEntry']) && $item['IUoMEntry'] !== '' ? (int)$item['IUoMEntry'] : null;
            $invntryUom = !empty($item['InvntryUom']) ? $item['InvntryUom'] : ($salUnitMsr ?? null);
            $puomEntry = isset($item['PUoMEntry']) && $item['PUoMEntry'] !== '' ? (int)$item['PUoMEntry'] : null;
            $purPackMsr = $item['PurPackMsr'] ?? null;
            $prchseItem = $item['PrchseItem'] ?? null;
            $sellItem = $item['SellItem'] ?? (isset($salesItem) ? 'Y' : null);
            $invntItem = $item['InvntItem'] ?? null;
            $itmsGrpCod = $item['ItmsGrpCod'] ?? null;

            $rows[] = [
                'item_code'    => $itemCode,
                'item_name'    => $itemName,
                'suom_entry'   => $suomEntry,
                'sal_unit_msr' => $salUnitMsr,
                'per_kg'       => $perKg,
                'iuom_entry'   => $iuomEntry,
                'invntry_uom'  => $invntryUom,
                'puom_entry'   => $puomEntry,
                'pur_pack_msr' => $purPackMsr,
                'prchse_item'  => $prchseItem,
                'sell_item'    => $sellItem,
                'invnt_item'   => $invntItem,
                'itms_grp_cod' => $itmsGrpCod,
                'brand'        => $brand,
                'status'       => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        // Include any sales items from ListItem that were not in ListItemProd
        foreach ($salesMap as $code => $si) {
            if (!isset($processedCodes[$code])) {
                $processedCodes[$code] = true;
                $itemName = trim($si['ItemName'] ?? '');
                $brand = $this->determineBrand($code, $itemName);
                $rows[] = [
                    'item_code'    => $code,
                    'item_name'    => $itemName,
                    'suom_entry'   => isset($si['SUoMEntry']) && $si['SUoMEntry'] !== '' ? (int)$si['SUoMEntry'] : (isset($si['UoMEntry']) && $si['UoMEntry'] !== '' ? (int)$si['UoMEntry'] : null),
                    'sal_unit_msr' => $si['SalUnitMsr'] ?? null,
                    'per_kg'       => isset($si['Perkg']) && $si['Perkg'] !== '' ? (float)$si['Perkg'] : null,
                    'iuom_entry'   => null,
                    'invntry_uom'  => $si['SalUnitMsr'] ?? null,
                    'puom_entry'   => null,
                    'pur_pack_msr' => null,
                    'prchse_item'  => 'N',
                    'sell_item'    => 'Y',
                    'invnt_item'   => 'Y',
                    'itms_grp_cod' => null,
                    'brand'        => $brand,
                    'status'       => 1,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
        }

        // Bulk upsert to database
        $this->itemRepository->upsertBatch($rows);

        // Log to Audit Log if user is authenticated
        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'SYNC_ITEMS',
                'Synchronized ' . count($rows) . ' items from SAP.'
            );
        }

        return $rows;
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
            return 'LAYAR';
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
