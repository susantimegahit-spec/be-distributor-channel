<?php

namespace App\Modules\MasterUnit\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterUnit\Requests\CreateMasterUnitRequest;
use App\Modules\MasterUnit\Requests\UpdateMasterUnitRequest;
use App\Modules\MasterUnit\Services\MasterUnitService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterUnitController extends Controller
{
    use ApiResponseFormatter;

    protected MasterUnitService $masterUnitService;

    /**
     * MasterUnitController constructor.
     *
     * @param  MasterUnitService  $masterUnitService
     */
    public function __construct(MasterUnitService $masterUnitService)
    {
        $this->masterUnitService = $masterUnitService;
    }

    /**
     * Display a listing of master units.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'sort_by', 'sort_dir', 'per_page']);
        $units = $this->masterUnitService->getAll($filters);

        return $this->successResponse($units, 'Daftar master unit berhasil diambil.');
    }

    /**
     * Store a newly created master unit.
     *
     * @param  CreateMasterUnitRequest  $request
     * @return JsonResponse
     */
    public function store(CreateMasterUnitRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            $unit = $this->masterUnitService->create($request->validated(), $userId);

            return $this->successResponse($unit, 'Master unit berhasil dibuat.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified master unit.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $unit = $this->masterUnitService->getById($id);

        if (!$unit) {
            return $this->errorResponse('Master unit tidak ditemukan.', null, 404);
        }

        return $this->successResponse($unit, 'Detail master unit berhasil diambil.');
    }

    /**
     * Update the specified master unit.
     *
     * @param  UpdateMasterUnitRequest  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(UpdateMasterUnitRequest $request, int $id): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            $unit = $this->masterUnitService->update($id, $request->validated(), $userId);

            return $this->successResponse($unit, 'Master unit berhasil diperbarui.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Master unit tidak ditemukan.', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified master unit.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            $this->masterUnitService->delete($id, $userId);

            return $this->successResponse(null, 'Master unit berhasil dihapus.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Master unit tidak ditemukan.', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }
}
