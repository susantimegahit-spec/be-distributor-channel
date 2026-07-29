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
     * Search for Qty Bin Locations from SAP.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchQtyBin(Request $request): JsonResponse
    {
        $request->validate([
            'CustomQuery' => 'nullable|string',
            'WhsCode' => 'required|string',
        ]);

        try {
            $result = $this->inventoryTransferService->searchQtyBin($request->all());
            
            if (isset($result['ErrorCode']) && $result['ErrorCode'] !== 0) {
                return $this->errorResponse($result['Message'] ?? 'Gagal mencari data bin qty', $result, 400);
            }

            return $this->successResponse($result['Result'] ?? [], $result['Message'] ?? 'Pencarian data bin qty berhasil.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Search for Master Bin Locations from SAP.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchBin(Request $request): JsonResponse
    {
        $request->validate([
            'CustomQuery' => 'required|string',
        ]);

        try {
            $result = $this->inventoryTransferService->searchBin($request->all());
            
            if (isset($result['ErrorCode']) && $result['ErrorCode'] !== 0) {
                return $this->errorResponse($result['Message'] ?? 'Gagal mencari data master bin', $result, 400);
            }

            return $this->successResponse($result['Result'] ?? [], $result['Message'] ?? 'Pencarian data master bin berhasil.');
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
            $userId = $request->user()?->id;
            $result = $this->inventoryTransferService->addInventoryTransfer($request->all(), $userId);

            if (isset($result['ErrorCode']) && $result['ErrorCode'] !== 0) {
                return $this->errorResponse($result['Message'] ?? 'Gagal memproses Inventory Transfer', $result, 400);
            }

            return $this->successResponse($result['Result'] ?? null, $result['Message'] ?? 'Proses Inventory Transfer berhasil.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Get list of Inventory Transfers from SAP.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listIT(Request $request): JsonResponse
    {
        $request->validate([
            'From' => 'nullable|string',
            'To' => 'nullable|string',
            'WhsCode' => 'nullable|string',
            'ToWhsCode' => 'nullable|string',
        ]);

        try {
            $filters = array_filter(
                $request->only(['From', 'To', 'WhsCode', 'ToWhsCode']),
                fn($v) => !is_null($v) && $v !== ''
            );

            $result = $this->inventoryTransferService->listIT($filters);

            if (isset($result['ErrorCode']) && $result['ErrorCode'] !== 0) {
                return $this->errorResponse($result['Message'] ?? 'Gagal mengambil daftar Inventory Transfer', $result, 400);
            }

            return $this->successResponse($result['Result'] ?? [], $result['Message'] ?? 'Mengambil daftar Inventory Transfer berhasil.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Get Inventory Transfer by DocEntry from SAP.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getITbyId(Request $request): JsonResponse
    {
        $request->validate([
            'CustomQuery' => 'required|string',
        ]);

        try {
            $result = $this->inventoryTransferService->getITbyId($request->input('CustomQuery'));

            if (isset($result['ErrorCode']) && $result['ErrorCode'] !== 0) {
                return $this->errorResponse($result['Message'] ?? 'Gagal mengambil detail Inventory Transfer', $result, 400);
            }

            // Format the SAP response so Table1 becomes the header data and Table2 becomes 'lines'
            $sapResult = $result['Result'] ?? [];
            $table1 = $sapResult['Table1'] ?? [];
            $table2 = $sapResult['Table2'] ?? [];

            $formatted = null;
            if (!empty($table1) && is_array($table1)) {
                $formatted = $table1[0];
                $formatted['lines'] = $table2;
            }

            return $this->successResponse($formatted, $result['Message'] ?? 'Mengambil detail Inventory Transfer berhasil.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }
}
