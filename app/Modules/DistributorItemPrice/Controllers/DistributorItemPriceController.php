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
     * Check if user is internal admin.
     *
     * @param  Request  $request
     * @return bool
     */
    protected function isAdminUser(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        $roleName = strtolower($user->role?->name ?? '');
        $adminRoles = ['administrator', 'admin finance', 'admin sales', 'admin logistic', 'super admin', 'superadmin'];

        return in_array($roleName, $adminRoles, true);
    }

    /**
     * Display a listing of distributor item prices.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'code_customer', 'item_code']);

        $isAdmin = $this->isAdminUser($request);

        // Scope to distributor's customer code(s) if not internal admin
        if (!$isAdmin && $user && $user->code_customer) {
            $allowedCodes = array_filter(array_map('trim', explode(',', $user->code_customer)));

            if (!empty($filters['code_customer'])) {
                $requested = is_array($filters['code_customer']) ? $filters['code_customer'] : [$filters['code_customer']];
                $intersect = array_values(array_intersect($requested, $allowedCodes));
                // Only allow querying codes that the distributor has access to
                $filters['code_customer'] = !empty($intersect) ? $intersect : $allowedCodes;
            } else {
                $filters['code_customer'] = $allowedCodes;
            }
        } elseif (!empty($filters['code_customer'])) {
            $filters['code_customer'] = is_array($filters['code_customer']) ? $filters['code_customer'] : [$filters['code_customer']];
        }

        $prices = $this->service->getAll($filters);

        return $this->successResponse($prices, 'Daftar harga item distributor berhasil diambil.');
    }

    /**
     * Display the specified distributor item price.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $price = $this->service->getById($id);

        if (!$price) {
            return $this->errorResponse('Data harga distributor tidak ditemukan.', 404);
        }

        $user = $request->user();
        $isAdmin = $this->isAdminUser($request);

        // Distributor can only view their own item prices
        if (!$isAdmin && $user && $user->code_customer) {
            $allowedCodes = array_filter(array_map('trim', explode(',', $user->code_customer)));
            if (!in_array($price->code_customer, $allowedCodes, true)) {
                return $this->errorResponse('Data harga distributor tidak ditemukan.', 404);
            }
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
        if (!$this->isAdminUser($request)) {
            return $this->errorResponse('Anda tidak memiliki hak akses untuk menambah master harga.', 403);
        }

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
        if (!$this->isAdminUser($request)) {
            return $this->errorResponse('Anda tidak memiliki hak akses untuk mengubah master harga.', 403);
        }

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
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$this->isAdminUser($request)) {
            return $this->errorResponse('Anda tidak memiliki hak akses untuk menghapus master harga.', 403);
        }

        try {
            $this->service->delete($id);
            return $this->successResponse(null, 'Harga item distributor berhasil dihapus.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
