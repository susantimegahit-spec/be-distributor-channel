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
     * Get the maximum discount setting.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getMaxDiscount(Request $request): JsonResponse
    {
        $setting = \App\Models\DiscountSetting::first();
        $maxDiscount = $setting ? (float)$setting->max_discount : 20.00;

        return $this->successResponse([
            'max_discount' => $maxDiscount
        ], 'Batas maksimal diskon berhasil diambil.');
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

        // If action is submit, immediately submit it to OM
        if ($request->input('action') === 'submit') {
            try {
                $salesOrder = $this->salesOrderService->submitOrder($salesOrder->id, $user->id);
                $message = 'Sales order berhasil dibuat dan langsung dikirim ke OM.';
            } catch (\Exception $e) {
                return $this->errorResponse($e->getMessage(), [], 400);
            }
        } else {
            $message = 'Sales order draft berhasil dibuat.';
        }

        return $this->successResponse($salesOrder, $message, 200);
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

        // Handle workflow actions if 'action' parameter is present
        if ($request->has('action')) {
            $action = $request->input('action');
            $notes = $request->input('notes');

            try {
                if ($action === 'submit') {
                    $salesOrder = $this->salesOrderService->submitOrder($id, $user->id);
                    $message = 'Sales order berhasil dikirim ke OM.';
                } elseif ($action === 'approve') {
                    $salesOrder = $this->salesOrderService->approveOrder($id, $user->id, $notes, $request->all());
                    $message = 'Sales order berhasil disetujui.';
                } elseif ($action === 'reject') {
                    $salesOrder = $this->salesOrderService->rejectOrder($id, $user->id, $notes);
                    $message = 'Sales order berhasil ditolak.';
                } else {
                    abort(400, 'Aksi tidak valid.');
                }
                return $this->successResponse($salesOrder, $message);
            } catch (\Exception $e) {
                return $this->errorResponse($e->getMessage(), [], 400);
            }
        }

        // Handle standard draft update
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



    /**
     * Save discounts by Admin Sales.
     */
    public function saveDiscounts(Request $request, int $id): JsonResponse
    {
        try {
            $salesOrder = $this->salesOrderService->saveDiscounts($id, $request->all(), $request->user()->id);
            return $this->successResponse($salesOrder, 'Diskon sales order berhasil disimpan dan diteruskan ke Finance.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }

    /**
     * Handle quick actions from ASM approval email.
     */
    public function emailAction(Request $request, int $id)
    {
        if (! $request->hasValidSignature()) {
            return response()->make('
                <div style="font-family: sans-serif; text-align: center; margin-top: 100px; color: #c62828;">
                    <h2>✗ Tautan Kedaluwarsa</h2>
                    <p>Tautan persetujuan ini tidak sah atau sudah kedaluwarsa.</p>
                </div>
            ', 401);
        }

        $action = $request->query('action');
        $userId = (int)$request->query('user_id');

        $salesOrder = $this->salesOrderService->getOrderById($id);
        if (!$salesOrder) {
            return response()->make('
                <div style="font-family: sans-serif; text-align: center; margin-top: 100px; color: #c62828;">
                    <h2>✗ Order Tidak Ditemukan</h2>
                    <p>Sales order tidak ditemukan di sistem.</p>
                </div>
            ', 404);
        }

        if ($action === 'approve') {
            try {
                $this->salesOrderService->approveOrder($id, $userId, 'Disetujui instan via Email.');
                return response()->make('
                    <div style="font-family: sans-serif; text-align: center; margin-top: 100px; color: #2e7d32;">
                        <h2>✓ Berhasil Disetujui</h2>
                        <p>Sales Order #' . htmlspecialchars($salesOrder->order_no) . ' telah berhasil disetujui.</p>
                    </div>
                ');
            } catch (\Exception $e) {
                return response()->make('
                    <div style="font-family: sans-serif; text-align: center; margin-top: 100px; color: #c62828;">
                        <h2>✗ Gagal Menyetujui</h2>
                        <p>' . htmlspecialchars($e->getMessage()) . '</p>
                    </div>
                ');
            }
        } elseif ($action === 'reject') {
            return response()->make('
                <div style="font-family: sans-serif; max-width: 500px; margin: 100px auto; padding: 20px; border: 1px solid #e1e8ed; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                    <h2 style="color: #c62828; margin-top: 0; font-size: 20px;">Tolak Sales Order #' . htmlspecialchars($salesOrder->order_no) . '</h2>
                    <p style="font-size: 14px; color: #666666;">Harap masukkan alasan penolakan untuk mengembalikan order ke Distributor.</p>
                    <form method="POST" action="' . URL::signedRoute('sales-orders.email-reject-post', ['id' => $id]) . '">
                        ' . csrf_field() . '
                        <input type="hidden" name="user_id" value="' . $userId . '">
                        <textarea name="notes" required placeholder="Tulis alasan penolakan di sini..." style="width: 100%; height: 100px; padding: 10px; border-radius: 4px; border: 1px solid #cccccc; box-sizing: border-box; margin-bottom: 15px; font-family: inherit; font-size: 14px; resize: none;"></textarea>
                        <button type="submit" style="background-color: #c62828; color: white; border: none; padding: 10px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px;">Kirim Penolakan</button>
                    </form>
                </div>
            ');
        }

        return response()->make('Aksi tidak valid.', 400);
    }

    /**
     * Process quick reject POST request from email form.
     */
    public function emailRejectPost(Request $request, int $id)
    {
        if (! $request->hasValidSignature()) {
            return response()->make('
                <div style="font-family: sans-serif; text-align: center; margin-top: 100px; color: #c62828;">
                    <h2>✗ Tautan Kedaluwarsa</h2>
                    <p>Proses penolakan gagal karena tautan sudah kedaluwarsa.</p>
                </div>
            ', 401);
        }

        $userId = (int)$request->input('user_id');
        $notes = $request->input('notes');

        try {
            $this->salesOrderService->rejectOrder($id, $userId, $notes);
            return response()->make('
                <div style="font-family: sans-serif; text-align: center; margin-top: 100px; color: #2e7d32;">
                    <h2>✓ Berhasil Ditolak</h2>
                    <p>Sales Order telah berhasil ditolak dengan alasan: <strong>' . htmlspecialchars($notes) . '</strong></p>
                </div>
            ');
        } catch (\Exception $e) {
            return response()->make('
                <div style="font-family: sans-serif; text-align: center; margin-top: 100px; color: #c62828;">
                    <h2>✗ Gagal Menolak</h2>
                    <p>' . htmlspecialchars($e->getMessage()) . '</p>
                </div>
            ');
        }
    }
}
