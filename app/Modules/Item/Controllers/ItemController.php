<?php

namespace App\Modules\Item\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Item\Services\ItemService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    use ApiResponseFormatter;

    protected ItemService $itemService;

    /**
     * ItemController constructor.
     *
     * @param  ItemService  $itemService
     */
    public function __construct(ItemService $itemService)
    {
        $this->itemService = $itemService;
    }

    /**
     * Display a listing of items.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'code_customer']);
        $items = $this->itemService->getAll($filters);

        return $this->successResponse($items, 'Daftar item berhasil diambil.');
    }

    /**
     * Synchronize items from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $syncedData = $this->itemService->syncFromSap($userId);
            return $this->successResponse($syncedData, 'Data item berhasil disinkronisasi dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
