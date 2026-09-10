<?php

namespace App\Modules\DistributorItemPrice\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DistributorItemPrice\Services\DistributorItemPriceService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistributorItemPriceController extends Controller
{
    use ApiResponseFormatter;

    protected DistributorItemPriceService $service;

    /**
     * DistributorItemPriceController constructor.
     *
     * @param  DistributorItemPriceService  $service
     */
    public function __construct(DistributorItemPriceService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of distributor item prices.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        $prices = $this->service->getAll($filters);

        return $this->successResponse($prices, 'Daftar harga item distributor berhasil diambil.');
    }

    /**
     * Display the specified distributor item price.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $price = $this->service->getById($id);

        if (!$price) {
            return $this->errorResponse('Data harga distributor tidak ditemukan.', 404);
        }

        return $this->successResponse($price, 'Detail harga item distributor berhasil diambil.');
    }

    /**
     * Store a newly created distributor item price.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'code_customer' => 'required|string|exists:distributors,code_customer',
            'item_code' => 'required|string|exists:items,item_code',
            'price' => 'required|numeric|min:0',
            'status' => 'nullable|integer',
        ]);

        $userId = $request->user()->id;

        try {
            $price = $this->service->create($request->all(), $userId);
            return $this->successResponse($price, 'Harga item distributor berhasil ditambahkan.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified distributor item price.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'code_customer' => 'nullable|string|exists:distributors,code_customer',
            'item_code' => 'nullable|string|exists:items,item_code',
            'price' => 'nullable|numeric|min:0',
            'status' => 'nullable|integer',
        ]);

        $userId = $request->user()->id;

        try {
            $price = $this->service->update($id, $request->all(), $userId);
            return $this->successResponse($price, 'Harga item distributor berhasil diperbarui.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified distributor item price.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->delete($id);
            return $this->successResponse(null, 'Harga item distributor berhasil dihapus.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
