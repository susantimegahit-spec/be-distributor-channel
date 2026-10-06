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

    /**
     * Get stock of items in a warehouse from SAP API (/api/getstokbyitem).
     *
     * @param  array  $params
     * @param  int|null  $userId
     * @return array
     * @throws \Exception
     */
    public function getStockByItem(array $params, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');
        $whsCode = (string) ($params['WhsCode'] ?? $params['whs_code'] ?? $params['warehouse'] ?? '');
        $rawQuery = $params['CustomQuery'] ?? $params['custom_query'] ?? $params['item_codes'] ?? $params['item_code'] ?? $params['items'] ?? '';

        if (empty($whsCode)) {
            throw new \Exception("Parameter 'WhsCode' (kode gudang) wajib diisi.");
        }

        // Format CustomQuery to SQL IN list format with single quotes: e.g. "'B12.B','B26'"
        $formattedQuery = $this->formatCustomQuery($rawQuery);
        if (empty($formattedQuery)) {
            throw new \Exception("Parameter 'CustomQuery' atau 'item_codes' wajib diisi.");
        }

        // Format WhsCode to single quotes format: e.g. "'FG01'"
        $formattedWhsCode = $this->formatWhsCode($whsCode);

        $sapPayload = [
            'CustomQuery' => $formattedQuery,
            'WhsCode'     => $formattedWhsCode,
        ];

        $response = Http::timeout(30)->post("{$sapUrl}/api/getstokbyitem", $sapPayload);

        if (!$response->successful()) {
            throw new \Exception("Gagal menghubungi API SAP getstokbyitem (HTTP {$response->status()}).");
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && (int)$body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        $items = $body['Result'] ?? $body['data'] ?? $body['Items'] ?? (is_array($body) && array_is_list($body) ? $body : []);

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'GET_STOCK_BY_ITEM_SAP',
                "Checked stock in SAP for Warehouse [{$formattedWhsCode}] with query: {$formattedQuery}"
            );
        }

        return [
            'whs_code'     => $formattedWhsCode,
            'custom_query' => $formattedQuery,
            'total_items'  => count($items),
            'items'        => $items,
            'raw'          => $body,
        ];
    }

    /**
     * Format raw input item codes into SAP CustomQuery format: "'ITEM1','ITEM2'".
     */
    protected function formatCustomQuery(mixed $raw): string
    {
        if (is_array($raw)) {
            $cleaned = array_filter(array_map(function ($item) {
                if (is_array($item)) {
                    $val = $item['ItemCode'] ?? $item['item_code'] ?? $item['code'] ?? $item['value'] ?? '';
                    return trim((string)$val, " '\"");
                }
                return trim((string)$item, " '\"");
            }, $raw));

            if (empty($cleaned)) {
                return '';
            }

            return "'" . implode("','", array_unique($cleaned)) . "'";
        }

        $str = trim((string) $raw);
        if (empty($str)) {
            return '';
        }

        // If string already contains properly formatted single quotes e.g. "'B12.B','B26'"
        if (str_contains($str, "'")) {
            return $str;
        }

        // If comma-separated or space-separated plain string e.g. "B12.B, B26"
        $parts = array_filter(array_map(fn($p) => trim($p, " '\""), explode(',', $str)));
        if (empty($parts)) {
            return '';
        }

        return "'" . implode("','", array_unique($parts)) . "'";
    }

    /**
     * Format raw input warehouse code into SAP single-quoted format: e.g. "'FG01'".
     */
    protected function formatWhsCode(mixed $raw): string
    {
        if (is_array($raw)) {
            $cleaned = array_filter(array_map(fn($w) => trim((string)$w, " '\""), $raw));
            if (empty($cleaned)) {
                return "''";
            }
            return "'" . implode("','", array_unique($cleaned)) . "'";
        }

        $str = trim((string) $raw);
        if (empty($str)) {
            return "''";
        }

        if (str_contains($str, "'")) {
            return $str;
        }

        $parts = array_filter(array_map(fn($p) => trim($p, " '\""), explode(',', $str)));
        if (empty($parts)) {
            return "'{$str}'";
        }

        return "'" . implode("','", array_unique($parts)) . "'";
    }
}
