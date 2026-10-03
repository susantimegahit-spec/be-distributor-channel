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
            $userId = $request->user()?->id;
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
