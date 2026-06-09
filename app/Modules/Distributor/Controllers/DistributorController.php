<?php

namespace App\Modules\Distributor\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Distributor\Services\DistributorService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistributorController extends Controller
{
    use ApiResponseFormatter;

    protected DistributorService $distributorService;

    /**
     * DistributorController constructor.
     *
     * @param  DistributorService  $distributorService
     */
    public function __construct(DistributorService $distributorService)
    {
        $this->distributorService = $distributorService;
    }

    /**
     * Display a listing of the distributors.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        $distributors = $this->distributorService->getAll($filters);

        return $this->successResponse($distributors, 'Daftar distributor berhasil diambil.');
    }

    /**
     * Display the specified distributor.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $distributor = $this->distributorService->getById($id);

        if (!$distributor) {
            abort(404, 'Distributor tidak ditemukan.');
        }

        return $this->successResponse($distributor, 'Detail distributor berhasil diambil.');
    }

    /**
     * Synchronize distributors from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $syncedData = $this->distributorService->syncFromSap($userId);

        return $this->successResponse($syncedData, 'Data distributor berhasil disinkronisasi dari SAP.');
    }
    /**
     * Get distributor addresses from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getAddresses(Request $request): JsonResponse
    {
        $cardCode = $request->query('card_code') ?? $request->query('CustomQuery');

        if (!$cardCode) {
            return $this->errorResponse('Parameter card_code atau CustomQuery wajib diisi.', 422);
        }

        try {
            $addresses = $this->distributorService->getAddressesFromSap($cardCode);
            return $this->successResponse($addresses, 'Daftar alamat berhasil diambil dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get OCR codes from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getOcrCodes(Request $request): JsonResponse
    {
        $type = $request->query('type') ?? $request->query('custom_query') ?? $request->query('CustomQuery');

        if (!$type || !in_array($type, ['1', '2', '3'])) {
            return $this->errorResponse('Parameter type/custom_query wajib diisi dengan nilai 1 (Cabang), 2 (Bisnis Unit), atau 3 (Dept).', 422);
        }

        try {
            $ocrCodes = $this->distributorService->getOcrCodesFromSap($type);
            return $this->successResponse($ocrCodes, 'Daftar OcrCode berhasil diambil dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
