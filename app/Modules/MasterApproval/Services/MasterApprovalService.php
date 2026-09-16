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
     * Get SAP API Base URL from configuration.
     *
     * @return string
     * @throws \Exception
     */
    protected function getSapBaseUrl(): string
    {
        $sapUrl = config('services.sap.url') ?: env('SAP_API_URL');

        if (empty($sapUrl)) {
            throw new \Exception('SAP URL configuration (services.sap.url / SAP_API_URL) is not configured in .env.');
        }

        return rtrim($sapUrl, '/');
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
            $sapUrl = $this->getSapBaseUrl();

            try {
                $response = Http::timeout(30)->post("{$sapUrl}/api/getstages", $payload);
            } catch (\Exception $e) {
                Log::error('SAP GetStages connection error: ' . $e->getMessage());
                throw new \Exception('Failed to connect to SAP API for stages: ' . $e->getMessage());
            }

            if (!$response->successful()) {
                throw new \Exception('SAP getstages returned status code ' . $response->status());
            }

            $body = $response->json();

            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                $errorMsg = $body['Message'] ?? 'Unknown error from SAP';
                Log::error('SAP GetStages returned ErrorCode: ' . $errorMsg);
                throw new \Exception('SAP getstages error: ' . $errorMsg);
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
     * Get originators list from SAP API (/api/GetOriginator) with Cache strategy.
     *
     * @param array $payload
     * @param int|null $userId
     * @param bool $forceRefresh
     * @return array
     * @throws \Exception
     */
    public function getOriginatorsFromSap(array $payload = [], ?int $userId = null, bool $forceRefresh = true): array
    {
        $cacheKey = empty($payload)
            ? 'sap_originators_all'
            : 'sap_originators_' . md5(json_encode($payload));

        $cacheTtl = (int) config('services.sap.cache_ttl', 1800); // 30 minutes default

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $cacheTtl, function () use ($payload, $userId) {
            $sapUrl = $this->getSapBaseUrl();

            try {
                $response = Http::timeout(30)->post("{$sapUrl}/api/GetOriginator", $payload);
            } catch (\Exception $e) {
                Log::error('SAP GetOriginator connection error: ' . $e->getMessage());
                throw new \Exception('Failed to connect to SAP API for originators: ' . $e->getMessage());
            }

            if (!$response->successful()) {
                throw new \Exception('SAP GetOriginator returned status code ' . $response->status());
            }

            $body = $response->json();

            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                $errorMsg = $body['Message'] ?? 'Unknown error from SAP';
                Log::error('SAP GetOriginator returned ErrorCode: ' . $errorMsg);
                throw new \Exception('SAP GetOriginator error: ' . $errorMsg);
            }

            $rawResult = $body['Result'] ?? [];

            // Filter out dummy/empty placeholder record from SAP (Rule 5)
            $result = array_values(array_filter($rawResult, function ($item) {
                if (!is_array($item)) {
                    return false;
                }
                $userId = trim((string) ($item['USERID'] ?? ''));
                $userCode = trim((string) ($item['USER_CODE'] ?? ''));
                return !($userId === '' || $userId === '0' || $userCode === '');
            }));

            // Log audit if user is authenticated
            if ($userId && $this->auditLogService) {
                try {
                    $this->auditLogService->log(
                        userId: $userId,
                        action: 'FETCH_SAP_ORIGINATORS',
                        modelType: 'OriginatorList',
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
     * Find originator detail from SAP originators list by originator key or username.
     *
     * @param string|null $originatorKey
     * @param string|null $fallbackUsername
     * @return array|null
     */
    public function findOriginatorDetail(?string $originatorKey, ?string $fallbackUsername = null): ?array
    {
        try {
            // Force refresh is false to leverage 30-minute cache
            $originators = $this->getOriginatorsFromSap([], null, false);

            if (!empty($originatorKey)) {
                $originatorKeyStr = trim((string) $originatorKey);
                foreach ($originators as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $userId = trim((string) ($item['USERID'] ?? ''));
                    $userCode = trim((string) ($item['USER_CODE'] ?? ''));

                    if ($userId === $originatorKeyStr || strcasecmp($userCode, $originatorKeyStr) === 0) {
                        return $item;
                    }
                }
            }

            if (!empty($fallbackUsername)) {
                $fallbackUsernameStr = trim((string) $fallbackUsername);
                foreach ($originators as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $userCode = trim((string) ($item['USER_CODE'] ?? ''));
                    if (strcasecmp($userCode, $fallbackUsernameStr) === 0) {
                        return $item;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to find originator detail from SAP: ' . $e->getMessage());
        }

        return null;
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
            $sapUrl = $this->getSapBaseUrl();

            try {
                $response = Http::timeout(30)->post("{$sapUrl}/api/getapproval", $payload);
            } catch (\Exception $e) {
                Log::error('SAP GetApproval connection error: ' . $e->getMessage());
                throw new \Exception('Failed to connect to SAP API for approval list: ' . $e->getMessage());
            }

            if (!$response->successful()) {
                throw new \Exception('SAP getapproval returned status code ' . $response->status());
            }

            $body = $response->json();

            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                $errorMsg = $body['Message'] ?? 'Unknown error from SAP';
                Log::error('SAP GetApproval returned ErrorCode: ' . $errorMsg);
                throw new \Exception('SAP getapproval error: ' . $errorMsg);
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

            // Fetch approval stages to map CurrStep -> Stage Name & Remarks
            $stagesMap = [];
            try {
                $stages = $this->getStagesFromSap([], null, false);
                foreach ($stages as $stage) {
                    if (is_array($stage)) {
                        $wstCode = trim((string) ($stage['WstCode'] ?? ''));
                        if ($wstCode !== '') {
                            $stagesMap[$wstCode] = $stage;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch stages for GetApproval mapping: ' . $e->getMessage());
            }

            $result = array_map(function ($item) use ($statusMap, $stagesMap) {
                if (is_array($item)) {
                    if (isset($item['Status'])) {
                        $rawStatus = strtoupper(trim((string) $item['Status']));
                        $item['raw_status'] = $rawStatus;
                        $item['Status'] = $statusMap[$rawStatus] ?? ($rawStatus === 'W' ? 'Pending' : $item['Status']);
                    }

                    // Map CurrStep to Stage Name and Remarks from getstages
                    $currStep = trim((string) ($item['CurrStep'] ?? ''));
                    $matchedStage = $stagesMap[$currStep] ?? null;

                    $item['Name'] = $matchedStage['Name'] ?? '';
                    if ($matchedStage && !empty($matchedStage['Remarks'])) {
                        $item['Remarks'] = $matchedStage['Remarks'];
                    } elseif (!isset($item['Remarks'])) {
                        $item['Remarks'] = '';
                    }
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
        $sapUrl = $this->getSapBaseUrl();

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
            throw new \Exception('Failed to connect to SAP API to process approval: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            throw new \Exception('SAP ApproveSAP returned status code ' . $response->status());
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            $errorMsg = $body['Message'] ?? 'Unknown error from SAP';
            Log::error('SAP ApproveSAP returned ErrorCode: ' . $errorMsg);
            throw new \Exception('SAP ApproveSAP error: ' . $errorMsg);
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
     * Get owner / my document approval list from SAP API (/api/GetListByOwnerId).
     *
     * @param array $payload
     * @param int|null $userId
     * @param bool $forceRefresh
     * @return array
     * @throws \Exception
     */
    public function getOwnerDocumentsFromSap(array $payload = [], ?int $userId = null, bool $forceRefresh = true): array
    {
        // Normalize payload parameters
        $sapPayload = [];

        // CustomQuery (Originator ID)
        if (isset($payload['CustomQuery'])) {
            $sapPayload['CustomQuery'] = (int) $payload['CustomQuery'];
        } elseif (isset($payload['custom_query'])) {
            $sapPayload['CustomQuery'] = (int) $payload['custom_query'];
        } elseif (isset($payload['originator_id'])) {
            $sapPayload['CustomQuery'] = (int) $payload['originator_id'];
        } else {
            $sapPayload['CustomQuery'] = 62; // Default originator query ID
        }

        // UserId (Optional)
        if (isset($payload['UserId']) && $payload['UserId'] !== '') {
            $sapPayload['UserId'] = (string) $payload['UserId'];
        } elseif (isset($payload['user_id']) && $payload['user_id'] !== '') {
            $sapPayload['UserId'] = (string) $payload['user_id'];
        }

        // ObjectCode (Required SAP Object Type)
        if (isset($payload['ObjectCode']) && $payload['ObjectCode'] !== '') {
            $sapPayload['ObjectCode'] = (string) $payload['ObjectCode'];
        } elseif (isset($payload['object_code']) && $payload['object_code'] !== '') {
            $sapPayload['ObjectCode'] = (string) $payload['object_code'];
        } elseif (isset($payload['obj_type']) && $payload['obj_type'] !== '') {
            $sapPayload['ObjectCode'] = (string) $payload['obj_type'];
        } else {
            $sapPayload['ObjectCode'] = '1470000113';
        }

        // From (Date filter YYYYMMDD)
        $from = $payload['From'] ?? $payload['from'] ?? $payload['date_from'] ?? null;
        if ($from) {
            $sapPayload['From'] = str_replace(['-', '/'], '', trim((string) $from));
        }

        // To (Date filter YYYYMMDD)
        $to = $payload['To'] ?? $payload['to'] ?? $payload['date_to'] ?? null;
        if ($to) {
            $sapPayload['To'] = str_replace(['-', '/'], '', trim((string) $to));
        }

        // Status filter (Optional: W, Y, N, C or human-readable status)
        $status = $payload['Status'] ?? $payload['status'] ?? null;
        if ($status !== null && $status !== '') {
            $cleanStatus = strtoupper(trim((string) $status));
            $reverseMap = [
                'PENDING'   => 'W',
                'APPROVED'  => 'Y',
                'REJECTED'  => 'N',
                'CANCELED'  => 'C',
                'CANCELLED' => 'C',
            ];
            $sapPayload['Status'] = $reverseMap[$cleanStatus] ?? $cleanStatus;
        }

        $cacheKey = 'sap_owner_approvals_' . md5(json_encode($sapPayload));
        $cacheTtl = (int) config('services.sap.cache_ttl', 1800);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $cacheTtl, function () use ($sapPayload, $userId) {
            $sapUrl = $this->getSapBaseUrl();

            try {
                $response = Http::timeout(30)->post("{$sapUrl}/api/GetListByOwnerId", $sapPayload);
            } catch (\Exception $e) {
                Log::error('SAP GetListByOwnerId connection error: ' . $e->getMessage());
                throw new \Exception('Failed to connect to SAP API for owner approvals: ' . $e->getMessage());
            }

            if (!$response->successful()) {
                throw new \Exception('SAP GetListByOwnerId returned status code ' . $response->status());
            }

            $body = $response->json();

            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                $errorMsg = $body['Message'] ?? 'Unknown error from SAP';
                Log::error('SAP GetListByOwnerId returned ErrorCode: ' . $errorMsg);
                throw new \Exception('SAP GetListByOwnerId error: ' . $errorMsg);
            }

            $rawResult = $body['Result'] ?? [];

            // Filter out dummy/empty records (Rule 5)
            $filteredResult = array_values(array_filter($rawResult, function ($item) {
                if (!is_array($item)) {
                    return false;
                }
                $wddCode = trim((string) ($item['WddCode'] ?? ''));
                if ($wddCode === '0' || $wddCode === '') {
                    return false;
                }
                return true;
            }));

            // Status mapping: W -> Pending, Y -> Approved, N -> Rejected, C -> Canceled
            $statusMap = [
                'W' => 'Pending',
                'Y' => 'Approved',
                'N' => 'Rejected',
                'C' => 'Canceled',
            ];

            // Fetch approval stages to map CurrStep -> Stage Name & Remarks
            $stagesMap = [];
            try {
                $stages = $this->getStagesFromSap([], null, false);
                foreach ($stages as $stage) {
                    if (is_array($stage)) {
                        $wstCode = trim((string) ($stage['WstCode'] ?? ''));
                        if ($wstCode !== '') {
                            $stagesMap[$wstCode] = $stage;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch stages for GetListByOwnerId mapping: ' . $e->getMessage());
            }

            $result = array_map(function ($item) use ($statusMap, $stagesMap) {
                if (is_array($item)) {
                    if (isset($item['Status'])) {
                        $rawStatus = strtoupper(trim((string) $item['Status']));
                        $item['raw_status'] = $rawStatus;
                        $item['Status'] = $statusMap[$rawStatus] ?? ($rawStatus === 'W' ? 'Pending' : $item['Status']);
                    }

                    // Map CurrStep to Stage Name and Remarks from getstages
                    $currStep = trim((string) ($item['CurrStep'] ?? ''));
                    $matchedStage = $stagesMap[$currStep] ?? null;

                    $item['Name'] = $matchedStage['Name'] ?? '';
                    if ($matchedStage && !empty($matchedStage['Remarks'])) {
                        $item['Remarks'] = $matchedStage['Remarks'];
                    } elseif (!isset($item['Remarks'])) {
                        $item['Remarks'] = '';
                    }
                }
                return $item;
            }, $filteredResult);

            // Log audit if user is authenticated
            if ($userId && $this->auditLogService) {
                try {
                    $this->auditLogService->log(
                        userId: $userId,
                        action: 'FETCH_SAP_OWNER_DOCUMENTS',
                        modelType: 'ApprovalList',
                        modelId: 0,
                        oldValues: [],
                        newValues: [
                            'payload' => $sapPayload,
                            'count'   => count($result),
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
     * Get document detail by object code from SAP API (/api/GetDetailByObjectCode).
     *
     * @param array $payload
     * @param int|null $userId
     * @param bool $forceRefresh
     * @return array
     * @throws \Exception
     */
    public function getDocumentDetailFromSap(array $payload = [], ?int $userId = null, bool $forceRefresh = true): array
    {
        // Normalize payload parameters
        $sapPayload = [];

        // CustomQuery (DocEntry ID)
        if (isset($payload['CustomQuery'])) {
            $sapPayload['CustomQuery'] = (int) $payload['CustomQuery'];
        } elseif (isset($payload['custom_query'])) {
            $sapPayload['CustomQuery'] = (int) $payload['custom_query'];
        } elseif (isset($payload['doc_entry'])) {
            $sapPayload['CustomQuery'] = (int) $payload['doc_entry'];
        } elseif (isset($payload['DocEntry'])) {
            $sapPayload['CustomQuery'] = (int) $payload['DocEntry'];
        } elseif (isset($payload['id'])) {
            $sapPayload['CustomQuery'] = (int) $payload['id'];
        }

        // ObjectCode (Required SAP Object Type)
        if (isset($payload['ObjectCode']) && $payload['ObjectCode'] !== '') {
            $sapPayload['ObjectCode'] = (string) $payload['ObjectCode'];
        } elseif (isset($payload['object_code']) && $payload['object_code'] !== '') {
            $sapPayload['ObjectCode'] = (string) $payload['object_code'];
        } elseif (isset($payload['obj_type']) && $payload['obj_type'] !== '') {
            $sapPayload['ObjectCode'] = (string) $payload['obj_type'];
        } else {
            $sapPayload['ObjectCode'] = '1470000113';
        }

        if (empty($sapPayload['CustomQuery'])) {
            throw new \Exception('CustomQuery (DocEntry) parameter is required.');
        }

        $cacheKey = 'sap_doc_detail_' . md5(json_encode($sapPayload));
        $cacheTtl = (int) config('services.sap.cache_ttl', 1800);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $cacheTtl, function () use ($sapPayload, $userId) {
            $sapUrl = $this->getSapBaseUrl();

            try {
                $response = Http::timeout(30)->post("{$sapUrl}/api/GetDetailByObjectCode", $sapPayload);
            } catch (\Exception $e) {
                Log::error('SAP GetDetailByObjectCode connection error: ' . $e->getMessage());
                throw new \Exception('Failed to connect to SAP API for document detail: ' . $e->getMessage());
            }

            if (!$response->successful()) {
                throw new \Exception('SAP GetDetailByObjectCode returned status code ' . $response->status());
            }

            $body = $response->json();

            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                $errorMsg = $body['Message'] ?? 'Unknown error from SAP';
                Log::error('SAP GetDetailByObjectCode returned ErrorCode: ' . $errorMsg);
                throw new \Exception('SAP GetDetailByObjectCode error: ' . $errorMsg);
            }

            $result = $body['Result'] ?? [];
            $table1 = $result['Table1'] ?? [];
            $table2 = $result['Table2'] ?? [];

            // Filter dummy / empty header records (Rule 5)
            $filteredTable1 = array_values(array_filter($table1, function ($h) {
                if (!is_array($h)) {
                    return false;
                }
                $docEntry = trim((string) ($h['DocEntry'] ?? ''));
                return !($docEntry === '' || $docEntry === '0');
            }));

            // Filter dummy / empty item records (Rule 5)
            $filteredTable2 = array_values(array_filter($table2, function ($line) {
                if (!is_array($line)) {
                    return false;
                }
                $docEntry = trim((string) ($line['DocEntry'] ?? ''));
                $itemCode = trim((string) ($line['ItemCode'] ?? ''));
                if ($docEntry === '0' || $docEntry === '' || $itemCode === '') {
                    return false;
                }
                return true;
            }));

            $header = !empty($filteredTable1) ? $filteredTable1[0] : null;

            $data = [
                'header' => $header,
                'items'  => $filteredTable2,
                'Table1' => $filteredTable1,
                'Table2' => $filteredTable2,
            ];

            // Log audit if user is authenticated
            if ($userId && $this->auditLogService) {
                try {
                    $this->auditLogService->log(
                        userId: $userId,
                        action: 'FETCH_SAP_DOCUMENT_DETAIL',
                        modelType: 'ApprovalDetail',
                        modelId: (int) ($sapPayload['CustomQuery'] ?? 0),
                        oldValues: [],
                        newValues: [
                            'payload'     => $sapPayload,
                            'has_header'  => $header !== null,
                            'items_count' => count($filteredTable2),
                        ]
                    );
                } catch (\Throwable $e) {
                    // Ignore audit log failure
                }
            }

            return $data;
        });
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

