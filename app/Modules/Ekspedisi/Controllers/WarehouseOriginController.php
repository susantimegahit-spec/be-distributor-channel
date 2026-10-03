<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WarehouseOrigin;
use App\Models\Warehouse;
use App\Traits\ApiResponseFormatter;
use App\Modules\Ekspedisi\Services\WarehouseOriginUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WarehouseOriginController extends Controller
{
    use ApiResponseFormatter;

    /**
     * Get paginated or filtered list of warehouse origins.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseOrigin::with(['warehouse']);

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('whs_name_origin', 'like', "%{$search}%")
                  ->orWhere('whs_code', 'like', "%{$search}%")
                  ->orWhere('whs_name', 'like', "%{$search}%")
                  ->orWhere('street', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $perPage = $request->get('per_page', 15);
        $origins = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse($origins, 'Daftar master origin/gudang berhasil diambil.');
    }

    /**
     * Store a new warehouse origin.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'whs_name_origin' => 'required|string|max:255',
            'whs_code'        => 'required|string|exists:warehouses,whs_code',
            'street'          => 'nullable|string',
            'status'          => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $data = $validator->validated();

        // Auto fill whs_name from public.warehouses table
        $warehouse = Warehouse::where('whs_code', $data['whs_code'])->first();
        if (!$warehouse) {
            return $this->errorResponse('Kode gudang tidak valid.', [], 422);
        }

        $data['whs_name'] = $warehouse->whs_name;
        $data['created_by'] = auth()->id();
        $data['status'] = $data['status'] ?? 'ACTIVE';

        $origin = WarehouseOrigin::create($data);

        return $this->successResponse($origin->load(['warehouse']), 'Master origin/gudang berhasil ditambahkan.', 201);
    }

    /**
     * Show detailed warehouse origin.
     */
    public function show(int $id): JsonResponse
    {
        $origin = WarehouseOrigin::with(['warehouse', 'creator', 'updater'])->find($id);

        if (!$origin) {
            return $this->errorResponse('Data origin/gudang tidak ditemukan.', [], 404);
        }

        return $this->successResponse($origin, 'Detail master origin/gudang berhasil diambil.');
    }

    /**
     * Update a warehouse origin.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $origin = WarehouseOrigin::find($id);

        if (!$origin) {
            return $this->errorResponse('Data origin/gudang tidak ditemukan.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'whs_name_origin' => 'sometimes|required|string|max:255',
            'whs_code'        => 'sometimes|required|string|exists:warehouses,whs_code',
            'street'          => 'nullable|string',
            'status'          => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $data = $validator->validated();

        // If whs_code is provided and has changed, auto fill whs_name again
        if (isset($data['whs_code']) && $data['whs_code'] !== $origin->whs_code) {
            $warehouse = Warehouse::where('whs_code', $data['whs_code'])->first();
            if (!$warehouse) {
                return $this->errorResponse('Kode gudang tidak valid.', [], 422);
            }
            $data['whs_name'] = $warehouse->whs_name;
        }

        $data['updated_by'] = auth()->id();

        $origin->update($data);

        return $this->successResponse($origin->load(['warehouse']), 'Master origin/gudang berhasil diperbarui.');
    }

    /**
     * Delete a warehouse origin.
     */
    public function destroy(int $id): JsonResponse
    {
        $origin = WarehouseOrigin::find($id);

        if (!$origin) {
            return $this->errorResponse('Data origin/gudang tidak ditemukan.', [], 404);
        }

        $origin->delete();

        return $this->successResponse(null, 'Master origin/gudang berhasil dihapus.');
    }

    /**
     * Upload master origins Excel/CSV file.
     */
    public function upload(Request $request, WarehouseOriginUploadService $uploadService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        try {
            $result = $uploadService->uploadOrigins($request->file('file'));
            return $this->successResponse($result, 'File master origin/gudang berhasil diupload dan diproses.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }
}
