<?php

namespace App\Modules\PurchasingRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PurchasingRequest\Requests\SavePurchaseRequestRequest;
use App\Modules\PurchasingRequest\Services\PurchaseRequestService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    use ApiResponseFormatter;

    protected PurchaseRequestService $service;

    public function __construct(PurchaseRequestService $service)
    {
        $this->service = $service;
    }

    public function getSeries(Request $request): JsonResponse
    {
        $customQuery = $request->query('CustomQuery') ?? $request->query('custom_query') ?? date('Ymd');
        $series = $this->service->getSeries($customQuery);

        return $this->successResponse($series, 'Daftar series Purchasing Request berhasil diambil.');
    }

    public function getItems(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        $items = $this->service->getItems($filters);

        return $this->successResponse($items, 'Daftar item untuk Purchasing Request berhasil diambil.');
    }

    public function getDepartments(Request $request): JsonResponse
    {
        $search = $request->query('search') ?? $request->query('q') ?? $request->input('search');
        $departments = $this->service->getDepartments($search);

        if ($request->is('api/GetDepartment') || $request->is('GetDepartment') || $request->query('format') === 'sap' || $request->input('format') === 'sap') {
            return response()->json([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => $departments,
            ]);
        }

        return $this->successResponse($departments, 'Daftar department berhasil diambil.');
    }

    /**
     * Get draft Purchase Requests from SAP B1 API (/api/GetDraftPR).
     */
    public function getDraftPR(Request $request): JsonResponse
    {
        $payload = $request->all();
        $userId = $request->user()?->id;

        $result = $this->service->getDraftPR($payload, $userId);
        $drafts = $result['data'] ?? [];

        $isSapFormat = $request->is('api/GetDraftPR')
            || $request->is('GetDraftPR')
            || $request->is('*GetDraftPR*')
            || $request->query('format') === 'sap'
            || $request->input('format') === 'sap';

        if ($isSapFormat) {
            if (empty($drafts)) {
                return response()->json([
                    'ErrorCode' => 0,
                    'Message'   => 'Data not found',
                    'Result'    => [],
                ], 200);
            }

            return response()->json([
                'ErrorCode' => 0,
                'Message'   => $result['message'] ?? '',
                'Result'    => $drafts,
            ], 200);
        }

        if (empty($drafts)) {
            return response()->json([
                'success' => true,
                'message' => 'Data not found.',
                'data'    => [],
            ], 200);
        }

        return $this->successResponse($drafts, 'Data draft Purchase Request berhasil diambil.');
    }

    /**
     * Get list of generated Purchase Requests from SAP B1 API (/api/GetListPRGenerated).
     */
    public function getListPRGenerated(Request $request): JsonResponse
    {
        $payload = $request->all();
        $userId = $request->user()?->id;

        $result = $this->service->getListPRGenerated($payload, $userId);
        $items = $result['data'] ?? [];

        $isSapFormat = $request->is('api/GetListPRGenerated')
            || $request->is('GetListPRGenerated')
            || $request->is('*GetListPRGenerated*')
            || $request->query('format') === 'sap'
            || $request->input('format') === 'sap';

        if ($isSapFormat) {
            if (empty($items)) {
                return response()->json([
                    'ErrorCode' => 0,
                    'Message'   => 'Data not found',
                    'Result'    => [],
                ], 200);
            }

            return response()->json([
                'ErrorCode' => 0,
                'Message'   => $result['message'] ?? '',
                'Result'    => $items,
            ], 200);
        }

        if (empty($items)) {
            return response()->json([
                'success' => true,
                'message' => 'Data not found.',
                'data'    => [],
            ], 200);
        }

        return $this->successResponse($items, 'Daftar Purchase Request yang telah di-generate berhasil diambil.');
    }

    /**
     * Close Purchase Request in SAP B1 (/api/ClosePR).
     */
    public function closePR(Request $request): JsonResponse
    {
        $payload = $request->all();
        $userId = $request->user()?->id;

        $docEntry = $payload['DocEntry'] ?? $payload['doc_entry'] ?? $payload['id'] ?? null;
        $isSapFormat = $request->is('api/ClosePR')
            || $request->is('ClosePR')
            || $request->is('*ClosePR*')
            || $request->query('format') === 'sap'
            || $request->input('format') === 'sap';

        if (empty($docEntry)) {
            if ($isSapFormat) {
                return response()->json([
                    'ErrorCode' => 1,
                    'Message'   => 'Failed - [ClosePurchaseRequest] Parameter DocEntry is required',
                    'Result'    => null,
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => 'Parameter DocEntry wajib diisi.',
                'data'    => null,
            ], 400);
        }

        $res = $this->service->closePR($payload, $userId);

        if ($isSapFormat) {
            return response()->json([
                'ErrorCode' => $res['error_code'],
                'Message'   => $res['message'],
                'Result'    => $res['result'],
            ], 200);
        }

        if (!$res['success']) {
            return response()->json([
                'success' => false,
                'message' => $res['message'],
                'data'    => [
                    'doc_entry'  => $res['doc_entry'],
                    'error_code' => $res['error_code'],
                ],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $res['message'] ?: 'Purchase Request berhasil di-close di SAP.',
            'data'    => [
                'doc_entry' => $res['doc_entry'],
                'result'    => $res['result'],
            ],
        ], 200);
    }

    /**
     * Cancel Purchase Request in SAP B1 (/api/CancelPR).
     */
    public function cancelPR(Request $request): JsonResponse
    {
        $payload = $request->all();
        $userId = $request->user()?->id;

        $docEntry = $payload['DocEntry'] ?? $payload['doc_entry'] ?? $payload['id'] ?? null;
        $isSapFormat = $request->is('api/CancelPR')
            || $request->is('CancelPR')
            || $request->is('*CancelPR*')
            || $request->query('format') === 'sap'
            || $request->input('format') === 'sap';

        if (empty($docEntry)) {
            if ($isSapFormat) {
                return response()->json([
                    'ErrorCode' => 1,
                    'Message'   => 'Failed - [CancelPurchaseRequest] Parameter DocEntry is required',
                    'Result'    => null,
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => 'Parameter DocEntry wajib diisi.',
                'data'    => null,
            ], 400);
        }

        $res = $this->service->cancelPR($payload, $userId);

        if ($isSapFormat) {
            return response()->json([
                'ErrorCode' => $res['error_code'],
                'Message'   => $res['message'],
                'Result'    => $res['result'],
            ], 200);
        }

        if (!$res['success']) {
            return response()->json([
                'success' => false,
                'message' => $res['message'],
                'data'    => [
                    'doc_entry'  => $res['doc_entry'],
                    'error_code' => $res['error_code'],
                ],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $res['message'] ?: 'Purchase Request berhasil di-cancel di SAP.',
            'data'    => [
                'doc_entry' => $res['doc_entry'],
                'result'    => $res['result'],
            ],
        ], 200);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'department',
            'cost_center',
            'status',
            'start_date',
            'end_date',
            'search',
            'paginate'
        ]);

        $perPage = (int)$request->query('per_page', 15);
        $data = $this->service->getList($filters, $perPage);

        return $this->successResponse($data, 'Data Purchasing Request berhasil diambil.');
    }

    public function store(SavePurchaseRequestRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id ?? (is_numeric($request->input('UserId')) ? (int)$request->input('UserId') : (is_numeric($request->input('user_id')) ? (int)$request->input('user_id') : null));
            $pr = $this->service->create($request->all(), $userId);

            return $this->successResponse($pr, 'Purchasing Request berhasil dibuat.', 201);
        } catch (\App\Exceptions\SapException $e) {
            return $this->errorResponse($e->getMessage(), $e->getSapError(), $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }

    public function show(int $id): JsonResponse
    {
        $pr = $this->service->getDetail($id);
        if (!$pr) {
            return $this->errorResponse('Purchasing Request tidak ditemukan.', [], 404);
        }

        return $this->successResponse($pr, 'Detail Purchasing Request berhasil diambil.');
    }

    public function update(SavePurchaseRequestRequest $request, int $id): JsonResponse
    {
        $userId = $request->user()?->id;
        $pr = $this->service->update($id, $request->validated(), $userId);

        if (!$pr) {
            return $this->errorResponse('Purchasing Request tidak ditemukan.', [], 404);
        }

        return $this->successResponse($pr, 'Purchasing Request berhasil diperbarui.');
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:DRAFT,SUBMITTED,APPROVED,REJECTED,CANCELLED,COMPLETED'
        ]);

        $userId = $request->user()?->id;
        $pr = $this->service->updateStatus($id, $request->input('status'), $userId);

        if (!$pr) {
            return $this->errorResponse('Purchasing Request tidak ditemukan.', [], 404);
        }

        return $this->successResponse($pr, "Status Purchasing Request berhasil diubah menjadi {$request->input('status')}.");
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->service->delete($id);
        if (!$deleted) {
            return $this->errorResponse('Purchasing Request tidak ditemukan.', [], 404);
        }

        return $this->successResponse(null, 'Purchasing Request berhasil dihapus.');
    }
}
