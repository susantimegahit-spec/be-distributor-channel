<?php

namespace App\Modules\MasterApproval\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MasterApproval;
use App\Modules\MasterApproval\Services\MasterApprovalService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterApprovalController extends Controller
{
    use ApiResponseFormatter;

    protected MasterApprovalService $masterApprovalService;

    /**
     * MasterApprovalController constructor.
     *
     * @param MasterApprovalService $masterApprovalService
     */
    public function __construct(MasterApprovalService $masterApprovalService)
    {
        $this->masterApprovalService = $masterApprovalService;
    }

    /**
     * Display a listing of master approvals.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $masterApprovals = MasterApproval::orderBy('id', 'asc')->get();

        return $this->successResponse($masterApprovals, 'Daftar master approval berhasil diambil.');
    }

    /**
     * Get approval stages from SAP API (/api/getstages).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStages(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $payload = $request->all();

        try {
            $stages = $this->masterApprovalService->getStagesFromSap($payload, $userId);
            $message = empty($stages) ? 'Data approval stage SAP tidak ditemukan.' : 'Data approval stages berhasil diambil dari SAP.';

            return $this->successResponse($stages, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil approval stages dari SAP: ' . $e->getMessage(), [], 500);
        }
    }
}
