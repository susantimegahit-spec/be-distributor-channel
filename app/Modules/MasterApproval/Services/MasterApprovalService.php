<?php

namespace App\Modules\MasterApproval\Services;

use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Support\Facades\Cache;
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
     * Get approval stages from SAP API (/api/getstages) with Cache strategy.
     *
     * @param array $payload
     * @param int|null $userId
     * @param bool $forceRefresh
     * @return array
     * @throws \Exception
     */
    public function getStagesFromSap(array $payload = [], ?int $userId = null, bool $forceRefresh = true): array
    {
        $cacheKey = empty($payload)
            ? 'sap_approval_stages_all'
            : 'sap_approval_stages_' . md5(json_encode($payload));

        $cacheTtl = (int) config('services.sap.cache_ttl', 1800); // 30 minutes default

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $cacheTtl, function () use ($payload, $userId) {
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
        });
    }

    /**
     * Get approval list from SAP API (/api/getapproval) with Cache strategy and Status mapping.
     *
     * @param array $payload
     * @param int|null $userId
     * @param bool $forceRefresh
     * @return array
     * @throws \Exception
     */
    public function getApprovalsFromSap(array $payload = [], ?int $userId = null, bool $forceRefresh = true): array
    {
        // Default CustomQuery to 2 if not explicitly provided
        if (!isset($payload['CustomQuery'])) {
            if (isset($payload['custom_query'])) {
                $payload['CustomQuery'] = (int) $payload['custom_query'];
                unset($payload['custom_query']);
            } else {
                $payload['CustomQuery'] = 2;
            }
        }

        $cacheKey = 'sap_approvals_' . md5(json_encode($payload));
        $cacheTtl = (int) config('services.sap.cache_ttl', 1800); // 30 minutes default

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $cacheTtl, function () use ($payload, $userId) {
            $sapUrl = config('services.sap.url') ?: 'http://103.18.133.187:3100';

            if (empty($sapUrl)) {
                throw new \Exception('Konfigurasi URL SAP (services.sap.url / SAP_API_URL) belum diatur di .env.');
            }

            try {
                $response = Http::timeout(30)->post("{$sapUrl}/api/getapproval", $payload);
            } catch (\Exception $e) {
                Log::error('SAP GetApproval connection error: ' . $e->getMessage());
                throw new \Exception('Gagal terhubung ke API SAP untuk mengambil approval list: ' . $e->getMessage());
            }

            if (!$response->successful()) {
                throw new \Exception('API SAP getapproval mengembalikan status code ' . $response->status());
            }

            $body = $response->json();

            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                $errorMsg = $body['Message'] ?? 'Unknown error from SAP';
                Log::error('SAP GetApproval returned ErrorCode: ' . $errorMsg);
                throw new \Exception('API SAP getapproval error: ' . $errorMsg);
            }

            $rawResult = $body['Result'] ?? [];

            // Filter out dummy/empty placeholder record from SAP (e.g. WddCode === "0" or empty)
            $filteredResult = array_values(array_filter($rawResult, function ($item) {
                if (!is_array($item)) {
                    return false;
                }
                $wddCode = trim((string) ($item['WddCode'] ?? ''));
                $status = trim((string) ($item['Status'] ?? ''));
                $docDate = trim((string) ($item['DocDate'] ?? ''));

                // SAP returns dummy row with WddCode 0 when no approval data exists
                if ($wddCode === '0' || $wddCode === '') {
                    return false;
                }

                return true;
            }));

            // Map SAP status code to human-readable status
            // Specifically map 'W' (Waiting / Pending) to 'Pending'
            $statusMap = [
                'W' => 'Pending',
                'Y' => 'Approved',
                'N' => 'Rejected',
                'C' => 'Canceled',
            ];

            $result = array_map(function ($item) use ($statusMap) {
                if (is_array($item) && isset($item['Status'])) {
                    $rawStatus = strtoupper(trim((string) $item['Status']));
                    $item['Status'] = $statusMap[$rawStatus] ?? ($rawStatus === 'W' ? 'Pending' : $item['Status']);
                }
                return $item;
            }, $filteredResult);

            // Log audit if user is authenticated
            if ($userId && $this->auditLogService) {
                try {
                    $this->auditLogService->log(
                        userId: $userId,
                        action: 'FETCH_SAP_APPROVALS',
                        modelType: 'ApprovalList',
                        modelId: 0,
                        oldValues: [],
                        newValues: [
                            'payload' => $payload,
                            'count' => count($result),
                        ]
                    );
                } catch (\Throwable $e) {
                    // Ignore audit log failure
                }
            }

            return $result;
        });
    }

    /**
     * Process approval or rejection in SAP API (/api/ApproveSAP).
     *
     * @param array $payload
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function processApprovalSap(array $payload, ?int $userId = null): array
    {
        $sapUrl = config('services.sap.url') ?: 'http://103.18.133.187:3100';

        if (empty($sapUrl)) {
            throw new \Exception('Konfigurasi URL SAP (services.sap.url / SAP_API_URL) belum diatur di .env.');
        }

        $statusCode = strtoupper(trim((string) ($payload['Status'] ?? $payload['status'] ?? '')));
        $remarks = (string) ($payload['Remarks'] ?? $payload['remarks'] ?? '');

        // Construct SAP payload with exact casing
        $sapPayload = [
            'approvalRequestCode' => (string) ($payload['approvalRequestCode'] ?? $payload['WddCode'] ?? $payload['wdd_code'] ?? $payload['approval_request_code'] ?? ''),
            'Username'            => (string) ($payload['Username'] ?? $payload['username'] ?? ''),
            'Password'            => (string) ($payload['Password'] ?? $payload['password'] ?? ''),
            'Status'              => $statusCode,
            'Remarks'             => $remarks,
        ];

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/ApproveSAP", $sapPayload);
        } catch (\Exception $e) {
            Log::error('SAP ApproveSAP connection error: ' . $e->getMessage());
            throw new \Exception('Gagal terhubung ke API SAP untuk memproses approval: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            throw new \Exception('API SAP ApproveSAP mengembalikan status code ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            $errorMsg = $body['Message'] ?? 'Unknown error from SAP';
            Log::error('SAP ApproveSAP returned ErrorCode: ' . $errorMsg);
            throw new \Exception('API SAP ApproveSAP error: ' . $errorMsg);
        }

        // Flush approval list cache so fresh data will be fetched next time
        $this->clearApprovalsCache();

        // Log audit
        if ($userId && $this->auditLogService) {
            try {
                $this->auditLogService->log(
                    userId: $userId,
                    action: 'SUBMIT_SAP_APPROVAL',
                    modelType: 'ApprovalRequest',
                    modelId: (int) ($sapPayload['approvalRequestCode'] ?: 0),
                    oldValues: [],
                    newValues: [
                        'approvalRequestCode' => $sapPayload['approvalRequestCode'],
                        'status'              => $statusCode,
                        'remarks'             => $remarks,
                    ]
                );
            } catch (\Throwable $e) {
                // Ignore audit log failure
            }
        }

        return $body['Result'] ?? $body;
    }

    /**
     * Clear cached SAP approval stages.
     */
    public function clearStagesCache(): bool
    {
        return Cache::forget('sap_approval_stages_all');
    }

    /**
     * Clear cached SAP approvals.
     */
    public function clearApprovalsCache(): bool
    {
        // Clear general approvals cache
        return Cache::flush(); // or flush cache tags / forget common keys
    }
}

