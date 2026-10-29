<?php

namespace App\Modules\PurchasingRequest\Services;

use App\Exceptions\SapException;
use App\Models\ProductionBom;
use App\Models\PurchaseRequest;
use App\Modules\PurchasingRequest\Repositories\PurchaseRequestRepositoryInterface;
use Illuminate\Support\Facades\Http;

class PurchaseRequestService
{
    protected PurchaseRequestRepositoryInterface $repository;

    public function __construct(PurchaseRequestRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getList(array $filters = [], int $perPage = 15)
    {
        return $this->repository->getAll($filters, $perPage);
    }

    public function getDetail(int $id): ?PurchaseRequest
    {
        return $this->repository->findById($id);
    }

    /**
     * Get available Document Series from SAP B1 for Purchase Request.
     */
    public function getSeries(?string $customQuery = null): array
    {
        $customQuery = $customQuery ?: date('Ymd');
        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        try {
            $response = Http::timeout(15)->post("{$sapUrl}/api/getSeries", [
                'CustomQuery' => $customQuery,
                'Document'    => '1470000113', // SAP Object Type for Purchase Request
            ]);

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['Result']) && is_array($body['Result'])) {
                    return $body['Result'];
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch PR series from SAP: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Get department list from SAP B1 (/api/GetDepartment).
     */
    public function getDepartments(?string $search = null): array
    {
        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        $departments = [];

        try {
            $response = Http::timeout(15)->post("{$sapUrl}/api/GetDepartment");

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['Result']) && is_array($body['Result'])) {
                    $departments = $body['Result'];
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch departments from SAP: ' . $e->getMessage());
        }

        if (empty($departments)) {
            $departments = [
                ['Code' => '-2', 'Remarks' => 'General Department', 'Name' => 'General'],
                ['Code' => '1', 'Remarks' => 'Production', 'Name' => 'PRO'],
                ['Code' => '2', 'Remarks' => 'Quality Control', 'Name' => 'QC'],
                ['Code' => '3', 'Remarks' => 'Warehouse', 'Name' => 'WRH'],
                ['Code' => '4', 'Remarks' => 'Marketing', 'Name' => 'MKT'],
                ['Code' => '5', 'Remarks' => 'Pengiriman (Delivery)', 'Name' => 'PRM'],
                ['Code' => '6', 'Remarks' => 'Management', 'Name' => 'MGM'],
                ['Code' => '7', 'Remarks' => 'Financial & Accounting', 'Name' => 'FAT'],
                ['Code' => '8', 'Remarks' => 'Information Technology', 'Name' => 'IT'],
                ['Code' => '9', 'Remarks' => 'Purchasing', 'Name' => 'PCG'],
                ['Code' => '10', 'Remarks' => 'GA & HRD', 'Name' => 'HRD'],
                ['Code' => '11', 'Remarks' => 'SECURITY', 'Name' => 'SECURITY'],
                ['Code' => '12', 'Remarks' => 'MTC', 'Name' => 'MAINTANANCE'],
                ['Code' => '13', 'Remarks' => 'LGC', 'Name' => 'LOGISTIC'],
            ];
        }

        if ($search !== null && $search !== '') {
            $term = strtolower(trim($search));
            $departments = array_values(array_filter($departments, function ($dept) use ($term) {
                return str_contains(strtolower($dept['Name'] ?? ''), $term)
                    || str_contains(strtolower($dept['Remarks'] ?? ''), $term)
                    || str_contains(strtolower((string)($dept['Code'] ?? '')), $term);
            }));
        }

        return $departments;
    }

    /**
     * Get item list for Purchase Request with default UoM parameters.
     */
    public function getItems(array $filters = []): array
    {
        $search = $filters['search'] ?? null;
        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        try {
            $response = Http::timeout(15)->post("{$sapUrl}/api/ListItem", [
                'CustomQuery' => $search ?: '',
            ]);

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['Result']) && is_array($body['Result'])) {
                    $items = [];
                    foreach ($body['Result'] as $item) {
                        $items[] = [
                            'item_code'  => $item['ItemCode'] ?? '',
                            'item_name'  => $item['ItemName'] ?? '',
                            'uom_entry'  => '-1',
                            'uom_code'   => '-1',
                            'uom'        => $item['SalUnitMsr'] ?? 'Pcs',
                            'per_kg'     => $item['Perkg'] ?? null,
                            'suom_entry' => $item['SUoMEntry'] ?? null,
                        ];
                    }
                    return $items;
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch items from SAP: ' . $e->getMessage());
        }

        // Fallback to local items table
        $localItems = \App\Models\Item::query();
        if ($search) {
            $localItems->where(function ($q) use ($search) {
                $q->where('item_code', 'like', "%{$search}%")
                  ->orWhere('item_name', 'like', "%{$search}%");
            });
        }

        return $localItems->limit(50)->get()->map(function ($it) {
            return [
                'item_code'  => $it->item_code,
                'item_name'  => $it->item_name,
                'uom_entry'  => '-1',
                'uom_code'   => '-1',
                'uom'        => $it->sal_unit_msr ?: 'Pcs',
                'per_kg'     => $it->per_kg,
                'suom_entry' => $it->suom_entry ? (string)$it->suom_entry : null,
            ];
        })->toArray();
    }

