<?php

namespace App\Modules\CustomerMonthlyOrder\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Modules\CustomerMonthlyOrder\Services\CustomerMonthlyOrderService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerMonthlyOrderController extends Controller
{
    use ApiResponseFormatter;

    protected CustomerMonthlyOrderService $service;

    public function __construct(CustomerMonthlyOrderService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of customer monthly orders.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $distributorId = null;

        if ($user->code_customer) {
            $distributor = Distributor::where('code_customer', $user->code_customer)->first();
            $distributorId = $distributor?->id;
        }

        $status = $request->query('status');
        $cardCode = $request->query('card_code') ?? $request->query('customer_code');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $orders = $this->service->getAllOrders($distributorId, $status, $cardCode, $startDate, $endDate);

        return $this->successResponse($orders, 'Daftar customer monthly order berhasil diambil.');
    }

    /**
     * Display the specified customer monthly order.
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

        $order = $this->service->getOrderById($id, $distributorId);

        if (!$order) {
            return $this->errorResponse('Customer monthly order tidak ditemukan.', [], 404);
        }

        return $this->successResponse($order, 'Detail customer monthly order berhasil diambil.');
    }

    /**
     * Store a newly created customer monthly order.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $distributorId = null;

        if ($user->code_customer) {
            $distributor = Distributor::where('code_customer', $user->code_customer)->first();
            if (!$distributor) {
                return $this->errorResponse('Distributor tidak terdaftar.', [], 403);
            }
            $distributorId = $distributor->id;
        } else {
            $request->validate([
                'distributor_id' => 'required|exists:distributors,id'
            ]);
            $distributorId = (int)$request->input('distributor_id');
        }

        $payload = $request->validate([
            'po_number' => 'nullable|string|max:100',
            'doc_date' => 'required|date',
            'doc_due_date' => 'nullable|date',
            'eta_date' => 'nullable|date',
            'slp_code' => 'nullable|integer',
            'cntct_code' => 'nullable|integer',
            'pay_to_code' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'ship_to_code' => 'nullable|string|max:255',
            'address2' => 'nullable|string',
            'disc_percent' => 'nullable|numeric|min:0|max:100',
            'comments' => 'nullable|string',
            'id_discount' => 'nullable|string|max:100',
            'series' => 'nullable|string',
            'series_name' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.item_code' => 'required|string|exists:items,item_code',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.disc_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.line_total' => 'required|numeric|min:0',
            'lines.*.whs_code' => 'nullable|string|exists:warehouses,whs_code',
            'lines.*.vat_group' => 'nullable|string|exists:vats,code',
            'lines.*.ocr_code' => 'nullable|string',
            'lines.*.ocr_code2' => 'nullable|string',
            'lines.*.ocr_code3' => 'nullable|string',
        ]);

        try {
            $order = $this->service->createOrder($payload, $user->id, $distributorId);
            return $this->successResponse($order, 'Customer monthly order draft berhasil dibuat.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal membuat customer monthly order: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update the specified customer monthly order.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $distributorId = null;

        if ($user->code_customer) {
            $distributor = Distributor::where('code_customer', $user->code_customer)->first();
            $distributorId = $distributor?->id;
        }

        $payload = $request->validate([
            'po_number' => 'nullable|string|max:100',
            'doc_date' => 'sometimes|required|date',
            'doc_due_date' => 'nullable|date',
            'eta_date' => 'nullable|date',
            'slp_code' => 'nullable|integer',
            'cntct_code' => 'nullable|integer',
            'pay_to_code' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'ship_to_code' => 'nullable|string|max:255',
            'address2' => 'nullable|string',
            'disc_percent' => 'nullable|numeric|min:0|max:100',
            'comments' => 'nullable|string',
            'id_discount' => 'nullable|string|max:100',
            'series' => 'nullable|string',
            'series_name' => 'nullable|string',
            'lines' => 'sometimes|required|array|min:1',
            'lines.*.item_code' => 'required|string|exists:items,item_code',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.disc_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.line_total' => 'required|numeric|min:0',
            'lines.*.whs_code' => 'nullable|string|exists:warehouses,whs_code',
            'lines.*.vat_group' => 'nullable|string|exists:vats,code',
            'lines.*.ocr_code' => 'nullable|string',
            'lines.*.ocr_code2' => 'nullable|string',
            'lines.*.ocr_code3' => 'nullable|string',
        ]);

        try {
            $order = $this->service->updateOrder($id, $payload, $user->id, $distributorId);
            return $this->successResponse($order, 'Customer monthly order berhasil diperbarui.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }

    /**
     * Remove the specified customer monthly order.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $distributorId = null;

        if ($user->code_customer) {
            $distributor = Distributor::where('code_customer', $user->code_customer)->first();
            $distributorId = $distributor?->id;
        }

        try {
            $this->service->deleteOrder($id, $distributorId);
            return $this->successResponse(null, 'Customer monthly order berhasil dihapus.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }

    /**
     * Post the specified customer monthly order to Sales Order.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function postToSalesOrder(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $distributorId = null;

        if ($user->code_customer) {
            $distributor = Distributor::where('code_customer', $user->code_customer)->first();
            $distributorId = $distributor?->id;
        }

        try {
            $salesOrder = $this->service->postToSalesOrder($id, $user->id, $distributorId);
            return $this->successResponse($salesOrder, 'Customer monthly order berhasil diposting ke Sales Order dengan status WAITING_OM.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }
}
