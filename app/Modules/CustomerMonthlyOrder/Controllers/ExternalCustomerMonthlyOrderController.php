<?php

namespace App\Modules\CustomerMonthlyOrder\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerMonthlyOrder;
use App\Models\Item;
use App\Modules\CustomerMonthlyOrder\Requests\StoreExternalCMORequest;
use App\Modules\CustomerMonthlyOrder\Services\CustomerMonthlyOrderService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalCustomerMonthlyOrderController extends Controller
{
    use ApiResponseFormatter;

    protected CustomerMonthlyOrderService $service;

    public function __construct(CustomerMonthlyOrderService $service)
    {
        $this->service = $service;
    }

    /**
     * Store a newly created Customer Monthly Order via External B2B API.
     *
     * @param  StoreExternalCMORequest  $request
     * @return JsonResponse
     */
    public function store(StoreExternalCMORequest $request): JsonResponse
    {
        $distributor = $request->get('distributor');
        if (!$distributor) {
            return $this->errorResponse('Distributor context tidak ditemukan.', [], 401);
        }

        $distributorRefNo = $request->input('distributor_ref_no');

        // 1. Idempotency Check: Check if an order with this distributor_ref_no already exists
        $existingOrder = CustomerMonthlyOrder::where('distributor_id', $distributor->id)
            ->where('distributor_ref_no', $distributorRefNo)
            ->first();

        if ($existingOrder) {
            $existingOrder->load('details');
            return response()->json([
                'success' => true,
                'message' => 'Customer Monthly Order dengan distributor_ref_no ini sudah pernah dibuat sebelumnya (Idempotent Response).',
                'is_duplicate' => true,
                'data' => $existingOrder,
            ], 200);
        }

        // 2. Prepare payload & normalize line items
        $validatedData = $request->validated();
        $lines = $validatedData['lines'];
        $normalizedLines = [];

        foreach ($lines as $line) {
            $itemCode = $line['item_code'];
            $item = Item::where('item_code', $itemCode)->first();

            $unitPrice = isset($line['unit_price']) ? (float)$line['unit_price'] : ($item?->price ?? 0.0);
            $quantity = (float)$line['quantity'];
            $discPercent = isset($line['disc_percent']) ? (float)$line['disc_percent'] : 0.0;
            
            $subtotal = $quantity * $unitPrice;
            $lineTotal = $subtotal - ($subtotal * ($discPercent / 100));

            $normalizedLines[] = [
                'item_code' => $itemCode,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'unit_msr' => $line['unit_msr'] ?? $item?->sales_uom ?? 'CTN',
                'whs_code' => $line['whs_code'] ?? null,
                'disc_percent' => $discPercent,
                'line_total' => round($lineTotal, 2),
            ];
        }

        $payload = array_merge($validatedData, [
            'distributor_ref_no' => $distributorRefNo,
            'created_via' => 'DISTRIBUTOR_API',
            'lines' => $normalizedLines,
        ]);

        try {
            $order = $this->service->createOrder($payload, null, $distributor->id);
            $order->load('details');

            return response()->json([
                'success' => true,
                'message' => 'Customer Monthly Order berhasil dibuat via External API.',
                'is_duplicate' => false,
                'data' => $order,
            ], 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal membuat Customer Monthly Order: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get detail or status of Customer Monthly Order by distributor_ref_no or order_no.
     *
     * @param  Request  $request
     * @param  string  $refNo
     * @return JsonResponse
     */
    public function show(Request $request, string $refNo): JsonResponse
    {
        $distributor = $request->get('distributor');
        if (!$distributor) {
            return $this->errorResponse('Distributor context tidak ditemukan.', [], 401);
        }

        $order = CustomerMonthlyOrder::with('details')
            ->where('distributor_id', $distributor->id)
            ->where(function ($query) use ($refNo) {
                $query->where('distributor_ref_no', $refNo)
                    ->orWhere('order_no', $refNo);
            })
            ->first();

        if (!$order) {
            return $this->errorResponse("Customer Monthly Order dengan referensi '{$refNo}' tidak ditemukan.", [], 404);
        }

        return $this->successResponse($order, 'Detail Customer Monthly Order berhasil diambil.');
    }
}