    /**
     * Get draft Purchase Requests from SAP B1 API (/api/GetDraftPR).
     *
     * @param array $payload
     * @param int|null $userId
     * @return array
     */
    public function getDraftPR(array $payload, ?int $userId = null): array
    {
        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        // 1. Resolve Originator (CostumQuery)
        $originator = $payload['CostumQuery']
            ?? $payload['CustomQuery']
            ?? $payload['costum_query']
            ?? $payload['custom_query']
            ?? $payload['originator_id']
            ?? $payload['originator']
            ?? null;

        if (($originator === null || $originator === '') && $userId) {
            $user = \App\Models\User::find($userId);
            if ($user && !empty($user->originator)) {
                $originator = (string) $user->originator;
            }
        }

        // 2. Resolve UserId
        $userIdInput = $payload['UserId']
            ?? $payload['user_id']
            ?? null;

        if ($userIdInput === null && $userId) {
            $user = \App\Models\User::find($userId);
            if ($user) {
                $userIdInput = (string) ($user->username ?: $user->id);
            }
        }

        // 3. Resolve From and To dates (format YYYYMMDD)
        $from = $payload['From'] ?? $payload['from'] ?? $payload['date_from'] ?? $payload['start_date'] ?? null;
        $fromClean = $from ? str_replace(['-', '/'], '', trim((string) $from)) : '';

        $to = $payload['To'] ?? $payload['to'] ?? $payload['date_to'] ?? $payload['end_date'] ?? null;
        $toClean = $to ? str_replace(['-', '/'], '', trim((string) $to)) : '';

        // 4. Resolve Status
        $status = $payload['Status'] ?? $payload['status'] ?? '';

        $sapPayload = [
            'CostumQuery' => (string) ($originator ?? ''),
            'CustomQuery' => (string) ($originator ?? ''),
            'UserId'      => (string) ($userIdInput ?? ''),
            'From'        => (string) $fromClean,
            'To'          => (string) $toClean,
            'Status'      => (string) $status,
        ];

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/GetDraftPR", $sapPayload);

            if ($response->successful()) {
                $body = $response->json();
                $errorCode = (int) ($body['ErrorCode'] ?? 0);
                $message = (string) ($body['Message'] ?? '');
                $result = $body['Result'] ?? [];

                if ($errorCode !== 0) {
                    return [
                        'error_code' => $errorCode,
                        'message'    => $message ?: 'Error from SAP GetDraftPR',
                        'data'       => [],
                    ];
                }

                if (is_array($result)) {
                    $filtered = array_values(array_filter($result, function ($item) {
                        return is_array($item) && !$this->isDummyDraftItem($item);
                    }));

                    return [
                        'error_code' => 0,
                        'message'    => empty($filtered) ? 'Data not found' : $message,
                        'data'       => $filtered,
                    ];
                }
            }

            return [
                'error_code' => $response->status(),
                'message'    => 'Failed to communicate with SAP B1: HTTP ' . $response->status(),
                'data'       => [],
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch draft PR from SAP: ' . $e->getMessage());

            return [
                'error_code' => 500,
                'message'    => 'Connection to SAP failed: ' . $e->getMessage(),
                'data'       => [],
            ];
        }
    }

