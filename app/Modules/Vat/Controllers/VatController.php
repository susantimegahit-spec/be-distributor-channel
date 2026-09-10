<?php

namespace App\Modules\Vat\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Vat\Services\VatService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VatController extends Controller
{
    use ApiResponseFormatter;

    protected VatService $vatService;

    /**
     * VatController constructor.
     *
     * @param  VatService  $vatService
     */
    public function __construct(VatService $vatService)
    {
        $this->vatService = $vatService;
    }

    /**
     * Display a listing of vats.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        $vats = $this->vatService->getAll($filters);

        return $this->successResponse($vats, 'Daftar master pajak berhasil diambil.');
    }

    /**
     * Synchronize vats from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $syncedData = $this->vatService->syncFromSap($userId);
            return $this->successResponse($syncedData, 'Data master pajak berhasil disinkronisasi dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
