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
            $custCodes = array_map('trim', explode(',', $user->code_customer));
            $distributorIds = Distributor::whereIn('code_customer', $custCodes)->pluck('id')->toArray();
            $distributorId = count($distributorIds) > 0 ? $distributorIds : null;
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
            $custCodes = array_map('trim', explode(',', $user->code_customer));
            $distributorIds = Distributor::whereIn('code_customer', $custCodes)->pluck('id')->toArray();
            $distributorId = count($distributorIds) > 0 ? $distributorIds : null;
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
        
        // Resolve distributor dynamically:
        // 1. Try resolving from request payload (card_code / code_customer / customer_code)
        $cardCode = $request->input('card_code') ?? $request->input('code_customer') ?? $request->input('customer_code');
        
        if ($cardCode) {
            $distributor = Distributor::where('code_customer', $cardCode)->first();
            if ($distributor) {
                $distributorId = $distributor->id;
            }
        }

        // 2. Fallback to authenticated user's code_customer
        if (!$distributorId && $user->code_customer) {
            $custCodes = array_filter(array_map('trim', explode(',', $user->code_customer)));
            if (!empty($custCodes)) {
                $distributor = Distributor::where('code_customer', $custCodes[0])->first();
                if ($distributor) {
                    $distributorId = $distributor->id;
                }
            }
        }

        // 3. Fallback to direct distributor_id input
        if (!$distributorId && $request->has('distributor_id')) {
            $distributorId = (int)$request->input('distributor_id');
        }

        // 4. If still not resolved, fail validation
        if (!$distributorId) {
            return $this->errorResponse('Gagal mengidentifikasi distributor. Silakan sertakan card_code atau code_customer yang valid.', [
                'card_code' => ['The card_code or code_customer field is required.']
            ], 422);
        }

        // Decode lines if it is a JSON string
        if (is_string($request->input('lines'))) {
            $decoded = json_decode($request->input('lines'), true);
            if (is_array($decoded)) {
                $request->merge(['lines' => $decoded]);
            }
        }

        $payload = $request->validate([
            'card_code' => 'nullable|string|max:50',
            'code_customer' => 'nullable|string|max:50',
            'customer_code' => 'nullable|string|max:50',
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
            'attachment' => 'nullable',
            'attachments' => 'nullable',
            'lines' => 'required|array|min:1',
            'lines.*.item_code' => 'required|string|exists:items,item_code',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_msr' => 'nullable|string|max:50',
            'lines.*.uom_entry' => 'nullable|integer',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.disc_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.line_total' => 'required|numeric|min:0',
            'lines.*.whs_code' => 'nullable|string|exists:warehouses,whs_code',
            'lines.*.vat_group' => 'nullable|string|exists:vats,code',
            'lines.*.free_text' => 'nullable|string',
            'lines.*.ocr_code' => 'nullable|string',
            'lines.*.ocr_code2' => 'nullable|string',
            'lines.*.ocr_code3' => 'nullable|string',
        ]);

        if ($request->hasFile('attachment')) {
            $payload['attachment'] = $request->file('attachment');
        } elseif ($request->hasFile('attachments')) {
            $payload['attachments'] = $request->file('attachments');
        }

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
            $custCodes = array_filter(array_map('trim', explode(',', $user->code_customer)));
            $distributorIds = Distributor::whereIn('code_customer', $custCodes)->pluck('id')->toArray();

            $order = \App\Models\CustomerMonthlyOrder::find($id);
            if (!$order || !in_array($order->distributor_id, $distributorIds)) {
                return $this->errorResponse('Customer monthly order tidak ditemukan.', [], 404);
            }
            $distributorId = $order->distributor_id;
        }

        // Decode lines if it is a JSON string
        if (is_string($request->input('lines'))) {
            $decoded = json_decode($request->input('lines'), true);
            if (is_array($decoded)) {
                $request->merge(['lines' => $decoded]);
            }
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
            'attachment' => 'nullable',
            'attachments' => 'nullable',
            'lines' => 'sometimes|required|array|min:1',
            'lines.*.item_code' => 'required|string|exists:items,item_code',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_msr' => 'nullable|string|max:50',
            'lines.*.uom_entry' => 'nullable|integer',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.disc_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.line_total' => 'required|numeric|min:0',
            'lines.*.whs_code' => 'nullable|string|exists:warehouses,whs_code',
            'lines.*.vat_group' => 'nullable|string|exists:vats,code',
            'lines.*.free_text' => 'nullable|string',
            'lines.*.ocr_code' => 'nullable|string',
            'lines.*.ocr_code2' => 'nullable|string',
            'lines.*.ocr_code3' => 'nullable|string',
        ]);

        if ($request->hasFile('attachment')) {
            $payload['attachment'] = $request->file('attachment');
        } elseif ($request->hasFile('attachments')) {
            $payload['attachments'] = $request->file('attachments');
        }

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
            $custCodes = array_filter(array_map('trim', explode(',', $user->code_customer)));
            $distributorIds = Distributor::whereIn('code_customer', $custCodes)->pluck('id')->toArray();

            $order = \App\Models\CustomerMonthlyOrder::find($id);
            if (!$order || !in_array($order->distributor_id, $distributorIds)) {
                return $this->errorResponse('Customer monthly order tidak ditemukan.', [], 404);
            }
            $distributorId = $order->distributor_id;
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
            $custCodes = array_filter(array_map('trim', explode(',', $user->code_customer)));
            $distributorIds = Distributor::whereIn('code_customer', $custCodes)->pluck('id')->toArray();

            $order = \App\Models\CustomerMonthlyOrder::find($id);
            if (!$order || !in_array($order->distributor_id, $distributorIds)) {
                return $this->errorResponse('Customer monthly order tidak ditemukan.', [], 404);
            }
            $distributorId = $order->distributor_id;
        }

        try {
            $salesOrder = $this->service->postToSalesOrder($id, $user->id, $distributorId);
            return $this->successResponse($salesOrder, 'Customer monthly order berhasil diposting ke Sales Order dengan status WAITING_OM.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }

    /**
     * Get report grouped by depo.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function reportByDepo(Request $request): JsonResponse
    {
        $user = $request->user();
        $distributorId = null;

        if ($user->code_customer) {
            $custCodes = array_map('trim', explode(',', $user->code_customer));
            $distributorIds = Distributor::whereIn('code_customer', $custCodes)->pluck('id')->toArray();
            $distributorId = count($distributorIds) > 0 ? $distributorIds : null;
        }

        $status = $request->query('status');
        $cardCode = $request->query('card_code') ?? $request->query('customer_code');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $year = $request->query('year') ? (int)$request->query('year') : null;

        $report = $this->service->getReportByDepo($distributorId, $status, $cardCode, $startDate, $endDate, $year);

        return $this->successResponse($report, 'Laporan CMO per depo berhasil diambil.');
    }

    /**
     * Get report grouped by year / month.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function reportByYear(Request $request): JsonResponse
    {
        $user = $request->user();
        $distributorId = null;

        if ($user->code_customer) {
            $custCodes = array_map('trim', explode(',', $user->code_customer));
            $distributorIds = Distributor::whereIn('code_customer', $custCodes)->pluck('id')->toArray();
            $distributorId = count($distributorIds) > 0 ? $distributorIds : null;
        }

        $status = $request->query('status');
        $cardCode = $request->query('card_code') ?? $request->query('customer_code');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $year = $request->query('year') ? (int)$request->query('year') : null;

        $report = $this->service->getReportByYear($distributorId, $status, $cardCode, $startDate, $endDate, $year);

        return $this->successResponse($report, 'Laporan CMO per tahun/bulan berhasil diambil.');
    }
}
