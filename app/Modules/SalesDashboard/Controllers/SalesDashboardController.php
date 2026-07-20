<?php

namespace App\Modules\SalesDashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SalesDashboard\Requests\UploadSalesDashboardRequest;
use App\Modules\SalesDashboard\Services\SalesDashboardService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalesDashboardController extends Controller
{
    use ApiResponseFormatter;

    protected SalesDashboardService $service;

    public function __construct(SalesDashboardService $service)
    {
        $this->service = $service;
    }

    /**
     * Upload Target or CMO Excel/CSV file.
     *
     * @param  UploadSalesDashboardRequest  $request
     * @return JsonResponse
     */
    public function upload(UploadSalesDashboardRequest $request): JsonResponse
    {
        try {
            $result = $this->service->handleUpload(
                $request->file('file'),
                $request->input('type')
            );
            return $this->successResponse($result, 'File berhasil diproses.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi file gagal.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get paginated raw sales dashboard data list.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['customer_code', 'brand', 'month', 'year', 'search']);

        // Security restriction: if distributor, force customer_code to their code_customer
        if ($user->code_customer) {
            $filters['customer_code'] = $user->code_customer;
        }

        $data = $this->service->getRawData($filters);
        return $this->successResponse($data, 'Data list dashboard berhasil diambil.');
    }

    /**
     * Delete a single record.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->service->deleteRecord($id);
        if ($deleted) {
            return $this->successResponse(null, 'Record berhasil dihapus.');
        }
        return $this->errorResponse('Record tidak ditemukan.', [], 404);
    }

    /**
     * Bulk delete / reset amounts to 0 for a specific type and month/year.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:target,cmo',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2099',
            'customer_code' => 'nullable|string',
        ]);

        $user = $request->user();
        $type = $request->input('type');
        $month = (int)$request->input('month');
        $year = (int)$request->input('year');
        $customerCode = $request->input('customer_code');

        if ($user->code_customer) {
            $customerCode = $user->code_customer;
        }

        $count = $this->service->bulkReset($type, $month, $year, $customerCode);
        return $this->successResponse(['count' => $count], "Berhasil mereset data {$type} untuk periode {$month}/{$year}.");
    }

    /**
     * Sync local PO/SO actual amounts and DO actual amounts (from SAP).
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function syncActuals(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2099',
            'customer_code' => 'nullable|string',
        ]);

        $user = $request->user();
        $month = (int)$request->input('month');
        $year = (int)$request->input('year');
        $customerCode = $request->input('customer_code');

        if ($user->code_customer) {
            $customerCode = $user->code_customer;
        }

        try {
            $result = $this->service->syncActuals($month, $year, $customerCode);
            return $this->successResponse($result, 'Pencocokan data PO/SO dan DO dari SAP berhasil disinkronkan.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal sinkronisasi data actual: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Sinkronisasi data CMO, SO, dan DO dari SAP ke tabel sales_dashboard_data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        $tahun = $request->input('Tahun') ?? $request->input('year');
        $cardCode = $request->input('CardCode') ?? $request->input('customer_code') ?? $request->input('code_customer');
        $brandInput = $request->input('Brand') ?? $request->input('brand') ?? $request->input('brands');

        if (!$tahun || !$cardCode || !$brandInput) {
            return $this->errorResponse('Parameter Tahun, CardCode/customer_code, dan Brand wajib diisi.', [], 422);
        }

        $brands = is_array($brandInput) ? $brandInput : array_map('trim', explode(',', $brandInput));
        $brands = array_map('strtoupper', $brands);

        try {
            // Sementara gunakan customer code C110001252 sebagai override sesuai request
            $result = $this->service->syncDashboardData(
                (int)$tahun,
                (string)$cardCode,
                $brands,
                'C110001252'
            );
            return $this->successResponse($result, 'Data Sales Dashboard berhasil disinkronkan.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal sinkronisasi data dashboard: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get comparison dashboard data (Target vs CMO vs PO/SO vs DO).
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function comparison(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
            'month' => 'nullable|integer|min:1|max:12',
            'customer_code' => 'nullable|string',
            'brand' => 'nullable|string',
            'brands' => 'nullable|string',
        ]);

        $user = $request->user();
        $year = (int)$request->query('year');
        $month = $request->query('month') ? (int)$request->query('month') : null;
        
        $brandInput = $request->query('brands') ?? $request->query('brand');
        $brands = null;
        if ($brandInput) {
            $brands = is_array($brandInput) ? $brandInput : array_map('trim', explode(',', $brandInput));
            $brands = array_map('strtoupper', $brands);
        }

        $filters = array_filter([
            'customer_code' => $request->query('customer_code'),
            'month' => $month,
            'brands' => $brands,
        ]);

        if ($user->code_customer) {
            $filters['customer_code'] = $user->code_customer;
        }

        try {
            $data = $this->service->getComparison($year, $filters);
            return $this->successResponse($data, 'Data perbandingan dashboard berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data dashboard: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update a single dashboard record by ID.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'target_amount' => 'sometimes|numeric|min:0',
            'cmo_amount' => 'sometimes|numeric|min:0',
            'so_amount' => 'sometimes|numeric|min:0',
            'do_amount' => 'sometimes|numeric|min:0',
            'customer_name' => 'sometimes|string|max:255',
            'depo' => 'sometimes|nullable|string|max:100',
            'brand' => 'sometimes|string|max:50',
        ]);

        $updated = $this->service->updateRecord($id, $payload);
        if ($updated) {
            return $this->successResponse($updated, 'Data dashboard berhasil diperbarui.');
        }
        return $this->errorResponse('Record tidak ditemukan.', [], 404);
    }
}