    /**
     * Check if a draft PR item returned from SAP is an empty/dummy placeholder.
     *
     * @param array $item
     * @return bool
     */
    public function isDummyDraftItem(array $item): bool
    {
        $docEntry = (string) ($item['DocEntry'] ?? '0');
        $draftEntry = (string) ($item['DraftEntry'] ?? '0');
        $draftNum = (string) ($item['DraftNum'] ?? '0');
        $docNum = (string) ($item['DocNum'] ?? '0');
        $wddCode = (string) ($item['WddCode'] ?? '0');
        $docDate = trim((string) ($item['DocDate'] ?? ''));
        $status = trim((string) ($item['Status'] ?? ''));
        $statusCode = trim((string) ($item['StatusCode'] ?? ''));
        $remarks = trim((string) ($item['Remarks'] ?? ''));
        $objType = trim((string) ($item['ObjType'] ?? ''));

        $isZeroOrEmpty = function ($val) {
            return $val === '' || $val === '0' || $val === 0 || $val === '0.000000';
        };

        if ($isZeroOrEmpty($docEntry)
            && $isZeroOrEmpty($draftEntry)
            && $isZeroOrEmpty($draftNum)
            && $isZeroOrEmpty($docNum)
            && $isZeroOrEmpty($wddCode)
            && $docDate === ''
            && $status === ''
            && $statusCode === ''
            && $objType === ''
            && $remarks === ''
        ) {
            return true;
        }

        return false;
    }

    /**
     * Get list of generated Purchase Requests from SAP B1 API (/api/GetListPRGenerated).
     *
     * @param array $payload
     * @param int|null $userId
     * @return array
     */
    public function getListPRGenerated(array $payload, ?int $userId = null): array
    {
        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        // 1. Resolve CostumQuery (e.g. "IT", "PRO", department name/code or search query)
        $customQuery = $payload['CostumQuery']
            ?? $payload['CustomQuery']
            ?? $payload['costum_query']
            ?? $payload['custom_query']
            ?? $payload['department']
            ?? $payload['search']
            ?? '';

        // 2. Resolve From and To dates (format YYYYMMDD)
        $from = $payload['From'] ?? $payload['from'] ?? $payload['date_from'] ?? $payload['start_date'] ?? null;
        $fromClean = $from ? str_replace(['-', '/'], '', trim((string) $from)) : '';

        $to = $payload['To'] ?? $payload['to'] ?? $payload['date_to'] ?? $payload['end_date'] ?? null;
        $toClean = $to ? str_replace(['-', '/'], '', trim((string) $to)) : '';

        $sapPayload = [
            'CostumQuery' => (string) $customQuery,
            'CustomQuery' => (string) $customQuery,
            'From'        => (string) $fromClean,
            'To'          => (string) $toClean,
        ];

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/GetListPRGenerated", $sapPayload);

            if ($response->successful()) {
                $body = $response->json();
                $errorCode = (int) ($body['ErrorCode'] ?? 0);
                $message = (string) ($body['Message'] ?? '');
                $result = $body['Result'] ?? [];

                if ($errorCode !== 0) {
                    return [
                        'error_code' => $errorCode,
                        'message'    => $message ?: 'Error from SAP GetListPRGenerated',
                        'data'       => [],
                    ];
                }

                if (is_array($result)) {
                    $filtered = array_values(array_filter($result, function ($item) {
                        return is_array($item) && !$this->isDummyGeneratedPRItem($item);
                    }));

                    return [
                        'error_code' => 0,
                        'message'    => empty($filtered) ? 'Data not found' : $message,
                        'data'       => $filtered,
                    ];
                }
            }

            return [
                'error_code' => $response->status(),
                'message'    => 'Failed to communicate with SAP B1: HTTP ' . $response->status(),
                'data'       => [],
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch generated PR list from SAP: ' . $e->getMessage());

            return [
                'error_code' => 500,
                'message'    => 'Connection to SAP failed: ' . $e->getMessage(),
                'data'       => [],
            ];
        }
    }

