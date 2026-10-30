<?php

namespace App\Modules\SalesDistributor\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SalesDistributor\Requests\StoreSalesDistributorRequest;
use App\Modules\SalesDistributor\Requests\UpdateSalesDistributorRequest;
use App\Modules\SalesDistributor\Services\SalesDistributorService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesDistributorController extends Controller
{
    use ApiResponseFormatter;

    protected SalesDistributorService $service;

    /**
     * SalesDistributorController constructor.
     *
     * @param  SalesDistributorService  $service
     */
    public function __construct(SalesDistributorService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of sales-distributor mappings.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['code_customer', 'status', 'search', 'per_page']);
        $mappings = $this->service->getAll($filters);

        return $this->successResponse($mappings, 'Daftar pemetaan sales dan distributor berhasil diambil.');
    }

    /**
     * Store a newly created mapping.
     *
     * @param  StoreSalesDistributorRequest  $request
     * @return JsonResponse
     */
    public function store(StoreSalesDistributorRequest $request): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            // Check for duplicate entry first to avoid SQL constraints error
            $exists = \App\Models\SalesDistributorMapping::where('code_customer', $request->input('code_customer'))
                ->where('slp_code', $request->input('slp_code'))
                ->exists();

            if ($exists) {
                return $this->errorResponse('Pemetaan untuk customer dan sales ini sudah terdaftar.', 422);
            }

            $mapping = $this->service->create([
                'code_customer' => $request->input('code_customer'),
                'slp_code' => $request->input('slp_code'),
                'status' => $request->input('status', 1),
            ], $userId);

            return $this->successResponse($mapping, 'Pemetaan sales dan distributor berhasil dibuat.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified mapping.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $mapping = $this->service->getById($id);

        if (!$mapping) {
            return $this->errorResponse('Pemetaan tidak ditemukan.', 404);
        }

        return $this->successResponse($mapping, 'Detail pemetaan sales dan distributor berhasil diambil.');
    }

    /**
     * Update the specified mapping in storage.
     *
     * @param  UpdateSalesDistributorRequest  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(UpdateSalesDistributorRequest $request, int $id): JsonResponse
    {
        $mapping = $this->service->getById($id);

        if (!$mapping) {
            return $this->errorResponse('Pemetaan tidak ditemukan.', 404);
        }

        $userId = $request->user()?->id;

        try {
            // If changing code_customer or slp_code, check unique constraint
            $newCodeCust = $request->input('code_customer', $mapping->code_customer);
            $newSlpCode = $request->input('slp_code', $mapping->slp_code);

            if ($newCodeCust !== $mapping->code_customer || $newSlpCode !== $mapping->slp_code) {
                $exists = \App\Models\SalesDistributorMapping::where('code_customer', $newCodeCust)
                    ->where('slp_code', $newSlpCode)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    return $this->errorResponse('Pemetaan untuk customer dan sales ini sudah terdaftar.', 422);
                }
            }

            $data = $request->only(['code_customer', 'slp_code', 'status']);
            $updated = $this->service->update($mapping, $data, $userId);

            return $this->successResponse($updated, 'Pemetaan sales dan distributor berhasil diperbarui.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified mapping from storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $mapping = $this->service->getById($id);

        if (!$mapping) {
            return $this->errorResponse('Pemetaan tidak ditemukan.', 404);
        }

        $userId = $request->user()?->id;

        try {
            $this->service->delete($mapping, $userId);
            return $this->successResponse(null, 'Pemetaan sales dan distributor berhasil dihapus.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
