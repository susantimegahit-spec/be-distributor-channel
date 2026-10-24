<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ekspedisi\Services\SapEkspedisiService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SapEkspedisiController extends Controller
{
    use ApiResponseFormatter;

    protected SapEkspedisiService $sapService;

    public function __construct(SapEkspedisiService $sapService)
    {
        $this->sapService = $sapService;
    }

    /**
     * Get list of Expedition Names (Nama Ekspedisi) from SAP B1 API (/api/getNamaEkspedisi).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getNamaEkspedisi(Request $request): JsonResponse
    {
        try {
            $data = $this->sapService->getNamaEkspedisi($request->all());

            if (empty($data)) {
                return $this->successResponse([], 'Data not found.');
            }

            return $this->successResponse($data, 'Expedition list retrieved successfully from SAP.');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve expedition names from SAP: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve expedition names from SAP: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get list of Checker Names (Nama Checker) from SAP B1 API (/api/getNamaChecker).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getNamaChecker(Request $request): JsonResponse
    {
        try {
            $data = $this->sapService->getNamaChecker($request->all());

            if (empty($data)) {
                return $this->successResponse([], 'Data not found.');
            }

            return $this->successResponse($data, 'Checker list retrieved successfully from SAP.');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve checker list from SAP: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve checker list from SAP: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get list of Vehicles (Kendaraan) from SAP B1 API (/api/getKendaraan).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getKendaraan(Request $request): JsonResponse
    {
        try {
            $data = $this->sapService->getKendaraan($request->all());

            if (empty($data)) {
                return $this->successResponse([], 'Data not found.');
            }

            return $this->successResponse($data, 'Vehicle list retrieved successfully from SAP.');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve vehicle list from SAP: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve vehicle list from SAP: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get list of Drivers (Sopir) from SAP B1 API (/api/getSopir).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getSopir(Request $request): JsonResponse
    {
        try {
            $data = $this->sapService->getSopir($request->all());

            if (empty($data)) {
                return $this->successResponse([], 'Data not found.');
            }

            return $this->successResponse($data, 'Driver list retrieved successfully from SAP.');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve driver list from SAP: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve driver list from SAP: ' . $e->getMessage(), null, 500);
        }
    }
}
