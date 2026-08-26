<?php

namespace App\Modules\MasterApproval\Services;

use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MasterApprovalService
{
    protected ?AuditLogService $auditLogService;

    /**
     * MasterApprovalService constructor.
     *
     * @param AuditLogService|null $auditLogService
     */
    public function __construct(?AuditLogService $auditLogService = null)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get approval stages from SAP API (/api/getstages).
     *
     * @param array $payload
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function getStagesFromSap(array $payload = [], ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');

        if (empty($sapUrl)) {
            throw new \Exception('Konfigurasi URL SAP (services.sap.url / SAP_API_URL) belum diatur di .env.');
        }

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/getstages", $payload);
        } catch (\Exception $e) {
            Log::error('SAP GetStages connection error: ' . $e->getMessage());
            throw new \Exception('Gagal terhubung ke API SAP untuk mengambil stages: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            throw new \Exception('API SAP getstages mengembalikan status code ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            $errorMsg = $body['Message'] ?? 'Unknown error from SAP';
            Log::error('SAP GetStages returned ErrorCode: ' . $errorMsg);
            throw new \Exception('API SAP getstages error: ' . $errorMsg);
        }

        $result = $body['Result'] ?? [];

        // Log audit if user is authenticated
        if ($userId && $this->auditLogService) {
            try {
                $this->auditLogService->log(
                    userId: $userId,
                    action: 'FETCH_SAP_STAGES',
                    modelType: 'ApprovalStage',
                    modelId: 0,
                    oldValues: [],
                    newValues: ['count' => count($result)]
                );
            } catch (\Throwable $e) {
                // Ignore audit log failure
            }
        }

        return $result;
    }
}
