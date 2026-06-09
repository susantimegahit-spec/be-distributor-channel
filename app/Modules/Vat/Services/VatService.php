<?php

namespace App\Modules\Vat\Services;

use App\Modules\Vat\Repositories\VatRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

class VatService
{
    protected VatRepositoryInterface $vatRepository;
    protected AuditLogService $auditLogService;

    /**
     * VatService constructor.
     *
     * @param  VatRepositoryInterface  $vatRepository
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(
        VatRepositoryInterface $vatRepository,
        AuditLogService $auditLogService
    ) {
        $this->vatRepository = $vatRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get all vats.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->vatRepository->getAll($filters);
    }

    /**
     * Synchronize vats from SAP.
     *
     * @param  int|null  $userId
     * @return array
     */
    public function syncFromSap(?int $userId = null): array
    {
        $response = Http::timeout(15)->post('http://103.18.133.187:3100/api/ListVat');

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk sinkronisasi master pajak.');
        }

        $body = $response->json();
        
        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        $sapData = $body['Result'] ?? [];
        $synced = [];

        foreach ($sapData as $item) {
            $synced[] = $this->vatRepository->upsertByCode([
                'code' => $item['Code'],
                'name' => $item['Name'],
                'rate' => $item['Rate'] ?? 0.000000,
                'status' => 1,
            ]);
        }

        // Log to Audit Log if user is authenticated
        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'SYNC_VATS',
                'Synchronized ' . count($synced) . ' vats from SAP.'
            );
        }

        return $synced;
    }
}