    /**
     * Check if a generated PR item returned from SAP is an empty/dummy placeholder.
     *
     * @param array $item
     * @return bool
     */
    public function isDummyGeneratedPRItem(array $item): bool
    {
        $docEntry = (string) ($item['DocEntry'] ?? '0');
        $docNum = (string) ($item['DocNum'] ?? '0');
        $docDate = trim((string) ($item['DocDate'] ?? ''));

        $isZeroOrEmpty = function ($val) {
            return $val === '' || $val === '0' || $val === 0;
        };

        return $isZeroOrEmpty($docEntry) && $isZeroOrEmpty($docNum) && $docDate === '';
    }

    /**
     * Close Purchase Request in SAP B1 (/api/ClosePR).
     *
     * @param array|string|int $payload
     * @param int|null $userId
     * @return array
     */
    public function closePR($payload, ?int $userId = null): array
    {
        $docEntry = is_array($payload)
            ? ($payload['DocEntry'] ?? $payload['doc_entry'] ?? $payload['id'] ?? '')
            : (string) $payload;

        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        $sapPayload = [
            'DocEntry' => (string) $docEntry,
        ];

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/ClosePR", $sapPayload);

            if ($response->successful()) {
                $body = $response->json();
                $errorCode = (int) ($body['ErrorCode'] ?? 0);
                $message = (string) ($body['Message'] ?? '');
                $result = $body['Result'] ?? null;

                $isFailed = ($errorCode !== 0) || (stripos($message, 'failed') !== false);

                // If local PurchaseRequest exists, optionally sync status to COMPLETED when success
                if (!$isFailed && !empty($docEntry)) {
                    $pr = \App\Models\PurchaseRequest::where('sap_doc_entry', (int) $docEntry)->first();
                    if ($pr) {
                        $this->repository->updateStatus((int) $pr->id, 'COMPLETED', $userId);
                    }
                }

                return [
                    'success'    => !$isFailed,
                    'error_code' => $errorCode,
                    'message'    => $message,
                    'result'     => $result,
                    'doc_entry'  => (string) $docEntry,
                ];
            }

            return [
                'success'    => false,
                'error_code' => $response->status(),
                'message'    => 'Failed to communicate with SAP B1: HTTP ' . $response->status(),
                'result'     => null,
                'doc_entry'  => (string) $docEntry,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to close PR in SAP: ' . $e->getMessage());

            return [
                'success'    => false,
                'error_code' => 500,
                'message'    => 'Connection to SAP failed: ' . $e->getMessage(),
                'result'     => null,
                'doc_entry'  => (string) $docEntry,
            ];
        }
    }

    /**
     * Cancel Purchase Request in SAP B1 (/api/CancelPR).
     *
     * @param array|string|int $payload
     * @param int|null $userId
     * @return array
     */
    public function cancelPR($payload, ?int $userId = null): array
    {
        $docEntry = is_array($payload)
            ? ($payload['DocEntry'] ?? $payload['doc_entry'] ?? $payload['id'] ?? '')
            : (string) $payload;

        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        $sapPayload = [
            'DocEntry' => (string) $docEntry,
        ];

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/CancelPR", $sapPayload);

            if ($response->successful()) {
                $body = $response->json();
                $errorCode = (int) ($body['ErrorCode'] ?? 0);
                $message = (string) ($body['Message'] ?? '');
                $result = $body['Result'] ?? null;

                $isFailed = ($errorCode !== 0) || (stripos($message, 'failed') !== false);

                // If local PurchaseRequest exists, sync status to CANCELLED when success
                if (!$isFailed && !empty($docEntry)) {
                    $pr = \App\Models\PurchaseRequest::where('sap_doc_entry', (int) $docEntry)->first();
                    if ($pr) {
                        $this->repository->updateStatus((int) $pr->id, 'CANCELLED', $userId);
                    }
                }

                return [
                    'success'    => !$isFailed,
                    'error_code' => $errorCode,
                    'message'    => $message,
                    'result'     => $result,
                    'doc_entry'  => (string) $docEntry,
                ];
            }

            return [
                'success'    => false,
                'error_code' => $response->status(),
                'message'    => 'Failed to communicate with SAP B1: HTTP ' . $response->status(),
                'result'     => null,
                'doc_entry'  => (string) $docEntry,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to cancel PR in SAP: ' . $e->getMessage());

            return [
                'success'    => false,
                'error_code' => 500,
                'message'    => 'Connection to SAP failed: ' . $e->getMessage(),
                'result'     => null,
                'doc_entry'  => (string) $docEntry,
            ];
        }
    }

