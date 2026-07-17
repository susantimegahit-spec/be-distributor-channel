<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Services\ProductionService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    use ApiResponseFormatter;

    protected ProductionService $productionService;

    /**
     * ProductionController constructor.
     *
     * @param  ProductionService  $productionService
     */
    public function __construct(ProductionService $productionService)
    {
        $this->productionService = $productionService;
    }

    /**
     * Display a listing of production resources.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function indexResources(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        $resources = $this->productionService->getAllResources($filters);

        return $this->successResponse($resources, 'Daftar resource produksi berhasil diambil.');
    }

    /**
     * Synchronize production resources from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function syncResources(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $syncedData = $this->productionService->syncResourcesFromSap($userId);
            return $this->successResponse($syncedData, 'Data resource produksi berhasil disinkronisasi dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display a listing of production items.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function indexItems(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        $items = $this->productionService->getAllItems($filters);

        return $this->successResponse($items, 'Daftar item produksi berhasil diambil.');
    }

    /**
     * Synchronize production items from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function syncItems(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $syncedData = $this->productionService->syncItemsFromSap($userId);
            return $this->successResponse($syncedData, 'Data item produksi berhasil disinkronisasi dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
