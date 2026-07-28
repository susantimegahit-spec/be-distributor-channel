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
     * Perform Inventory Transfer in SAP.
     *
     * @param array $payload
     * @return array
     */
    public function addInventoryTransfer(array $payload): array
    {
        // Preprocess/sanitize Lines for BinActivfrom and BinActivto
        if (isset($payload['Lines']) && is_array($payload['Lines'])) {
            foreach ($payload['Lines'] as &$line) {
                // Preprocess Lines_BinFROM
                $binFrom = isset($line['Lines_BinFROM']) ? array_filter((array)$line['Lines_BinFROM'], function ($b) {
                    return !empty($b['AbsEntry']);
                }) : [];

                if (empty($binFrom)) {
                    $line['BinActivfrom'] = 'N';
                    unset($line['Lines_BinFROM']);
                } else {
                    $line['BinActivfrom'] = 'Y';
                    $line['Lines_BinFROM'] = array_values($binFrom);
                }

                // Preprocess Lines_BinTO
                $binTo = isset($line['Lines_BinTO']) ? array_filter((array)$line['Lines_BinTO'], function ($b) {
                    return !empty($b['AbsEntry']);
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

        $response = Http::timeout(30)->post('http://103.18.133.187:3100/api/addIT', $payload);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk proses Inventory Transfer.');
        }

        return $response->json();
    }
}
