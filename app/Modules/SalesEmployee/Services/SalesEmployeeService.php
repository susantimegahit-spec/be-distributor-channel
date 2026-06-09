<?php

namespace App\Modules\SalesEmployee\Services;

use App\Modules\SalesEmployee\Repositories\SalesEmployeeRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

class SalesEmployeeService
{
    protected SalesEmployeeRepositoryInterface $salesEmployeeRepository;
    protected AuditLogService $auditLogService;

    /**
     * SalesEmployeeService constructor.
     *
     * @param  SalesEmployeeRepositoryInterface  $salesEmployeeRepository
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(
        SalesEmployeeRepositoryInterface $salesEmployeeRepository,
        AuditLogService $auditLogService
    ) {
        $this->salesEmployeeRepository = $salesEmployeeRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get all sales employees.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->salesEmployeeRepository->getAll($filters);
    }

    /**
     * Synchronize sales employees from SAP.
     *
     * @param  int|null  $userId
     * @return array
     */
    public function syncFromSap(?int $userId = null): array
    {
        $response = Http::timeout(15)->post('http://103.18.133.187:3100/api/ListSalesEmp');

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk sinkronisasi Sales Employee.');
        }

        $body = $response->json();
        
        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        $sapData = $body['Result'] ?? [];
        $synced = [];

        foreach ($sapData as $item) {
            $synced[] = $this->salesEmployeeRepository->upsertByCode([
                'slp_code' => $item['SlpCode'],
                'slp_name' => $item['SlpName'],
                'status' => 1,
            ]);
        }

        // Log to Audit Log if user is authenticated
        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'SYNC_SALES_EMPLOYEES',
                'Synchronized ' . count($synced) . ' sales employees from SAP.'
            );
        }

        return $synced;
    }
}
