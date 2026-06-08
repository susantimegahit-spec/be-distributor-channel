<?php

namespace App\Modules\SalesOrder\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SalesOrder\Requests\SaveSalesOrderRequest;
use App\Modules\SalesOrder\Services\SalesOrderService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Distributor;

class SalesOrderController extends Controller
{
    use ApiResponseFormatter;

    protected SalesOrderService $salesOrderService;

    /**
     * SalesOrderController constructor.
     *
     * @param  SalesOrderService  $salesOrderService
     */
    public function __construct(SalesOrderService $salesOrderService)
    {
        $this->salesOrderService = $salesOrderService;
    }

    /**
     * Display a listing of sales orders.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $distributorId = null;

        // If the user has a code_customer, restrict them to their own distributor data
        if ($user->code_customer) {
            $distributor = Distributor::where('code_customer', $user->code_customer)->first();
            $distributorId = $distributor?->id;
        }

        $status = $request->query('status');
        $orders = $this->salesOrderService->getAllOrders($distributorId, $status);

        return $this->successResponse($orders, 'Daftar sales order berhasil diambil.');
    }

    /**
     * Display the specified sales order.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $distributorId = null;

        if ($user->code_customer) {
            $distributor = Distributor::where('code_customer', $user->code_customer)->first();
            $distributorId = $distributor?->id;
        }

        $salesOrder = $this->salesOrderService->getOrderById($id, $distributorId);

        if (!$salesOrder) {
            abort(404, 'Sales order tidak ditemukan.');
        }

        return $this->successResponse($salesOrder, 'Detail sales order berhasil diambil.');
    }

    /**
     * Store a newly created draft sales order in storage.
     *
     * @param  SaveSalesOrderRequest  $request
     * @return JsonResponse
     */
    public function store(SaveSalesOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->code_customer) {
            abort(403, 'Hanya user distributor yang dapat membuat sales order.');
        }

        $distributor = Distributor::where('code_customer', $user->code_customer)->first();
        if (!$distributor) {
            abort(400, 'Data distributor tidak terdaftar.');
        }

        $salesOrder = $this->salesOrderService->createDraft(
            $request->validated(),
            $user->id,
            $distributor->id
        );

        return $this->successResponse($salesOrder, 'Sales order draft berhasil dibuat.', 200);
    }

    /**
     * Update the specified draft sales order in storage.
     *
     * @param  SaveSalesOrderRequest  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(SaveSalesOrderRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->code_customer) {
            abort(403, 'Hanya user distributor yang dapat mengubah sales order.');
        }

        $distributor = Distributor::where('code_customer', $user->code_customer)->first();
        if (!$distributor) {
            abort(400, 'Data distributor tidak terdaftar.');
        }

        $salesOrder = $this->salesOrderService->updateDraft(
            $id,
            $request->validated(),
            $user->id,
            $distributor->id
        );

        return $this->successResponse($salesOrder, 'Sales order draft berhasil diperbarui.');
    }

    /**
     * Remove the specified draft sales order from storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->code_customer) {
            abort(403, 'Hanya user distributor yang dapat menghapus sales order.');
        }

        $distributor = Distributor::where('code_customer', $user->code_customer)->first();
        if (!$distributor) {
            abort(400, 'Data distributor tidak terdaftar.');
        }

        $this->salesOrderService->deleteDraft($id, $user->id, $distributor->id);

        return $this->successResponse(null, 'Sales order draft berhasil dihapus.');
    }
}
