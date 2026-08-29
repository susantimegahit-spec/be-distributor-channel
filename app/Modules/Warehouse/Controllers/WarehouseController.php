<?php

namespace App\Modules\Warehouse\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Warehouse\Requests\CreateWarehouseRequest;
use App\Modules\Warehouse\Requests\UpdateWarehouseRequest;
use App\Modules\Warehouse\Services\WarehouseService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    use ApiResponseFormatter;

    protected WarehouseService $warehouseService;

    /**
     * WarehouseController constructor.
     *
     * @param  WarehouseService  $warehouseService
     */
    public function __construct(WarehouseService $warehouseService)
    {
        $this->warehouseService = $warehouseService;
    }

    /**
     * Display a listing of warehouses.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'master_unit_id', 'status', 'sort_by', 'sort_dir']);
        $warehouses = $this->warehouseService->getAll($filters);

        return $this->successResponse($warehouses, 'Daftar master gudang berhasil diambil.');
    }

    /**
     * Store a newly created warehouse manually.
     *
     * @param  CreateWarehouseRequest  $request
     * @return JsonResponse
     */
    public function store(CreateWarehouseRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            $warehouse = $this->warehouseService->create($request->validated(), $userId);

            return $this->successResponse($warehouse, 'Data master gudang berhasil ditambahkan.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified warehouse with unit details.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $warehouse = $this->warehouseService->getById($id);

        if (!$warehouse) {
            return $this->errorResponse('Data gudang tidak ditemukan.', null, 404);
        }

        return $this->successResponse($warehouse, 'Detail master gudang berhasil diambil.');
    }

    /**
     * Update the specified warehouse (e.g. assigning/changing master_unit_id).
     *
     * @param  UpdateWarehouseRequest  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(UpdateWarehouseRequest $request, int $id): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            $warehouse = $this->warehouseService->update($id, $request->validated(), $userId);

            return $this->successResponse($warehouse, 'Data master gudang berhasil diperbarui.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Data gudang tidak ditemukan.', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified warehouse.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            $this->warehouseService->delete($id, $userId);

            return $this->successResponse(null, 'Data master gudang berhasil dihapus.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Data gudang tidak ditemukan.', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Synchronize warehouses from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $syncedData = $this->warehouseService->syncFromSap($userId);
            return $this->successResponse($syncedData, 'Data master gudang berhasil disinkronisasi dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }
}
