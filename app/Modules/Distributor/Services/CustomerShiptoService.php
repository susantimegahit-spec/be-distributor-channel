<?php

namespace App\Modules\Distributor\Services;

use App\Modules\Distributor\Repositories\CustomerShiptoRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;

class CustomerShiptoService
{
    protected CustomerShiptoRepositoryInterface $shiptoRepository;
    protected AuditLogService $auditLogService;

    /**
     * CustomerShiptoService constructor.
     *
     * @param CustomerShiptoRepositoryInterface $shiptoRepository
     * @param AuditLogService $auditLogService
     */
    public function __construct(
        CustomerShiptoRepositoryInterface $shiptoRepository,
        AuditLogService $auditLogService
    ) {
        $this->shiptoRepository = $shiptoRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get paginated customer shiptos with filters.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->shiptoRepository->getPaginated($filters, $perPage);
    }

    /**
     * Synchronize customer shiptos from SAP.
     *
     * @param int|null $userId
     * @return array
     */
    public function syncFromSap(?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(15)->post("{$sapUrl}/api/ListKiriman");

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk sinkronisasi Ship To master.');
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        $sapData = $body['Result'] ?? [];
        $synced = [];

        foreach ($sapData as $item) {
            if (empty($item['CardCode']) || empty($item['Address'])) {
                continue;
            }

            $synced[] = $this->shiptoRepository->upsert([
                'card_code' => $item['CardCode'],
                'name' => $item['NAME'] ?? null,
                'address' => $item['Address'],
                'city' => $item['City'] ?? null,
                'street' => $item['Street'] ?? null,
            ]);
        }

        // Log audit trail
        $this->auditLogService->log(
            $userId,
            'Sync Ship To Master',
            'Sinkronisasi Ship To Master dari SAP berhasil. Jumlah data: ' . count($synced)
        );

        return $synced;
    }
}
