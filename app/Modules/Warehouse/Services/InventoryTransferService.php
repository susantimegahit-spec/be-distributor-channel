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
        $response = Http::timeout(30)->post('http://103.18.133.187:3100/api/searchQtyBin', [
            'CustomQuery' => $payload['CustomQuery'] ?? '',
            'WhsCode' => $payload['WhsCode'] ?? '',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk mencari data Bin.');
        }

        return $response->json();
    }

    /**
     * Search master bins from SAP.
     *
     * @param array $payload
     * @return array
     */
    public function searchBin(array $payload): array
    {
        $response = Http::timeout(30)->post('http://103.18.133.187:3100/api/searchBin', [
            'CustomQuery' => $payload['CustomQuery'] ?? '',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk mencari data Master Bin.');
        }

        return $response->json();
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

        $response = Http::timeout(30)->post('http://103.18.133.187:3100/api/addIT', $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk proses Inventory Transfer.');
        }

        return $response->json();
    }

    /**
     * Get list of Inventory Transfers (IT) from SAP.
     *
     * @return array
     */
    public function listIT(): array
    {
        $response = Http::timeout(30)->post('http://103.18.133.187:3100/api/getListIT');

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
        $response = Http::timeout(30)->post('http://103.18.133.187:3100/api/getITbyId', [
            'CustomQuery' => $docEntry,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk mendapatkan data Inventory Transfer.');
        }

        return $response->json();
    }
}
