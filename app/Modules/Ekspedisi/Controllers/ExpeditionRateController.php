<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerShipto;
use App\Models\Expedition;
use App\Models\ExpeditionRate;
use App\Models\Warehouse;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExpeditionRateController extends Controller
{
    use ApiResponseFormatter;

    /**
     * Get paginated or filtered list of expedition rates.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExpeditionRate::with(['expedition', 'warehouse', 'destination', 'approver']);

        $expeditionCode = $request->get('expedition_code') ?? $request->get('expedisi_code') ?? $request->get('kode_ekspedisi');
        if (!empty($expeditionCode)) {
            $query->whereHas('expedition', function ($q) use ($expeditionCode) {
                $q->where('expedition_code', $expeditionCode);
            });
        }

        $warehouseCode = $request->get('warehouse_code') ?? $request->get('kode_gudang') ?? $request->get('origin_code');
        if (!empty($warehouseCode)) {
            $query->whereHas('warehouse', function ($q) use ($warehouseCode) {
                $q->where('whs_code', $warehouseCode);
            });
        }

        if ($request->has('destination_id')) {
            $query->where('destination_id', $request->get('destination_id'));
        }

        $transportMode = $request->get('transport_mode');
        if (!empty($transportMode)) {
            $modes = is_array($transportMode)
                ? $transportMode
                : explode(',', (string) $transportMode);

            $expandedModes = [];
            $mapping = [
                'd' => ['d', 'darat'],
                'darat' => ['d', 'darat'],
                'l' => ['l', 'laut'],
                'laut' => ['l', 'laut'],
                'u' => ['u', 'udara'],
                'udara' => ['u', 'udara'],
            ];

            foreach ($modes as $item) {
                $clean = strtolower(trim((string) $item));
                if ($clean === '') {
                    continue;
                }
                if (isset($mapping[$clean])) {
                    $expandedModes = array_merge($expandedModes, $mapping[$clean]);
                } else {
                    $expandedModes[] = $clean;
                }
            }

            $expandedModes = array_values(array_unique($expandedModes));

            if (!empty($expandedModes)) {
                $query->whereIn(DB::raw('LOWER(transport_mode)'), $expandedModes);
            }
        }

        if ($request->has('service_type')) {
            $query->where('service_type', $request->get('service_type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter Flag Approval Atasan
        if ($request->has('flag') && $request->get('flag') !== null && $request->get('flag') !== '') {
            $query->where('flag', filter_var($request->get('flag'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', strtoupper($request->get('approval_status')));
        }

        $search = $request->get('search');
        if (!empty($search)) {
            $searchLower = strtolower($search);
            $query->where(function ($q) use ($searchLower) {
                $q->whereHas('destination', function ($dq) use ($searchLower) {
                    $dq->whereRaw('LOWER(alias) LIKE ?', ["%{$searchLower}%"])
                      ->orWhereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
                      ->orWhereRaw('LOWER(card_code) LIKE ?', ["%{$searchLower}%"])
                      ->orWhereRaw('LOWER(city) LIKE ?', ["%{$searchLower}%"])
                      ->orWhereRaw('LOWER(address) LIKE ?', ["%{$searchLower}%"]);
                })->orWhereHas('expedition', function ($eq) use ($searchLower) {
                    $eq->whereRaw('LOWER(expedition_name) LIKE ?', ["%{$searchLower}%"])
                      ->orWhereRaw('LOWER(expedition_code) LIKE ?', ["%{$searchLower}%"]);
                })->orWhereHas('warehouse', function ($wq) use ($searchLower) {
                    $wq->whereRaw('LOWER(whs_name) LIKE ?', ["%{$searchLower}%"])
                      ->orWhereRaw('LOWER(whs_code) LIKE ?', ["%{$searchLower}%"]);
                });
            });
        }

        $perPage = $request->get('per_page', 15);
        $rates = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse($rates, 'Daftar tarif ekspedisi berhasil diambil.');
    }

    /**
     * Store a new expedition rate.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'expedition_id' => 'required|integer|exists:pgsql_ekspedisi.ekspedisi.expeditions,id',
            'warehouse_id' => 'nullable|integer|exists:public.warehouses,id',
            'destination_id' => 'nullable|integer',
            'transport_mode' => 'nullable|string|max:50',
            'service_type' => 'nullable|string|max:50',
            'min_tonnage' => 'nullable|numeric|min:0',
            'max_tonnage' => 'nullable|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'eta_days' => 'nullable|integer|min:0',
            'min_shipment_qty' => 'nullable|numeric|min:0',
            'max_shipment_qty' => 'nullable|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'status' => 'nullable|string|max:20',
            'flag' => 'nullable|boolean',
            'approval_status' => 'nullable|string|max:20',
            'approval_notes' => 'nullable|string',
            'remarks' => 'nullable|string',
            'upload_batch_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $data = $validator->validated();
        $data['created_by'] = auth()->id();
        $data['status'] = $data['status'] ?? 'ACTIVE';
        $data['flag'] = $request->boolean('flag', false);
        $data['approval_status'] = $data['approval_status'] ?? ($data['flag'] ? 'APPROVED' : 'PENDING');

        if ($data['flag']) {
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        }

        $rate = ExpeditionRate::create($data);

        return $this->successResponse($rate->load(['expedition', 'warehouse', 'destination', 'approver']), 'Tarif ekspedisi berhasil ditambahkan.', 201);
    }

    /**
     * Show detailed expedition rate.
     */
    public function show(int $id): JsonResponse
    {
        $rate = ExpeditionRate::with(['expedition', 'warehouse', 'destination', 'creator', 'updater', 'approver'])->find($id);

        if (!$rate) {
            return $this->errorResponse('Data tarif ekspedisi tidak ditemukan.', [], 404);
        }

        return $this->successResponse($rate, 'Detail tarif ekspedisi berhasil diambil.');
    }

    /**
     * Update an expedition rate.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $rate = ExpeditionRate::find($id);

        if (!$rate) {
            return $this->errorResponse('Data tarif ekspedisi tidak ditemukan.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'expedition_id' => 'nullable|integer|exists:pgsql_ekspedisi.ekspedisi.expeditions,id',
            'expedition' => 'nullable|string',
            'warehouse_id' => 'nullable|integer|exists:public.warehouses,id',
            'origin' => 'nullable|string',
            'destination_id' => 'nullable|integer',
            'destination' => 'nullable|string',
            'transport_mode' => 'nullable|string|max:50',
            'service_type' => 'nullable|string|max:50',
            'min_tonnage' => 'nullable|numeric|min:0',
            'min_kg' => 'nullable|numeric|min:0',
            'max_tonnage' => 'nullable|numeric|min:0',
            'max_kg' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'rate' => 'nullable|numeric|min:0',
            'eta_days' => 'nullable|integer|min:0',
            'min_shipment_qty' => 'nullable|numeric|min:0',
            'max_shipment_qty' => 'nullable|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'status' => 'nullable|string|max:20',
            'flag' => 'nullable|boolean',
            'approval_status' => 'nullable|string|max:20',
            'approval_notes' => 'nullable|string',
            'remarks' => 'nullable|string',
            'upload_batch_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $payload = [];

        // Flexible Expedition Resolver
        if ($request->filled('expedition_id')) {
            $payload['expedition_id'] = $request->get('expedition_id');
        } elseif ($request->filled('expedition')) {
            $expInput = trim((string) $request->get('expedition'));
            $exp = Expedition::where('expedition_code', $expInput)
                ->orWhere('id', is_numeric($expInput) ? (int) $expInput : 0)
                ->orWhere('expedition_name', $expInput)
                ->first();
            if ($exp) {
                $payload['expedition_id'] = $exp->id;
            }
        }

        // Flexible Warehouse Origin Resolver
        if ($request->filled('warehouse_id')) {
            $payload['warehouse_id'] = $request->get('warehouse_id');
        } elseif ($request->filled('origin')) {
            $originInput = trim((string) $request->get('origin'));
            $whs = Warehouse::where('whs_code', $originInput)
                ->orWhere('id', is_numeric($originInput) ? (int) $originInput : 0)
                ->orWhere('whs_name', $originInput)
                ->first();
            if ($whs) {
                $payload['warehouse_id'] = $whs->id;
            }
        }

        // Flexible Destination Shipto Resolver
        if ($request->filled('destination_id')) {
            $payload['destination_id'] = $request->get('destination_id');
        } elseif ($request->filled('destination')) {
            $destInput = trim((string) $request->get('destination'));
            $shipto = CustomerShipto::where('card_code', $destInput)
                ->orWhere('id', is_numeric($destInput) ? (int) $destInput : 0)
                ->orWhere('alias', $destInput)
                ->orWhere('name', $destInput)
                ->first();
            if ($shipto) {
                $payload['destination_id'] = $shipto->id;
            }
        }

        // Tonnage & Price Resolvers
        if ($request->has('min_tonnage') || $request->has('min_kg')) {
            $payload['min_tonnage'] = floatval($request->get('min_tonnage') ?? $request->get('min_kg') ?? 0);
        }
        if ($request->has('max_tonnage') || $request->has('max_kg')) {
            $payload['max_tonnage'] = floatval($request->get('max_tonnage') ?? $request->get('max_kg') ?? 0);
        }
        if ($request->has('price') || $request->has('rate')) {
            $payload['price'] = floatval($request->get('price') ?? $request->get('rate') ?? 0);
        }

        // Optional standard fields
        foreach (['transport_mode', 'service_type', 'eta_days', 'min_shipment_qty', 'max_shipment_qty', 'valid_from', 'valid_until', 'status', 'approval_notes', 'remarks', 'upload_batch_id'] as $field) {
            if ($request->has($field)) {
                $payload[$field] = $request->get($field);
            }
        }

        // Handle Flag / Approval Status update
        if ($request->has('flag')) {
            $flagVal = filter_var($request->get('flag'), FILTER_VALIDATE_BOOLEAN);
            $payload['flag'] = $flagVal;
            $payload['approval_status'] = $flagVal ? 'APPROVED' : 'PENDING';
            if ($flagVal) {
                $payload['approved_by'] = auth()->id();
                $payload['approved_at'] = now();
            }
        }

        if ($request->filled('approval_status')) {
            $payload['approval_status'] = strtoupper($request->get('approval_status'));
            if ($payload['approval_status'] === 'APPROVED') {
                $payload['flag'] = true;
                $payload['approved_by'] = auth()->id();
                $payload['approved_at'] = now();
            } elseif ($payload['approval_status'] === 'REJECTED') {
                $payload['flag'] = false;
                $payload['approved_by'] = auth()->id();
                $payload['approved_at'] = now();
            }
        }

        $payload['updated_by'] = auth()->id();

        $rate->update($payload);

        return $this->successResponse($rate->fresh()->load(['expedition', 'warehouse', 'destination', 'approver']), 'Tarif ekspedisi berhasil diperbarui.');
    }

    /**
     * Approve rate by Atasan / Supervisor.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $rate = ExpeditionRate::find($id);

        if (!$rate) {
            return $this->errorResponse('Data tarif ekspedisi tidak ditemukan.', [], 404);
        }

        $rate->update([
            'flag' => true,
            'approval_status' => 'APPROVED',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approval_notes' => $request->input('notes') ?? 'Approved by supervisor',
            'updated_by' => auth()->id(),
        ]);

        return $this->successResponse($rate->load(['expedition', 'warehouse', 'destination', 'approver']), 'Tarif ekspedisi berhasil disetujui (Flag Aktif).');
    }

    /**
     * Reject rate by Atasan / Supervisor.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $rate = ExpeditionRate::find($id);

        if (!$rate) {
            return $this->errorResponse('Data tarif ekspedisi tidak ditemukan.', [], 404);
        }

        $rate->update([
            'flag' => false,
            'approval_status' => 'REJECTED',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approval_notes' => $request->input('notes') ?? $request->input('reason') ?? 'Rejected by supervisor',
            'updated_by' => auth()->id(),
        ]);

        return $this->successResponse($rate->load(['expedition', 'warehouse', 'destination', 'approver']), 'Tarif ekspedisi ditolak.');
    }

    /**
     * Bulk approve multiple rates by Atasan.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rate_ids' => 'required|array|min:1',
            'rate_ids.*' => 'integer|exists:pgsql_ekspedisi.ekspedisi.expedition_rates,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $rateIds = $request->input('rate_ids');
        $notes = $request->input('notes') ?? 'Bulk approved by supervisor';

        ExpeditionRate::whereIn('id', $rateIds)->update([
            'flag' => true,
            'approval_status' => 'APPROVED',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approval_notes' => $notes,
            'updated_by' => auth()->id(),
        ]);

        return $this->successResponse(['approved_count' => count($rateIds)], 'Daftar tarif ekspedisi berhasil disetujui sekaligus.');
    }

    /**
     * Delete an expedition rate.
     */
    public function destroy(int $id): JsonResponse
    {
        $rate = ExpeditionRate::find($id);

        if (!$rate) {
            return $this->errorResponse('Data tarif ekspedisi tidak ditemukan.', [], 404);
        }

        $rate->delete();

        return $this->successResponse(null, 'Tarif ekspedisi berhasil dihapus.');
    }

    /**
     * Get ranked list of expedition rates based on origin, destination, and weight.
     */
    public function rank(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'origin' => 'required|string',
            'destination' => 'required|string',
            'weight' => 'nullable|numeric|min:0',
            'service_type' => 'nullable|string',
            'transport_mode' => 'nullable',
            'include_unapproved' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $origin = $request->get('origin');
        $destination = $request->get('destination');
        $weight = $request->filled('weight') ? floatval($request->get('weight')) : null;
        $serviceType = $request->get('service_type');
        $transportMode = $request->get('transport_mode');

        // Resolve origin to warehouse ID
        $warehouse = DB::table('warehouses')
            ->where('whs_code', $origin)
            ->first();

        if (!$warehouse) {
            return $this->successResponse([], 'Gudang asal tidak ditemukan.');
        }

        // Resolve destination to customer shipto IDs
        $shiptoIds = DB::table('customer_shiptos')
            ->where('card_code', $destination)
            ->pluck('id')
            ->toArray();

        if (is_numeric($destination)) {
            $exists = DB::table('customer_shiptos')
                ->where('id', (int) $destination)
                ->exists();
            if ($exists) {
                $shiptoIds[] = (int) $destination;
            }
        }

        if (empty($shiptoIds)) {
            return $this->successResponse([], 'Tujuan/Customer tidak ditemukan.');
        }

        // Query active rates matching route and weight limits, sorted by price ASC
        $query = ExpeditionRate::with(['expedition', 'warehouse', 'destination', 'approver'])
            ->where('warehouse_id', $warehouse->id)
            ->whereIn('destination_id', $shiptoIds)
            ->where('status', 'ACTIVE');

        // By default, only include approved rates (flag: true) unless explicitly requested
        if (!$request->boolean('include_unapproved', false)) {
            $query->where(function ($q) {
                $q->where('flag', true)->orWhereNull('flag');
            });
        }

        if ($weight !== null) {
            $query->where('min_tonnage', '<=', $weight)
                ->where('max_tonnage', '>=', $weight);
        }

        if (!empty($serviceType)) {
            $query->whereRaw('LOWER(service_type) = ?', [strtolower($serviceType)]);
        }

        if (!empty($transportMode)) {
            $modes = is_array($transportMode)
                ? $transportMode
                : explode(',', (string) $transportMode);

            $expandedModes = [];
            $mapping = [
                'd' => ['d', 'darat'],
                'darat' => ['d', 'darat'],
                'l' => ['l', 'laut'],
                'laut' => ['l', 'laut'],
                'u' => ['u', 'udara'],
                'udara' => ['u', 'udara'],
            ];

            foreach ($modes as $item) {
                $clean = strtolower(trim((string) $item));
                if ($clean === '') {
                    continue;
                }
                if (isset($mapping[$clean])) {
                    $expandedModes = array_merge($expandedModes, $mapping[$clean]);
                } else {
                    $expandedModes[] = $clean;
                }
            }

            $expandedModes = array_values(array_unique($expandedModes));

            if (!empty($expandedModes)) {
                $query->whereIn(DB::raw('LOWER(transport_mode)'), $expandedModes);
            }
        }

        $rates = $query->orderBy('price', 'asc')->get();

        return $this->successResponse($rates, 'Perankingan tarif ekspedisi berhasil diambil.');
    }

    /**
     * Upload master expedition rates Excel/CSV file.
     */
    public function upload(Request $request, \App\Modules\Ekspedisi\Services\ExpeditionUploadService $uploadService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        try {
            $result = $uploadService->uploadRates($request->file('file'));
            return $this->successResponse($result, 'File tarif ekspedisi berhasil diupload dan diproses.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }
}
