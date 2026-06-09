<?php

namespace App\Modules\SalesEmployee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SalesEmployee\Services\SalesEmployeeService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesEmployeeController extends Controller
{
    use ApiResponseFormatter;

    protected SalesEmployeeService $salesEmployeeService;

    /**
     * SalesEmployeeController constructor.
     *
     * @param  SalesEmployeeService  $salesEmployeeService
     */
    public function __construct(SalesEmployeeService $salesEmployeeService)
    {
        $this->salesEmployeeService = $salesEmployeeService;
    }

    /**
     * Display a listing of sales employees.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        $employees = $this->salesEmployeeService->getAll($filters);

        return $this->successResponse($employees, 'Daftar Sales Employee berhasil diambil.');
    }

    /**
     * Synchronize sales employees from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $syncedData = $this->salesEmployeeService->syncFromSap($userId);
            return $this->successResponse($syncedData, 'Data Sales Employee berhasil disinkronisasi dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
