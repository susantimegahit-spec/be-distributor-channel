<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Services\DashboardService;
use App\Traits\ApiResponseFormatter;
use App\Models\Distributor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponseFormatter;

    protected DashboardService $dashboardService;

    /**
     * DashboardController constructor.
     *
     * @param  DashboardService  $dashboardService
     */
    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get summary metrics for Admin.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function adminSummary(Request $request): JsonResponse
    {
        $user = $request->user();

        // Security check: only users without code_customer (admins/staff) can view admin summary
        if ($user->code_customer) {
            return $this->errorResponse('Akses ditolak. Anda tidak memiliki izin Admin.', null, 403);
        }

        $summary = $this->dashboardService->getAdminSummary();

        return $this->successResponse($summary, 'Statistik ringkasan admin berhasil diambil.');
    }

    /**
     * Get chart metrics for Admin.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function adminCharts(Request $request): JsonResponse
    {
        $user = $request->user();

        // Security check: only users without code_customer (admins/staff) can view admin charts
        if ($user->code_customer) {
            return $this->errorResponse('Akses ditolak. Anda tidak memiliki izin Admin.', null, 403);
        }

        $charts = $this->dashboardService->getAdminCharts();

        return $this->successResponse($charts, 'Statistik grafik admin berhasil diambil.');
    }

    /**
     * Get summary metrics for Distributor.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function distributorSummary(Request $request): JsonResponse
    {
        $user = $request->user();

        // Security check: only users with code_customer (distributors) can view distributor summary
        if (!$user->code_customer) {
            return $this->errorResponse('Akses ditolak. Anda bukan user Distributor.', null, 403);
        }

        $distributor = Distributor::where('code_customer', $user->code_customer)->first();
        if (!$distributor) {
            return $this->errorResponse('Data distributor Anda tidak ditemukan.', null, 404);
        }

        $summary = $this->dashboardService->getDistributorSummary($distributor->id, $user->code_customer);

        return $this->successResponse($summary, 'Statistik ringkasan distributor berhasil diambil.');
    }

    /**
     * Get chart metrics for Distributor.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function distributorCharts(Request $request): JsonResponse
    {
        $user = $request->user();

        // Security check: only users with code_customer (distributors) can view distributor charts
        if (!$user->code_customer) {
            return $this->errorResponse('Akses ditolak. Anda bukan user Distributor.', null, 403);
        }

        $distributor = Distributor::where('code_customer', $user->code_customer)->first();
        if (!$distributor) {
            return $this->errorResponse('Data distributor Anda tidak ditemukan.', null, 404);
        }

        $charts = $this->dashboardService->getDistributorCharts($distributor->id);

        return $this->successResponse($charts, 'Statistik grafik distributor berhasil diambil.');
    }
}