    /**
     * Get detail of Purchase Request from SAP B1 API (/api/GetDetailPR).
     *
     * @param array|string|int $payload
     * @param int|null $userId
     * @return array
     */
    public function getDetailPR($payload, ?int $userId = null): array
    {
        $docEntry = is_array($payload)
            ? ($payload['CustomQuery'] ?? $payload['custom_query'] ?? $payload['CostumQuery'] ?? $payload['costum_query'] ?? $payload['DocEntry'] ?? $payload['doc_entry'] ?? $payload['id'] ?? '')
            : (string) $payload;

        $docEntry = trim((string) $docEntry);

        if ($docEntry === '') {
            return [
                'success'    => false,
                'error_code' => 1,
                'message'    => 'Parameter DocEntry (CustomQuery) wajib diisi.',
                'data'       => null,
                'doc_entry'  => '',
            ];
        }

        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        $sapPayload = [
            'CustomQuery' => (string) $docEntry,
        ];

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/GetDetailPR", $sapPayload);

            if ($response->successful()) {
                $body = $response->json();
                $errorCode = (int) ($body['ErrorCode'] ?? 0);
                $message = (string) ($body['Message'] ?? '');
                $result = $body['Result'] ?? null;

                if ($errorCode !== 0) {
                    return [
                        'success'    => false,
                        'error_code' => $errorCode,
                        'message'    => $message ?: 'Error from SAP GetDetailPR',
                        'data'       => null,
                        'doc_entry'  => (string) $docEntry,
                    ];
                }

                $table1 = is_array($result) && isset($result['Table1']) && is_array($result['Table1'])
                    ? $result['Table1']
                    : [];
                $table2 = is_array($result) && isset($result['Table2']) && is_array($result['Table2'])
                    ? $result['Table2']
                    : [];

                // Filter dummy / empty header records
                $filteredTable1 = array_values(array_filter($table1, function ($item) {
                    return is_array($item) && !$this->isDummyDetailHeader($item);
                }));

                // Filter dummy / empty item records
                $filteredTable2 = array_values(array_filter($table2, function ($item) {
                    return is_array($item) && !$this->isDummyDetailItem($item);
                }));

                $isEmpty = empty($filteredTable1);

                return [
                    'success'    => !$isEmpty,
                    'error_code' => 0,
                    'message'    => $isEmpty ? 'Data not found' : $message,
                    'data'       => [
                        'Table1' => $filteredTable1,
                        'Table2' => $filteredTable2,
                        'header' => $filteredTable1[0] ?? null,
                        'items'  => $filteredTable2,
                    ],
                    'doc_entry'  => (string) $docEntry,
                ];
            }

            return [
                'success'    => false,
                'error_code' => $response->status(),
                'message'    => 'Failed to communicate with SAP B1: HTTP ' . $response->status(),
                'data'       => null,
                'doc_entry'  => (string) $docEntry,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch PR detail from SAP: ' . $e->getMessage());

            return [
                'success'    => false,
                'error_code' => 500,
                'message'    => 'Connection to SAP failed: ' . $e->getMessage(),
                'data'       => null,
                'doc_entry'  => (string) $docEntry,
            ];
        }
    }

    /**
     * Check if a PR detail header returned from SAP is an empty/dummy placeholder.
     *
     * @param array $item
     * @return bool
     */
    public function isDummyDetailHeader(array $item): bool
    {
        $docEntry = trim((string) ($item['DocEntry'] ?? '0'));
        $docNum = trim((string) ($item['DocNum'] ?? '0'));

        $isZeroOrEmpty = function ($val) {
            return $val === '' || $val === '0' || $val === 0;
        };

        return $isZeroOrEmpty($docEntry) && $isZeroOrEmpty($docNum);
    }

