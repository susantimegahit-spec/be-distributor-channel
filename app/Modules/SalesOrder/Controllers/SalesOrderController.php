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
        $cardCode = $request->query('card_code') ?? $request->query('customer_code');
        $orders = $this->salesOrderService->getAllOrders($distributorId, $status, $cardCode);

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

    /**
     * Post/Integrate the specified sales order to SAP.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function postToSap(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // If request has data, validate it using the update rules
        $updateData = null;
        if (!empty($request->all())) {
            $normalized = $this->salesOrderService->normalizePayload($request->all());

            $validator = \Illuminate\Support\Facades\Validator::make($normalized, [
                'card_code' => 'sometimes|required|string|max:50',
                'po_number' => 'nullable|string|max:100',
                'doc_date' => 'sometimes|required|date',
                'doc_due_date' => 'nullable|date',
                'slp_code' => 'nullable|integer',
                'cntct_code' => 'nullable|integer',
                'pay_to_code' => 'nullable|string|max:255',
                'address' => 'nullable|string',
                'ship_to_code' => 'nullable|string|max:255',
                'address2' => 'nullable|string',
                'comments' => 'nullable|string',
                'id_discount' => 'nullable|string|max:100',
                'status' => 'nullable|string', // ignored but allowed in input
                'lines' => 'sometimes|required|array|min:1',
                'lines.*.item_code' => 'required_with:lines|string|max:50',
                'lines.*.quantity' => 'required_with:lines|numeric|min:0.0001',
                'lines.*.unit_msr' => 'nullable|string|max:50',
                'lines.*.uom_entry' => 'nullable|integer',
                'lines.*.whs_code' => 'nullable|string|max:20',
                'lines.*.unit_price' => 'required_with:lines|numeric|min:0',
                'lines.*.disc_percent' => 'nullable|numeric|min:0|max:100',
                'lines.*.vat_group' => 'nullable|string|max:10',
                'lines.*.line_total' => 'required_with:lines|numeric|min:0',
                'lines.*.free_text' => 'nullable|string',
                'lines.*.ocr_code' => 'nullable|string|max:20',
                'lines.*.ocr_code2' => 'nullable|string|max:20',
                'lines.*.ocr_code3' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation error', $validator->errors()->toArray(), 422);
            }

            $updateData = $request->all();
        }

        try {
            $result = $this->salesOrderService->postToSap($id, $user->id, $updateData);
            return $this->successResponse($result['sap_response'], 'Sales order berhasil dikirim ke SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }

    /**
     * Create a new Sales Order and post/integrate it to SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function postNewToSap(Request $request): JsonResponse
    {
        $user = $request->user();

        $normalized = $this->salesOrderService->normalizePayload($request->all());

        // Validate creation inputs
        $validator = \Illuminate\Support\Facades\Validator::make($normalized, [
            'card_code' => 'required|string|max:50',
            'po_number' => 'nullable|string|max:100',
            'doc_date' => 'required|date',
            'doc_due_date' => 'nullable|date',
            'slp_code' => 'nullable|integer',
            'cntct_code' => 'nullable|integer',
            'pay_to_code' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'ship_to_code' => 'nullable|string|max:255',
            'address2' => 'nullable|string',
            'comments' => 'nullable|string',
            'id_discount' => 'nullable|string|max:100',
            'status' => 'nullable|string', // ignored but allowed in input
            'lines' => 'required|array|min:1',
            'lines.*.item_code' => 'required|string|max:50',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_msr' => 'nullable|string|max:50',
            'lines.*.uom_entry' => 'nullable|integer',
            'lines.*.whs_code' => 'nullable|string|max:20',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.disc_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.vat_group' => 'nullable|string|max:10',
            'lines.*.line_total' => 'required|numeric|min:0',
            'lines.*.free_text' => 'nullable|string',
            'lines.*.ocr_code' => 'nullable|string|max:20',
            'lines.*.ocr_code2' => 'nullable|string|max:20',
            'lines.*.ocr_code3' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation error', $validator->errors()->toArray(), 422);
        }

        try {
            $result = $this->salesOrderService->postNewToSap($request->all(), $user->id);
            return $this->successResponse($result['sap_response'], 'Sales order berhasil dikirim ke SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }
}
