<?php

namespace App\Modules\CustomerMonthlyOrder\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerMonthlyOrder;
use App\Models\Distributor;
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
     * Supports multiple distributors per API Key (many-to-many).
     *
     * @param  StoreExternalCMORequest  $request
     * @return JsonResponse
     */
    public function store(StoreExternalCMORequest $request): JsonResponse
    {
        // 1. Get all allowed distributors from middleware context
        $allowedDistributors = $request->get('allowed_distributors');
        $apiKey              = $request->get('distributor_api_key');

        if (!$allowedDistributors || $allowedDistributors->isEmpty()) {
            return $this->errorResponse('Tidak ada distributor yang terdaftar pada API Key ini.', [], 401);
        }

        // 2. Validate card_code is in allowed distributor list for this API Key
        $cardCode    = $request->input('card_code');
        $distributor = $allowedDistributors->firstWhere('code_customer', $cardCode);

        if (!$distributor) {
            $allowedCodes = $allowedDistributors->pluck('code_customer')->implode(', ');
            return $this->errorResponse(
                "card_code '{$cardCode}' tidak terdaftar untuk API Key ini. Distributor yang diizinkan: [{$allowedCodes}]",
                [],
                403
            );
        }

        $distributorRefNo = $request->input('distributor_ref_no');

        // 3. Idempotency Check — check if order with this distributor_ref_no already exists
        $existingOrder = CustomerMonthlyOrder::where('distributor_id', $distributor->id)
            ->where('distributor_ref_no', $distributorRefNo)
            ->first();

        if ($existingOrder) {
            return response()->json([
                'success'      => true,
                'message'      => 'Customer Monthly Order dengan distributor_ref_no ini sudah pernah dibuat sebelumnya (Idempotent Response).',
                'is_duplicate' => true,
            ], 200);
        }

        // 4. Prepare payload & normalize line items
        $validatedData = $request->validated();
        $lines         = $validatedData['lines'];
        $normalizedLines = [];

        foreach ($lines as $line) {
            $itemCode   = $line['item_code'];
            $item       = Item::where('item_code', $itemCode)->first();
            
            // Get active master price from DistributorItemPrice
            $distPrice = \App\Models\DistributorItemPrice::where('code_customer', $distributor->code_customer)
                ->where('item_code', $itemCode)
                ->where('status', 1)
                ->value('price');

            $masterPrice = $distPrice !== null ? (float)$distPrice : null;

            if ($masterPrice === null || $masterPrice <= 0) {
                return $this->errorResponse(
                    "Item '{$itemCode}' belum memiliki master harga aktif untuk distributor '{$distributor->code_customer}'.",
                    ['lines' => ["Item '{$itemCode}' belum terdaftar pada master harga distributor."]],
                    422
                );
            }

            // If unit_price is explicitly provided by client and > 0, validate match with master price
            if (isset($line['unit_price']) && (float)$line['unit_price'] > 0) {
                $sentPrice = (float)$line['unit_price'];
                if (abs($sentPrice - $masterPrice) > 0.01) {
                    $sentPriceFormatted   = number_format($sentPrice, 0, ',', '.');
                    $masterPriceFormatted = number_format($masterPrice, 0, ',', '.');
                    return $this->errorResponse(
                        "Harga (unit_price) untuk item '{$itemCode}' (Rp {$sentPriceFormatted}) tidak sesuai dengan harga resmi master distributor (Rp {$masterPriceFormatted}).",
                        ['lines' => ["Harga item '{$itemCode}' tidak sesuai dengan master harga."]],
                        422
                    );
                }
                $unitPrice = $sentPrice;
            } else {
                // If omitted or sent as 0, auto-fill from master price
                $unitPrice = $masterPrice;
            }

            $quantity   = (float)$line['quantity'];
            $discPercent = isset($line['disc_percent']) ? (float)$line['disc_percent'] : 0.0;

            $subtotal  = $quantity * $unitPrice;
            $lineTotal = $subtotal - ($subtotal * ($discPercent / 100));

            $normalizedLines[] = [
                'item_code'   => $itemCode,
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
                'unit_msr'    => $line['unit_msr'] ?? $item?->sales_uom ?? 'CTN',
                'whs_code'    => $line['whs_code'] ?? null,
                'disc_percent' => $discPercent,
                'line_total'  => round($lineTotal, 2),
            ];
        }

        $payload = array_merge($validatedData, [
            'distributor_ref_no' => $distributorRefNo,
            'created_via'        => 'DISTRIBUTOR_API',
            'lines'              => $normalizedLines,
        ]);

        if ($request->hasFile('attachment')) {
            $payload['attachment'] = $request->file('attachment');
        } elseif ($request->hasFile('attachments')) {
            $payload['attachments'] = $request->file('attachments');
        }

        try {
            $this->service->createOrder($payload, null, $distributor->id);

            return response()->json([
                'success'      => true,
                'message'      => 'Customer Monthly Order berhasil dibuat via External API.',
                'is_duplicate' => false,
            ], 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal membuat Customer Monthly Order: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of allowed customer codes (distributors) for this API key.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getDistributors(Request $request): JsonResponse
    {
        $allowedDistributors = $request->get('allowed_distributors');

        if (!$allowedDistributors || $allowedDistributors->isEmpty()) {
            return $this->errorResponse('Tidak ada distributor yang terdaftar pada API Key ini.', [], 401);
        }

        $data = $allowedDistributors->map(function ($distributor) {
            return [
                'card_code'     => $distributor->code_customer,
                'customer_name' => $distributor->name,
                'depo'           => $distributor->depo,
                'address'        => $distributor->address,
                'address_shipto' => $distributor->mail_address,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar distributor berhasil diambil.',
            'data'    => $data,
        ], 200);
    }

    /**
     * Get list of items/products.
     * Accessible by B2B key owner. Verifies the requested card_code is allowed.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getItems(Request $request): JsonResponse
    {
        $allowedDistributors = $request->get('allowed_distributors');
        if (!$allowedDistributors || $allowedDistributors->isEmpty()) {
            return $this->errorResponse('Tidak ada distributor yang terdaftar pada API Key ini.', [], 401);
        }

        $cardCode = $request->query('card_code');

        // If card_code is not passed, default to the first one if there's only one allowed
        if (empty($cardCode) && $allowedDistributors->count() === 1) {
            $cardCode = $allowedDistributors->first()->code_customer;
        }

        if (empty($cardCode)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter card_code wajib disertakan untuk memfilter barang.',
                'errors' => []
            ], 422);
        }

        // Validate the card_code is in the allowed list for this API Key
        $distributor = $allowedDistributors->firstWhere('code_customer', $cardCode);
        if (!$distributor) {
            $allowedCodes = $allowedDistributors->pluck('code_customer')->implode(', ');
            return response()->json([
                'success' => false,
                'message' => "Akses ditolak. card_code '{$cardCode}' tidak terdaftar untuk API Key ini. Distributor yang diizinkan: [{$allowedCodes}]",
                'errors' => []
            ], 403);
        }

        // Fetch items
        $search = $request->query('search');
        $itemService = app(\App\Modules\Item\Services\ItemService::class);
        $items = $itemService->getAll([
            'search' => $search,
            'code_customer' => $cardCode,
        ]);

        $data = $items->map(function ($item) {
            return [
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'sales_uom' => $item->sal_unit_msr ?? 'CTN',
                'price'     => (float)($item->price ?? 0.0),
                'brand'     => $item->brand,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar barang berhasil diambil.',
            'data'    => $data,
        ], 200);
    }

    /**
     * Get detail/status of CMO by distributor_ref_no or order_no.
     * Only returns orders for distributors allowed by the API Key.
     *
     * @param  Request  $request
     * @param  string   $refNo
     * @return JsonResponse
     */
    public function show(Request $request, string $refNo): JsonResponse
    {
        $allowedDistributors = $request->get('allowed_distributors');
        if (!$allowedDistributors || $allowedDistributors->isEmpty()) {
            return $this->errorResponse('Tidak ada distributor yang terdaftar pada API Key ini.', [], 401);
        }

        $allowedDistributorIds = $allowedDistributors->pluck('id')->toArray();

        $order = CustomerMonthlyOrder::with('details')
            ->whereIn('distributor_id', $allowedDistributorIds)
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
