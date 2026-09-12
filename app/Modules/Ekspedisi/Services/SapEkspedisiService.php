<?php

namespace App\Modules\Ekspedisi\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SapEkspedisiService
{
    /**
     * Get base URL for SAP B1 Service API.
     */
    protected function getSapBaseUrl(): string
    {
        $url = config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100');
        return rtrim($url, '/');
    }

    /**
     * Execute POST request to SAP API endpoint.
     *
     * @param string $endpoint
     * @param array $payload
     * @return array
     * @throws \Exception
     */
    protected function callSapEndpoint(string $endpoint, array $payload = []): array
    {
        $baseUrl = $this->getSapBaseUrl();
        $targetUrl = "{$baseUrl}/api/{$endpoint}";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($targetUrl, (object) $payload);
        } catch (\Throwable $e) {
            Log::error("Failed to connect to SAP endpoint {$endpoint}: " . $e->getMessage());
            throw new \Exception("Failed to connect to SAP API ({$endpoint}): " . $e->getMessage());
        }

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            Log::error("SAP endpoint {$endpoint} returned HTTP {$status}: {$body}");
            throw new \Exception("SAP API returned HTTP {$status}: " . substr($body, 0, 200));
        }

        $result = $response->json();

        if (isset($result['ErrorCode']) && (int) $result['ErrorCode'] !== 0) {
            $msg = $result['Message'] ?? 'Unknown SAP error';
            Log::warning("SAP endpoint {$endpoint} returned ErrorCode {$result['ErrorCode']}: {$msg}");
            throw new \Exception("SAP Error [{$result['ErrorCode']}]: {$msg}");
        }

        return $result['Result'] ?? [];
    }

    /**
     * Fetch list of Expedition Names (Nama Ekspedisi) from SAP API (/api/getNamaEkspedisi).
     *
     * @param array $filters
     * @return array
     */
    public function getNamaEkspedisi(array $filters = []): array
    {
        $rawItems = $this->callSapEndpoint('getNamaEkspedisi');

        // Filter out empty / dummy rows (Rule 5)
        $items = array_values(array_filter($rawItems, function ($item) {
            if (!is_array($item)) return false;
            $code = trim((string) ($item['Code'] ?? ''));
            $name = trim((string) ($item['U_NamaEkspedisi'] ?? ''));
            $vendor = trim((string) ($item['U_VendorEkspedisi'] ?? ''));

            if (empty($code) && empty($name) && empty($vendor)) return false;
            if ($code === '0' && ($name === '' || $name === '0')) return false;

            return true;
        }));

        // Optional search filter
        $search = trim((string) ($filters['search'] ?? ''));
        if (!empty($search)) {
            $searchLower = strtolower($search);
            $items = array_values(array_filter($items, function ($item) use ($searchLower) {
                return str_contains(strtolower((string) ($item['Code'] ?? '')), $searchLower)
                    || str_contains(strtolower((string) ($item['U_NamaEkspedisi'] ?? '')), $searchLower)
                    || str_contains(strtolower((string) ($item['U_VendorEkspedisi'] ?? '')), $searchLower);
            }));
        }

        return $items;
    }

    /**
     * Fetch list of Checker Names (Nama Checker) from SAP API (/api/getNamaChecker).
     *
     * @param array $filters
     * @return array
     */
    public function getNamaChecker(array $filters = []): array
    {
        $rawItems = $this->callSapEndpoint('getNamaChecker');

        // Filter out empty / dummy rows (Rule 5)
        $items = array_values(array_filter($rawItems, function ($item) {
            if (!is_array($item)) return false;
            $code = trim((string) ($item['Code'] ?? ''));
            $name = trim((string) ($item['Name'] ?? ''));

            if (empty($code) && empty($name)) return false;
            if ($code === '0' && ($name === '' || $name === '0')) return false;

            return true;
        }));

        // Optional search filter
        $search = trim((string) ($filters['search'] ?? ''));
        if (!empty($search)) {
            $searchLower = strtolower($search);
            $items = array_values(array_filter($items, function ($item) use ($searchLower) {
                return str_contains(strtolower((string) ($item['Code'] ?? '')), $searchLower)
                    || str_contains(strtolower((string) ($item['Name'] ?? '')), $searchLower);
            }));
        }

        return $items;
    }

    /**
     * Fetch list of Vehicles (Kendaraan) from SAP API (/api/getKendaraan).
     *
     * @param array $filters
     * @return array
     */
    public function getKendaraan(array $filters = []): array
    {
        $rawItems = $this->callSapEndpoint('getKendaraan');

        // Filter out empty / dummy rows (Rule 5)
        $items = array_values(array_filter($rawItems, function ($item) {
            if (!is_array($item)) return false;
            $nopol = trim((string) ($item['Nopol'] ?? ''));
            $merk = trim((string) ($item['U_Merk'] ?? ''));

            if (empty($nopol) && empty($merk)) return false;
            if ($nopol === '0' || $nopol === '') return false;

            return true;
        }));

        // Optional search filter
        $search = trim((string) ($filters['search'] ?? ''));
        if (!empty($search)) {
            $searchLower = strtolower($search);
            $items = array_values(array_filter($items, function ($item) use ($searchLower) {
                return str_contains(strtolower((string) ($item['Nopol'] ?? '')), $searchLower)
                    || str_contains(strtolower((string) ($item['U_Merk'] ?? '')), $searchLower);
            }));
        }

        return $items;
    }

    /**
     * Fetch list of Drivers (Sopir) from SAP API (/api/getSopir).
     *
     * @param array $filters
     * @return array
     */
    public function getSopir(array $filters = []): array
    {
        $rawItems = $this->callSapEndpoint('getSopir');

        // Filter out empty / dummy rows (Rule 5)
        $items = array_values(array_filter($rawItems, function ($item) {
            if (!is_array($item)) return false;
            $name = trim((string) ($item['Name'] ?? ''));
            $cabang = trim((string) ($item['U_Cabang'] ?? ''));

            if (empty($name) && empty($cabang)) return false;
            if ($name === '0' || $name === '') return false;

            return true;
        }));

        // Optional search or branch filter
        $search = trim((string) ($filters['search'] ?? ''));
        $cabang = trim((string) ($filters['cabang'] ?? ($filters['U_Cabang'] ?? '')));

        if (!empty($cabang)) {
            $cabangLower = strtolower($cabang);
            $items = array_values(array_filter($items, function ($item) use ($cabangLower) {
                return strtolower((string) ($item['U_Cabang'] ?? '')) === $cabangLower;
            }));
        }

        if (!empty($search)) {
            $searchLower = strtolower($search);
            $items = array_values(array_filter($items, function ($item) use ($searchLower) {
                return str_contains(strtolower((string) ($item['Name'] ?? '')), $searchLower)
                    || str_contains(strtolower((string) ($item['U_Cabang'] ?? '')), $searchLower);
            }));
        }

        return $items;
    }
}
