<?php

namespace App\Modules\Budgeting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Budgeting\Requests\SaveMasterBudgetRequest;
use App\Modules\Budgeting\Services\MasterBudgetService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterBudgetController extends Controller
{
    use ApiResponseFormatter;

    protected MasterBudgetService $service;

    public function __construct(MasterBudgetService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'department',
            'cost_center',
            'budget_category',
            'period_year',
            'period_month',
            'status',
            'search',
            'paginate'
        ]);

        $perPage = (int)$request->query('per_page', 15);
        $data = $this->service->getList($filters, $perPage);

        return $this->successResponse($data, 'Data Master Budget berhasil diambil.');
    }

    public function store(SaveMasterBudgetRequest $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $budget = $this->service->create($request->validated(), $userId);

        return $this->successResponse($budget, 'Master Budget berhasil dibuat.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $budget = $this->service->getDetail($id);
        if (!$budget) {
            return $this->errorResponse('Master Budget tidak ditemukan.', [], 404);
        }

        return $this->successResponse($budget, 'Detail Master Budget berhasil diambil.');
    }

    public function update(SaveMasterBudgetRequest $request, int $id): JsonResponse
    {
        $userId = $request->user()?->id;
        $budget = $this->service->update($id, $request->validated(), $userId);

        if (!$budget) {
            return $this->errorResponse('Master Budget tidak ditemukan.', [], 404);
        }

        return $this->successResponse($budget, 'Master Budget berhasil diperbarui.');
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->service->delete($id);
        if (!$deleted) {
            return $this->errorResponse('Master Budget tidak ditemukan.', [], 404);
        }

        return $this->successResponse(null, 'Master Budget berhasil dihapus.');
    }
}