    /**
     * Check if a PR detail line item returned from SAP is an empty/dummy placeholder.
     *
     * @param array $item
     * @return bool
     */
    public function isDummyDetailItem(array $item): bool
    {
        $docEntry = trim((string) ($item['DocEntry'] ?? '0'));
        $itemCode = trim((string) ($item['ItemCode'] ?? ''));

        $isZeroOrEmpty = function ($val) {
            return $val === '' || $val === '0' || $val === 0;
        };

        return $isZeroOrEmpty($docEntry) || $itemCode === '';
    }

    /**
     * Build SAP payload for addpr API endpoint.
     */
    public function buildSapPayload(array $payload, ?int $userId = null): array
    {
        $user = auth()->user();
        $series = (string) ($payload['Series'] ?? $payload['series'] ?? $payload['pr_number'] ?? '4876');
        $reqType = (string) ($payload['ReqType'] ?? $payload['req_type'] ?? '12'); // hardcode 12
        $requester = (string) ($payload['Requester'] ?? $payload['requester'] ?? $payload['requester_code'] ?? ($user?->username ?: ($user?->employee_code ?: 'IND01')));
        $requesterName = (string) ($payload['RequesterName'] ?? $payload['requester_name'] ?? ($user?->name ?: 'Purchasing Balaraja'));
        $department = (string) ($payload['Department'] ?? $payload['department'] ?? '9');

        $docDateInput = $payload['DocDate'] ?? $payload['doc_date'] ?? date('Y-m-d');
        $docDate = date('Y-m-d', strtotime($docDateInput));

        $reqDateInput = $payload['ReqDate'] ?? $payload['req_date'] ?? $payload['required_date'] ?? $docDate;
        $reqDate = date('Y-m-d', strtotime($reqDateInput));

        $docDueDateInput = $payload['DocDueDate'] ?? $payload['doc_due_date'] ?? $payload['required_date'] ?? $docDate;
        $docDueDate = date('Y-m-d', strtotime($docDueDateInput));

        $comments = (string) ($payload['Comments'] ?? $payload['comments'] ?? $payload['remarks'] ?? '');
        $userIdVal = (string) ($payload['UserId'] ?? $payload['user_id'] ?? ($userId ? (string)$userId : ($user?->id ? (string)$user->id : '19')));
        $addonId = (isset($payload['AddonId']) && $payload['AddonId'] !== '')
            ? (string)$payload['AddonId']
            : ((isset($payload['AddOnId']) && $payload['AddOnId'] !== '')
                ? (string)$payload['AddOnId']
                : ((isset($payload['addon_id']) && $payload['addon_id'] !== '')
                    ? (string)$payload['addon_id']
                    : '2')); // default 2

        $details = $payload['Lines'] ?? $payload['lines'] ?? $payload['details'] ?? [];
        $lines = [];

        foreach ($details as $item) {
            $itemCode = (string) ($item['ItemCode'] ?? $item['item_code'] ?? '');

            // Check BOM lookup if itemCode is empty
            if ($itemCode === '') {
                $bomId = $item['bom_id'] ?? $item['production_bom_id'] ?? $item['Bomid'] ?? null;
                if ($bomId) {
                    $bom = ProductionBom::find($bomId);
                    if ($bom && !empty($bom->code)) {
                        $itemCode = $bom->code;
                    }
                }
            }

            if ($itemCode === '') {
                $itemCode = 'JS000009'; // Fallback code
            }

            $pqtReqDateInput = $item['PQTReqDate'] ?? $item['pqt_req_date'] ?? $item['required_date'] ?? $docDueDate;
            $pqtReqDate = date('Y-m-d', strtotime($pqtReqDateInput));

            $quantity = floatval($item['Quantity'] ?? $item['quantity'] ?? 1.0);
            $uomEntry = (isset($item['UomEntry']) && $item['UomEntry'] !== '')
                ? (string)$item['UomEntry']
                : ((isset($item['uom_entry']) && $item['uom_entry'] !== '') ? (string)$item['uom_entry'] : '-1');

            $uomCode = (isset($item['UomCode']) && $item['UomCode'] !== '')
                ? (string)$item['UomCode']
                : ((isset($item['uom_code']) && $item['uom_code'] !== '') ? (string)$item['uom_code'] : '-1');

            $whsCode = (isset($item['WhsCode']) && $item['WhsCode'] !== '')
                ? (string)$item['WhsCode']
                : ((isset($item['whs_code']) && $item['whs_code'] !== '')
                    ? (string)$item['whs_code']
                    : ((isset($item['warehouse_code']) && $item['warehouse_code'] !== '') ? (string)$item['warehouse_code'] : '01'));

            $unitMsr = (isset($item['UnitMsr']) && $item['UnitMsr'] !== '')
                ? (string)$item['UnitMsr']
                : ((isset($item['unit_msr']) && $item['unit_msr'] !== '')
                    ? (string)$item['unit_msr']
                    : ((isset($item['uom']) && $item['uom'] !== '')
                        ? (string)$item['uom']
                        : (\App\Models\Item::where('item_code', $itemCode)->value('sal_unit_msr') ?: 'Pcs')));

            $freeTxt = (string) ($item['FreeTxt'] ?? $item['free_txt'] ?? $item['remarks'] ?? '');

            $ocrCode = (string) ($item['OcrCode'] ?? $item['ocr_code'] ?? $payload['cost_center'] ?? 'BLR');
            $ocrCode2 = (string) ($item['OcrCode2'] ?? $item['ocr_code2'] ?? $item['ocr_code_2'] ?? 'GRM');
            $ocrCode3 = (string) ($item['OcrCode3'] ?? $item['ocr_code3'] ?? $item['ocr_code_3'] ?? 'PCG');

            $lines[] = [
                'ItemCode' => $itemCode,
                'PQTReqDate' => $pqtReqDate,
                'Quantity' => $quantity,
                'UomEntry' => $uomEntry,
                'UomCode' => $uomCode,
                'WhsCode' => $whsCode,
                'UnitMsr' => $unitMsr,
                'FreeTxt' => $freeTxt,
                'OcrCode' => $ocrCode,
                'OcrCode2' => $ocrCode2,
                'OcrCode3' => $ocrCode3,
            ];
        }

        return [
            'Series' => $series,
            'ReqType' => $reqType,
            'ReqDate' => $reqDate,
            'Requester' => $requester,
            'RequesterName' => $requesterName,
            'Department' => $department,
            'DocDate' => $docDate,
            'DocDueDate' => $docDueDate,
            'Comments' => $comments,
            'UserId' => $userIdVal,
            'AddonId' => $addonId,
            'Lines' => $lines,
        ];
    }

