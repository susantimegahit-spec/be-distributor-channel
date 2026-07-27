<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ExpeditionRate;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpeditionRateController extends Controller
{
    use ApiResponseFormatter;

    /**
     * Get paginated or filtered list of expedition rates.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExpeditionRate::with(['expedition', 'warehouse', 'destination']);

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

        if ($request->has('transport_mode')) {
            $query->where('transport_mode', $request->get('transport_mode'));
        }

        if ($request->has('service_type')) {
            $query->where('service_type', $request->get('service_type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
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
            'remarks' => 'nullable|string',
            'upload_batch_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $data = $validator->validated();
        $data['created_by'] = auth()->id();
        $data['status'] = $data['status'] ?? 'ACTIVE';

        $rate = ExpeditionRate::create($data);

        return $this->successResponse($rate->load(['expedition', 'warehouse', 'destination']), 'Tarif ekspedisi berhasil ditambahkan.', 201);
    }

    /**
     * Show detailed expedition rate.
     */
    public function show(int $id): JsonResponse
    {
        $rate = ExpeditionRate::with(['expedition', 'warehouse', 'destination', 'creator', 'updater'])->find($id);

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
            'expedition_id' => 'sometimes|required|integer|exists:pgsql_ekspedisi.ekspedisi.expeditions,id',
            'warehouse_id' => 'nullable|integer|exists:public.warehouses,id',
            'destination_id' => 'nullable|integer',
            'transport_mode' => 'nullable|string|max:50',
            'service_type' => 'nullable|string|max:50',
            'min_tonnage' => 'nullable|numeric|min:0',
            'max_tonnage' => 'nullable|numeric|min:0',
            'price' => 'sometimes|required|numeric|min:0',
            'eta_days' => 'nullable|integer|min:0',
            'min_shipment_qty' => 'nullable|numeric|min:0',
            'max_shipment_qty' => 'nullable|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'status' => 'nullable|string|max:20',
            'remarks' => 'nullable|string',
            'upload_batch_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $data = $validator->validated();
        $data['updated_by'] = auth()->id();

        $rate->update($data);

        return $this->successResponse($rate->load(['expedition', 'warehouse', 'destination']), 'Tarif ekspedisi berhasil diperbarui.');
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
