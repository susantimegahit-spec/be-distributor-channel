<?php

namespace App\Modules\MasterApproval\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MasterApproval;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;

class MasterApprovalController extends Controller
{
    use ApiResponseFormatter;

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
}
