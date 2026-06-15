<?php

namespace App\Modules\Distributor\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Distributor\Services\DistributorService;
use App\Modules\Item\Services\ItemService;
use App\Modules\SalesEmployee\Services\SalesEmployeeService;
use App\Modules\Vat\Services\VatService;
use App\Modules\Warehouse\Services\WarehouseService;
use App\Modules\Discount\Services\DiscountService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SyncAllController extends Controller
{
    use ApiResponseFormatter;

    protected DistributorService $distributorService;
    protected ItemService $itemService;
    protected SalesEmployeeService $salesEmployeeService;
    protected VatService $vatService;
    protected WarehouseService $warehouseService;
    protected DiscountService $discountService;

    /**
     * SyncAllController constructor.
     */
    public function __construct(
        DistributorService $distributorService,
        ItemService $itemService,
        SalesEmployeeService $salesEmployeeService,
        VatService $vatService,
        WarehouseService $warehouseService,
        DiscountService $discountService
    ) {
        $this->distributorService = $distributorService;
        $this->itemService = $itemService;
        $this->salesEmployeeService = $salesEmployeeService;
        $this->vatService = $vatService;
        $this->warehouseService = $warehouseService;
        $this->discountService = $discountService;
    }

    /**
     * Synchronize all master data from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function syncAll(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $results = [];

        // 1. Sync Distributors
        try {
            $data = $this->distributorService->syncFromSap($userId);
            $results['distributors'] = [
                'success' => true,
                'count' => count($data),
                'message' => 'Data distributor berhasil disinkronisasi.',
            ];
        } catch (Throwable $e) {
            $results['distributors'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // 2. Sync OCR Codes
        try {
            $data = $this->distributorService->syncOcrCodesFromSap($userId);
            $results['ocr_codes'] = [
                'success' => true,
                'count' => count($data),
                'message' => 'Data OcrCode berhasil disinkronisasi.',
            ];
        } catch (Throwable $e) {
            $results['ocr_codes'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // 3. Sync Items/Products
        try {
            $data = $this->itemService->syncFromSap($userId);
            $results['items'] = [
                'success' => true,
                'count' => count($data),
                'message' => 'Data item berhasil disinkronisasi.',
            ];
        } catch (Throwable $e) {
            $results['items'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // 4. Sync Sales Employees
        try {
            $data = $this->salesEmployeeService->syncFromSap($userId);
            $results['sales_employees'] = [
                'success' => true,
                'count' => count($data),
                'message' => 'Data Sales Employee berhasil disinkronisasi.',
            ];
        } catch (Throwable $e) {
            $results['sales_employees'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // 5. Sync Vats
        try {
            $data = $this->vatService->syncFromSap($userId);
            $results['vats'] = [
                'success' => true,
                'count' => count($data),
                'message' => 'Data master pajak berhasil disinkronisasi.',
            ];
        } catch (Throwable $e) {
            $results['vats'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // 6. Sync Warehouses
        try {
            $data = $this->warehouseService->syncFromSap($userId);
            $results['warehouses'] = [
                'success' => true,
                'count' => count($data),
                'message' => 'Data master gudang berhasil disinkronisasi.',
            ];
        } catch (Throwable $e) {
            $results['warehouses'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // 7. Sync Discount Types
        try {
            $data = $this->discountService->syncDiscountTypesFromSap($userId);
            $results['discount_types'] = [
                'success' => true,
                'count' => count($data),
                'message' => 'Data tipe diskon berhasil disinkronisasi.',
            ];
        } catch (Throwable $e) {
            $results['discount_types'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        return $this->successResponse($results, 'Sinkronisasi semua data master dari SAP selesai.');
    }
}
