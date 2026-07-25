<?php

namespace App\Modules\Distributor\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Distributor\Services\CustomerShiptoService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerShiptoController extends Controller
{
    use ApiResponseFormatter;

    protected CustomerShiptoService $shiptoService;

    /**
     * CustomerShiptoController constructor.
     *
     * @param CustomerShiptoService $shiptoService
     */
    public function __construct(CustomerShiptoService $shiptoService)
    {
        $this->shiptoService = $shiptoService;
    }

    /**
     * Display a listing of the customer shiptos.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['card_code', 'search']);
        $perPage = $request->input('per_page', 15);

        $shiptos = $this->shiptoService->getPaginated($filters, $perPage);

        return $this->successResponse($shiptos, 'Daftar Ship To master berhasil diambil.');
    }

    /**
     * Synchronize customer shiptos from SAP.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            $data = $this->shiptoService->syncFromSap($userId);

            return $this->successResponse([
                'count' => count($data),
            ], 'Sinkronisasi Ship To master dari SAP berhasil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal melakukan sinkronisasi: ' . $e->getMessage(), [], 500);
        }
    }
}