    public function create(array $payload, ?int $userId = null): PurchaseRequest
    {
        $sapPayload = $this->buildSapPayload($payload, $userId);

        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');
        $response = Http::timeout(30)->post("{$sapUrl}/api/addpr", $sapPayload);

        if (!$response->successful()) {
            throw new SapException(
                'Gagal menghubungi API SAP addpr. HTTP Status: ' . $response->status(),
                ['sap_payload' => $sapPayload, 'http_status' => $response->status()],
                400
            );
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            $msg = $body['Message'] ?? 'Failed - [AddPurchaseRequest] Error from SAP';
            throw new SapException($msg, $body, 400);
        }

        // Set SAP returned values if present
        if (isset($body['Result'])) {
            if (is_numeric($body['Result'])) {
                $payload['sap_doc_entry'] = (int)$body['Result'];
            } elseif (is_array($body['Result'])) {
                $payload['sap_doc_entry'] = $body['Result']['DocEntry'] ?? null;
                $payload['sap_doc_num'] = $body['Result']['DocNum'] ?? null;
            }
        }

        // Regex fallback from Message if Result was empty
        if (empty($payload['sap_doc_entry']) && !empty($body['Message'])) {
            if (preg_match('/DocEntr(?:y|ies):\s*([0-9]+)/i', $body['Message'], $mEntry)) {
                $payload['sap_doc_entry'] = (int)$mEntry[1];
            }
            if (preg_match('/DocNum(?:s)?:\s*([A-Za-z0-9_-]+)/i', $body['Message'], $mNum)) {
                $payload['sap_doc_num'] = (string)$mNum[1];
            }
        }

        if (empty($payload['pr_number'])) {
            $payload['pr_number'] = $payload['sap_doc_num'] ?? $payload['series'] ?? $sapPayload['Series'] ?? ('PR-' . date('YmdHis'));
        }
        if (empty($payload['department'])) {
            $payload['department'] = $sapPayload['Department'];
        }
        if (empty($payload['cost_center'])) {
            $payload['cost_center'] = $sapPayload['Lines'][0]['OcrCode'] ?? 'BLR';
        }
        if (empty($payload['doc_date'])) {
            $payload['doc_date'] = $sapPayload['DocDate'];
        }
        if (empty($payload['req_date'])) {
            $payload['req_date'] = $sapPayload['ReqDate'];
        }
        if (empty($payload['required_date'])) {
            $payload['required_date'] = $sapPayload['ReqDate'];
        }
        if (empty($payload['doc_due_date'])) {
            $payload['doc_due_date'] = $sapPayload['DocDueDate'];
        }
        if (empty($payload['status'])) {
            $payload['status'] = 'SUBMITTED';
        }
        $payload['series'] = $sapPayload['Series'];
        $payload['req_type'] = $sapPayload['ReqType'];
        $payload['req_date'] = $sapPayload['ReqDate'];
        $payload['requester'] = $sapPayload['Requester'];
        $payload['requester_name'] = $sapPayload['RequesterName'];
        $payload['comments'] = $sapPayload['Comments'];
        $payload['user_id'] = $sapPayload['UserId'];
        $payload['addon_id'] = $sapPayload['AddonId'];

        $details = $payload['details'] ?? $payload['lines'] ?? $payload['Lines'] ?? [];
        unset($payload['details'], $payload['lines'], $payload['Lines']);

        $normalizedDetails = [];
        foreach ($details as $idx => $item) {
            $sapLine = $sapPayload['Lines'][$idx] ?? [];
            $itemCode = $sapLine['ItemCode'] ?? ($item['item_code'] ?? $item['ItemCode'] ?? null);
            $qty = floatval($sapLine['Quantity'] ?? ($item['quantity'] ?? $item['Quantity'] ?? 1));
            $unitPrice = floatval($item['unit_price'] ?? $item['UnitPrice'] ?? 0);

            $normalizedDetails[] = [
                'master_budget_id' => $item['master_budget_id'] ?? null,
                'bom_id' => $item['bom_id'] ?? $item['production_bom_id'] ?? $item['Bomid'] ?? null,
                'item_code' => $itemCode,
                'item_description' => $item['item_description'] ?? $item['ItemDescription'] ?? $itemCode,
                'pqt_req_date' => $sapLine['PQTReqDate'] ?? null,
                'quantity' => $qty,
                'uom' => $sapLine['UnitMsr'] ?? ($item['uom'] ?? null),
                'uom_entry' => $sapLine['UomEntry'] ?? null,
                'uom_code' => $sapLine['UomCode'] ?? null,
                'whs_code' => $sapLine['WhsCode'] ?? null,
                'unit_msr' => $sapLine['UnitMsr'] ?? null,
                'unit_price' => $unitPrice,
                'free_txt' => $sapLine['FreeTxt'] ?? null,
                'ocr_code' => $sapLine['OcrCode'] ?? null,
                'ocr_code2' => $sapLine['OcrCode2'] ?? null,
                'ocr_code3' => $sapLine['OcrCode3'] ?? null,
                'remarks' => $item['remarks'] ?? null,
            ];
        }

        if ($userId) {
            $payload['created_by'] = $userId;
            $payload['updated_by'] = $userId;
            $payload['requester_id'] = $payload['requester_id'] ?? $userId;
        }

        return $this->repository->create($payload, $normalizedDetails);
    }

    public function update(int $id, array $payload, ?int $userId = null): ?PurchaseRequest
    {
        $details = $payload['details'] ?? null;
        unset($payload['details']);

        if ($userId) {
            $payload['updated_by'] = $userId;
        }

        return $this->repository->update($id, $payload, $details);
    }

    public function updateStatus(int $id, string $status, ?int $userId = null): ?PurchaseRequest
    {
        return $this->repository->updateStatus($id, $status, $userId);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
