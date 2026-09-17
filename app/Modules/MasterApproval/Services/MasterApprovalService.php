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
    public function getStagesFromSap(array $payload = [], ?int $userId = null, bool $forceRefresh = false): array
    {
        $cacheKey = empty($payload)
            ? 'sap_approval_stages_all'
            : 'sap_approval_stages_' . md5(json_encode($payload));

        $cacheTtl = (int) config('services.sap.cache_ttl', 60); // 1 minute default

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
    public function getOriginatorsFromSap(array $payload = [], ?int $userId = null, bool $forceRefresh = false): array
    {
        $cacheKey = empty($payload)
            ? 'sap_originators_all'
            : 'sap_originators_' . md5(json_encode($payload));

        $cacheTtl = (int) config('services.sap.cache_ttl', 60); // 1 minute default

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
    public function getApprovalsFromSap(array $payload = [], ?int $userId = null, bool $forceRefresh = false): array
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

        // Normalize Status for SAP & save original filter
        $filterStatus = $payload['Status'] ?? $payload['status'] ?? null;
        if (isset($payload['status'])) {
            unset($payload['status']);
        }
        if ($filterStatus !== null && $filterStatus !== '') {
            $upperStatus = strtoupper(trim((string) $filterStatus));
            if (in_array($upperStatus, ['G', 'GENERATED', 'Y', 'APPROVED'])) {
                $payload['Status'] = 'Y';
            } elseif (in_array($upperStatus, ['W', 'PENDING'])) {
                $payload['Status'] = 'W';
            } elseif (in_array($upperStatus, ['N', 'REJECTED'])) {
                $payload['Status'] = 'N';
            } elseif (in_array($upperStatus, ['C', 'CANCELED', 'CANCELLED'])) {
                $payload['Status'] = 'C';
            }
        }

        $cacheKey = 'sap_approvals_' . md5(json_encode($payload));
        $cacheTtl = (int) config('services.sap.cache_ttl', 60); // 1 minute default

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $cacheTtl, function () use ($payload, $filterStatus, $userId) {
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
                'G' => 'Generated',
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
                        $docEntry = trim((string) ($item['DocEntry'] ?? ''));

                        // Status Y dari SAP:
                        // Urutan approval: Pending -> Approve -> Generated
                        // 1. Generated: Status == 'Y' DAN DocEntry > 0 (dokumen target SAP telah terbentuk)
                        // 2. Approved: Status == 'Y' DAN (DocEntry == '0' atau null atau '') (disetujui, menunggu generate dokumen)
                        if ($rawStatus === 'Y') {
                            if ($docEntry !== '' && $docEntry !== '0' && is_numeric($docEntry) && (int) $docEntry > 0) {
                                $rawStatus = 'G'; // Generated
                            } else {
                                $rawStatus = 'Y'; // Approved
                            }
                        }

                        $item['raw_status'] = $rawStatus;
                        $item['Status'] = $statusMap[$rawStatus] ?? ($rawStatus === 'W' ? 'Pending' : $item['Status']);
                        if (isset($item['StatusCode'])) {
                            $item['StatusCode'] = $rawStatus;
                        }
                        if (isset($item['StatusDescription'])) {
                            $item['StatusDescription'] = $item['Status'];
                        }
                    }

                    // Map CurrStep to Stage Name from getstages
                    $currStep = trim((string) ($item['CurrStep'] ?? ''));
                    $matchedStage = $stagesMap[$currStep] ?? null;

                    $item['Name'] = $matchedStage['Name'] ?? '';
                    // Remarks diambil dari data approval list SAP asli, bukan ditimpa oleh remarks current step
                    $item['Remarks'] = $item['Remarks'] ?? '';
                    if ($matchedStage && !empty($matchedStage['Remarks'])) {
                        $item['StageRemarks'] = $matchedStage['Remarks'];
                    }
                }
                return $item;
            }, $filteredResult);

            // Filter by status if requested by user in payload
            if ($filterStatus !== null && $filterStatus !== '') {
                $cleanFilter = strtoupper(trim((string) $filterStatus));
                $target = match ($cleanFilter) {
                    'W', 'PENDING'   => 'Pending',
                    'Y', 'APPROVED'  => 'Approved',
                    'G', 'GENERATED' => 'Generated',
                    'N', 'REJECTED'  => 'Rejected',
                    'C', 'CANCELED', 'CANCELLED' => 'Canceled',
                    default => $cleanFilter,
                };
                $result = array_values(array_filter($result, function ($item) use ($target) {
                    return strcasecmp($item['Status'] ?? '', $target) === 0
                        || strcasecmp($item['raw_status'] ?? '', $target) === 0;
                }));
            }

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
    public function getOwnerDocumentsFromSap(array $payload = [], ?int $userId = null, bool $forceRefresh = false): array
    {
        // Normalize payload parameters
        $sapPayload = [];

        // CustomQuery (Originator ID)
        $customQuery = $payload['CustomQuery'] ?? $payload['custom_query'] ?? $payload['originator_id'] ?? null;
        if ($customQuery !== null && $customQuery !== '') {
            $sapPayload['CustomQuery'] = (int) $customQuery;
        } elseif ($userId) {
            // Check if authenticated user has originator assigned in users table
            $user = \App\Models\User::find($userId);
            if ($user && !empty($user->originator)) {
                $sapPayload['CustomQuery'] = (int) $user->originator;
            }
        }

        // If CustomQuery (Originator ID) is empty or 0, return empty array (Data not found)
        if (empty($sapPayload['CustomQuery'])) {
            return [];
        }

        // UserId (Optional)
        if (isset($payload['UserId']) && $payload['UserId'] !== '') {
            $sapPayload['UserId'] = (string) $payload['UserId'];
        } elseif (isset($payload['user_id']) && $payload['user_id'] !== '') {
            $sapPayload['UserId'] = (string) $payload['user_id'];
        }

        // ObjectCode (SAP Object Type, dynamic without hardcoded default)
        if (isset($payload['ObjectCode'])) {
            $sapPayload['ObjectCode'] = (string) $payload['ObjectCode'];
        } elseif (isset($payload['object_code'])) {
            $sapPayload['ObjectCode'] = (string) $payload['object_code'];
        } elseif (isset($payload['obj_type'])) {
            $sapPayload['ObjectCode'] = (string) $payload['obj_type'];
        } else {
            $sapPayload['ObjectCode'] = '';
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

        // Status filter will be applied in PHP post-filter to avoid SAP ODBC column mismatch
        $filterStatus = $payload['Status'] ?? $payload['status'] ?? null;

        $cacheKey = 'sap_owner_approvals_' . md5(json_encode($sapPayload) . '_' . ($filterStatus ?? ''));
        $cacheTtl = (int) config('services.sap.cache_ttl', 60); // 1 minute default

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $cacheTtl, function () use ($sapPayload, $filterStatus, $userId) {
            $sapUrl = $this->getSapBaseUrl();

            try {
                $response = Http::timeout(30)->post("{$sapUrl}/api/GetListByOwnerId", $sapPayload);

                // If SAP CustomQuery 62 query template has column mismatch bug with UserId, retry without UserId
                if ($response->successful() && isset($sapPayload['UserId'])) {
                    $testBody = $response->json();
                    if (isset($testBody['ErrorCode']) && $testBody['ErrorCode'] !== 0) {
                        $errorMsg = $testBody['Message'] ?? '';
                        if (str_contains($errorMsg, 'number of columns mismatch') || str_contains($errorMsg, '426')) {
                            $fallbackPayload = $sapPayload;
                            unset($fallbackPayload['UserId']);
                            $retryResponse = Http::timeout(30)->post("{$sapUrl}/api/GetListByOwnerId", $fallbackPayload);
                            if ($retryResponse->successful() && ($retryResponse->json()['ErrorCode'] ?? 1) === 0) {
                                $response = $retryResponse;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
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
                'G' => 'Generated',
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
                        $docEntry = trim((string) ($item['DocEntry'] ?? ''));

                        // Status Y dari SAP:
                        // Urutan approval: Pending -> Approve -> Generated
                        // 1. Generated: Status == 'Y' DAN DocEntry > 0 (dokumen target SAP telah terbentuk)
                        // 2. Approved: Status == 'Y' DAN (DocEntry == '0' atau null atau '') (disetujui, menunggu generate dokumen)
                        if ($rawStatus === 'Y') {
                            if ($docEntry !== '' && $docEntry !== '0' && is_numeric($docEntry) && (int) $docEntry > 0) {
                                $rawStatus = 'G'; // Generated
                            } else {
                                $rawStatus = 'Y'; // Approved
                            }
                        }

                        $item['raw_status'] = $rawStatus;
                        $item['Status'] = $statusMap[$rawStatus] ?? ($rawStatus === 'W' ? 'Pending' : $item['Status']);
                        if (isset($item['StatusCode'])) {
                            $item['StatusCode'] = $rawStatus;
                        }
                        if (isset($item['StatusDescription'])) {
                            $item['StatusDescription'] = $item['Status'];
                        }
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

            // Filter by status if requested by user in payload
            $filterStatus = $payload['Status'] ?? $payload['status'] ?? null;
            if ($filterStatus !== null && $filterStatus !== '') {
                $cleanFilter = strtoupper(trim((string) $filterStatus));
                $target = match ($cleanFilter) {
                    'W', 'PENDING'   => 'Pending',
                    'Y', 'APPROVED'  => 'Approved',
                    'G', 'GENERATED' => 'Generated',
                    'N', 'REJECTED'  => 'Rejected',
                    'C', 'CANCELED', 'CANCELLED' => 'Canceled',
                    default => $cleanFilter,
                };
                $result = array_values(array_filter($result, function ($item) use ($target) {
                    return strcasecmp($item['Status'] ?? '', $target) === 0
                        || strcasecmp($item['raw_status'] ?? '', $target) === 0;
                }));
            }

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
    public function getDocumentDetailFromSap(array $payload = [], ?int $userId = null, bool $forceRefresh = false): array
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

        // ObjectCode (SAP Object Type, dynamic without hardcoded default)
        if (isset($payload['ObjectCode'])) {
            $sapPayload['ObjectCode'] = (string) $payload['ObjectCode'];
        } elseif (isset($payload['object_code'])) {
            $sapPayload['ObjectCode'] = (string) $payload['object_code'];
        } elseif (isset($payload['obj_type'])) {
            $sapPayload['ObjectCode'] = (string) $payload['obj_type'];
        } else {
            $sapPayload['ObjectCode'] = '';
        }



        // Status (e.g. 'W' for draft/pending approval, 'Y' for approved/generated, etc.)
        $status = $payload['Status'] ?? $payload['status'] ?? null;
        if ($status !== null && $status !== '') {
            $cleanStatus = strtoupper(trim((string) $status));
            $reverseMap = [
                'PENDING'   => 'W',
                'APPROVED'  => 'Y',
                'GENERATED' => 'Y',
                'REJECTED'  => 'N',
                'CANCELED'  => 'C',
                'CANCELLED' => 'C',
            ];
            $sapPayload['Status'] = $reverseMap[$cleanStatus] ?? $cleanStatus;
        }

        if (empty($sapPayload['CustomQuery'])) {
            throw new \Exception('CustomQuery (DocEntry) parameter is required.');
        }

        $cacheKey = 'sap_doc_detail_' . md5(json_encode($sapPayload));
        $cacheTtl = (int) config('services.sap.cache_ttl', 60); // 1 minute default

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

            // Helper to check if Table1 contains valid non-dummy header record
            $hasValidHeader = function (array $t1): bool {
                foreach ($t1 as $h) {
                    if (is_array($h)) {
                        $docEntry = trim((string) ($h['DocEntry'] ?? ''));
                        if ($docEntry !== '' && $docEntry !== '0') {
                            return true;
                        }
                    }
                }
                return false;
            };

            // If Status was not provided and result has dummy 0, auto-fallback to Status = 'W' (draft document)
            if (!isset($sapPayload['Status']) && !$hasValidHeader($table1)) {
                try {
                    $fallbackPayload = $sapPayload;
                    $fallbackPayload['Status'] = 'W';
                    $fallbackRes = Http::timeout(30)->post("{$sapUrl}/api/GetDetailByObjectCode", $fallbackPayload);
                    if ($fallbackRes->successful()) {
                        $fallbackBody = $fallbackRes->json();
                        $fallbackTable1 = $fallbackBody['Result']['Table1'] ?? [];
                        if ($hasValidHeader($fallbackTable1)) {
                            $result = $fallbackBody['Result'] ?? [];
                            $table1 = $result['Table1'] ?? [];
                            $table2 = $result['Table2'] ?? [];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Fallback with Status W for GetDetailByObjectCode failed: ' . $e->getMessage());
                }
            }

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

