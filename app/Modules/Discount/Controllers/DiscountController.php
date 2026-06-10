<?php

namespace App\Modules\Discount\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Discount\Requests\CreateSapDiscountRequest;
use App\Modules\Discount\Services\DiscountService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
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
}
