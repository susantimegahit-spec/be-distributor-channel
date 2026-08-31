<?php

namespace App\Modules\Warehouse\Services;

use Illuminate\Support\Facades\Http;

class InventoryTransferService
{
    /**
     * Search bins from SAP.
     *
     * @param array $payload
     * @return array
     */
    public function searchQtyBin(array $payload): array
    {
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(30)->post("{$sapUrl}/api/searchQtyBin", [
            'CustomQuery' => $payload['CustomQuery'] ?? $payload['custom_query'] ?? '',
            'WhsCode' => $payload['WhsCode'] ?? $payload['whs_code'] ?? '',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk mencari data Bin.');
        }

        $data = $response->json();
        $rawItems = $data['Result'] ?? $data['result'] ?? [];

        // Filter out dummy empty 0 rows returned by SAP when data is not found
        $filteredItems = array_values(array_filter((array)$rawItems, function ($row) {
            if (!is_array($row)) return false;
            $itemCode = trim((string)($row['ItemCode'] ?? $row['item_code'] ?? ''));
            $absEntry = trim((string)($row['AbsEntry'] ?? $row['abs_entry'] ?? ''));
            $whsCode  = trim((string)($row['WhsCode'] ?? $row['whs_code'] ?? ''));
            $sisaQty  = floatval($row['SisaQty'] ?? $row['sisa_qty'] ?? 0);

            return (!empty($itemCode) && $itemCode !== '0') ||
                   (!empty($absEntry) && $absEntry !== '0') ||
                   (!empty($whsCode)) ||
                   $sisaQty > 0;
        }));

        $data['Result'] = $filteredItems;
        return $data;
    }

    /**
     * Search master bins from SAP.
     *
     * @param array $payload
     * @return array
     */
    public function searchBin(array $payload): array
    {
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(30)->post("{$sapUrl}/api/searchBin", [
            'CustomQuery' => $payload['CustomQuery'] ?? $payload['custom_query'] ?? '',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk mencari data Master Bin.');
        }

        $data = $response->json();
        $rawItems = $data['Result'] ?? $data['result'] ?? [];

        // Filter out dummy empty rows returned by SAP when data is not found
        $filteredItems = array_values(array_filter((array)$rawItems, function ($row) {
            if (!is_array($row)) return false;
            $binCode  = trim((string)($row['BinCode'] ?? $row['bin_code'] ?? ''));
            $absEntry = trim((string)($row['AbsEntry'] ?? $row['abs_entry'] ?? ''));
            $whsCode  = trim((string)($row['WhsCode'] ?? $row['whs_code'] ?? ''));

            return (!empty($binCode) && $binCode !== '0') ||
                   (!empty($absEntry) && $absEntry !== '0') ||
                   (!empty($whsCode));
        }));

        $data['Result'] = $filteredItems;
        return $data;
    }

    /**
     * Resolve Bin Code string to its numeric AbsEntry ID by querying SAP.
     *
     * @param string $binCode
     * @return int|null
     */
    private function resolveBinAbsEntry(string $binCode): ?int
    {
        try {
            $result = $this->searchBin(['CustomQuery' => $binCode]);
            $bins = $result['Result'] ?? $result['result'] ?? [];
            foreach ($bins as $bin) {
                if (strcasecmp(trim($bin['BinCode'] ?? ''), trim($binCode)) === 0) {
                    return (int)$bin['AbsEntry'];
                }
            }
        } catch (\Exception $e) {
            // Ignore search error and return null
        }
        return null;
    }

    /**
     * Perform Inventory Transfer in SAP.
     *
     * @param array $payload
     * @param int|null $userId
     * @return array
     */
    public function addInventoryTransfer(array $payload, ?int $userId): array
    {
        // Preprocess/sanitize Lines for BinActivfrom and BinActivto
        if (isset($payload['Lines']) && is_array($payload['Lines'])) {
            foreach ($payload['Lines'] as &$line) {
                // Ensure UseBaseUn is uppercase
                if (isset($line['UseBaseUn'])) {
                    $line['UseBaseUn'] = strtoupper($line['UseBaseUn']);
                }

                // Preprocess Lines_BinFROM
                if (isset($line['Lines_BinFROM']) && is_array($line['Lines_BinFROM'])) {
                    foreach ($line['Lines_BinFROM'] as &$b) {
                        if (isset($b['AbsEntry']) && !is_numeric($b['AbsEntry']) && $b['AbsEntry'] !== 'NO BIN') {
                            $resolved = $this->resolveBinAbsEntry((string)$b['AbsEntry']);
                            if ($resolved !== null) {
                                $b['AbsEntry'] = $resolved;
                            }
                        }
                    }
                }

                $binFrom = isset($line['Lines_BinFROM']) ? array_filter((array)$line['Lines_BinFROM'], function ($b) {
                    return !empty($b['AbsEntry']) && is_numeric($b['AbsEntry']);
                }) : [];

                if (empty($binFrom)) {
                    $line['BinActivfrom'] = 'N';
                    unset($line['Lines_BinFROM']);
                } else {
                    $line['BinActivfrom'] = 'Y';
                    $line['Lines_BinFROM'] = array_values($binFrom);
                }

                // Preprocess Lines_BinTO
                if (isset($line['Lines_BinTO']) && is_array($line['Lines_BinTO'])) {
                    foreach ($line['Lines_BinTO'] as &$b) {
                        if (isset($b['AbsEntry']) && !is_numeric($b['AbsEntry']) && $b['AbsEntry'] !== 'NO BIN') {
                            $resolved = $this->resolveBinAbsEntry((string)$b['AbsEntry']);
                            if ($resolved !== null) {
                                $b['AbsEntry'] = $resolved;
                            }
                        }
                    }
                }

                $binTo = isset($line['Lines_BinTO']) ? array_filter((array)$line['Lines_BinTO'], function ($b) {
                    return !empty($b['AbsEntry']) && is_numeric($b['AbsEntry']);
                }) : [];

                if (empty($binTo)) {
                    $line['BinActivto'] = 'N';
                    unset($line['Lines_BinTO']);
                } else {
                    $line['BinActivto'] = 'Y';
                    $line['Lines_BinTO'] = array_values($binTo);
                }
            }
        }

        // Add AddonId and UserId
        $payload['AddonId'] = 2;
        $payload['UserId'] = (int)$userId;
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(30)->post("{$sapUrl}/api/addIT", $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk proses Inventory Transfer.');
        }

        return $response->json();
    }

    /**
     * Get list of Inventory Transfers (IT) from SAP.
     *
     * @param array $filters
     * @return array
     */
    public function listIT(array $filters = []): array
    {
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(30)->post("{$sapUrl}/api/getListIT", $filters);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk mendapatkan daftar Inventory Transfer.');
        }

        return $response->json();
    }

    /**
     * Get Inventory Transfer (IT) by ID/DocEntry from SAP.
     *
     * @param string $docEntry
     * @return array
     */
    public function getITbyId(string $docEntry): array
    {
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(30)->post("{$sapUrl}/api/getITbyId", [
            'CustomQuery' => $docEntry,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk mendapatkan data Inventory Transfer.');
        }

        return $response->json();
    }

    /**
     * Cancel Inventory Transfer (IT) in SAP.
     *
     * @param string|int $docEntry
     * @param int|null $userId
     * @return array
     */
    public function cancelIT(string|int $docEntry, ?int $userId): array
    {
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(30)->post("{$sapUrl}/api/CancelIT", [
            'DocEntry' => (string) $docEntry,
            'UserId' => (int) $userId,
            'AddonId' => 2,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk membatalkan Inventory Transfer.');
        }

        return $response->json();
    }
}
