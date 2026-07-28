<?php

namespace App\Modules\Warehouse\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Warehouse\Services\InventoryTransferService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryTransferController extends Controller
{
    use ApiResponseFormatter;

    protected InventoryTransferService $inventoryTransferService;

    public function __construct(InventoryTransferService $inventoryTransferService)
    {
        $this->inventoryTransferService = $inventoryTransferService;
    }

    /**
     * Search for Bin Locations from SAP.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchBin(Request $request): JsonResponse
    {
        $request->validate([
            'CustomQuery' => 'nullable|string',
            'WhsCode' => 'required|string',
        ]);

        try {
            $result = $this->inventoryTransferService->searchQtyBin($request->all());
            
            if (isset($result['ErrorCode']) && $result['ErrorCode'] !== 0) {
                return $this->errorResponse($result['Message'] ?? 'Gagal mencari data bin', $result, 400);
            }

            return $this->successResponse($result['Result'] ?? [], $result['Message'] ?? 'Pencarian data bin berhasil.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Perform Inventory Transfer in SAP.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'DocDate' => 'required|date_format:Y-m-d',
            'DocDueDate' => 'required|date_format:Y-m-d',
            'Filler' => 'required|string',
            'ToWhsCode' => 'required|string',
            'Lines' => 'required|array',
            'Lines.*.ItemCode' => 'required|string',
            'Lines.*.Quantity' => 'required|numeric|min:0.001',
            'Lines.*.Filler' => 'required|string',
            'Lines.*.ToWhsCode' => 'required|string',
        ]);

        try {
            $result = $this->inventoryTransferService->addInventoryTransfer($request->all());

            if (isset($result['ErrorCode']) && $result['ErrorCode'] !== 0) {
                return $this->errorResponse($result['Message'] ?? 'Gagal memproses Inventory Transfer', $result, 400);
            }

            return $this->successResponse($result['Result'] ?? null, $result['Message'] ?? 'Proses Inventory Transfer berhasil.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }
}
