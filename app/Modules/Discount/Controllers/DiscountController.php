<?php

namespace App\Modules\Discount\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Discount\Requests\CreateSapDiscountRequest;
use App\Modules\Discount\Services\DiscountService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class DiscountController extends Controller
{
    use ApiResponseFormatter;

    protected DiscountService $discountService;

    /**
     * DiscountController constructor.
     *
     * @param  DiscountService  $discountService
     */
    public function __construct(DiscountService $discountService)
    {
        $this->discountService = $discountService;
    }

    /**
     * Send UDO Discount to SAP.
     *
     * @param  CreateSapDiscountRequest  $request
     * @return JsonResponse
     */
    public function sendToSap(CreateSapDiscountRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->discountService->sendToSap($request->validated(), $user ? $user->id : null);
            
            return $this->successResponse($result, 'Diskon UDO berhasil dikirim ke SAP.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 400);
        }
    }

    /**
     * Synchronize Discount Types from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function syncDiscountTypes(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->discountService->syncDiscountTypesFromSap($user ? $user->id : null);
            
            return $this->successResponse($result, 'Data tipe diskon berhasil disinkronisasi dari SAP.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 400);
        }
    }

    /**
     * Get Discount Types from local database.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getDiscountTypes(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search']);
            $result = $this->discountService->getDiscountTypesFromDb($filters);
            
            return $this->successResponse($result, 'Daftar tipe diskon berhasil diambil.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 400);
        }
    }
}
