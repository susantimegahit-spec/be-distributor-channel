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
        $payload = $request->except(['refresh', 'force_refresh']);
        $forceRefresh = $request->boolean('refresh') || $request->boolean('force_refresh');

        try {
            $stages = $this->masterApprovalService->getStagesFromSap($payload, $userId, $forceRefresh);
            $message = empty($stages) ? 'Data approval stage SAP tidak ditemukan.' : 'Data approval stages berhasil diambil dari SAP.';

            return $this->successResponse($stages, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil approval stages dari SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get approval list from SAP API (/api/getapproval).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getApprovals(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $payload = $request->except(['refresh', 'force_refresh']);
        $forceRefresh = $request->boolean('refresh') || $request->boolean('force_refresh');

        try {
            $approvals = $this->masterApprovalService->getApprovalsFromSap($payload, $userId, $forceRefresh);
            $message = empty($approvals) ? 'Data approval SAP tidak ditemukan.' : 'Data approval berhasil diambil dari SAP.';

            return $this->successResponse($approvals, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar approval dari SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Process approval or rejection in SAP API (/api/ApproveSAP).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function approveSap(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        // Normalize input keys
        $input = $request->all();
        if (!isset($input['approvalRequestCode']) || $input['approvalRequestCode'] === '') {
            $input['approvalRequestCode'] = $input['WddCode'] ?? $input['wdd_code'] ?? $input['approval_request_code'] ?? null;
        }
        if (!isset($input['Username']) || $input['Username'] === '') {
            $input['Username'] = $input['username'] ?? null;
        }
        if (!isset($input['Password']) || $input['Password'] === '') {
            $input['Password'] = $input['password'] ?? null;
        }
        if (!isset($input['Status']) || $input['Status'] === '') {
            $input['Status'] = $input['status'] ?? null;
        }
        if (isset($input['Status'])) {
            $input['Status'] = strtoupper(trim((string) $input['Status']));
        }
        if (!isset($input['Remarks'])) {
            $input['Remarks'] = $input['remarks'] ?? '';
        }

        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'approvalRequestCode' => ['required', 'string'],
            'Username'            => ['required', 'string'],
            'Password'            => ['required', 'string'],
            'Status'              => ['required', 'string', 'in:Y,N'],
            'Remarks'             => ['nullable', 'string', 'required_if:Status,N'],
        ], [
            'approvalRequestCode.required' => 'approvalRequestCode (WddCode) wajib diisi.',
            'Username.required'            => 'Username wajib diisi.',
            'Password.required'            => 'Password wajib diisi.',
            'Status.required'              => 'Status approval wajib diisi (Y atau N).',
            'Status.in'                    => 'Status hanya menerima nilai Y (Approve) atau N (Reject).',
            'Remarks.required_if'          => 'Remarks wajib diisi jika status N (Reject).',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        try {
            $result = $this->masterApprovalService->processApprovalSap($input, $userId);
            $actionLabel = ($input['Status'] === 'Y') ? 'disetujui (Approve)' : 'ditolak (Reject)';

            return $this->successResponse($result, "Dokumen approval berhasil {$actionLabel} di SAP.");
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memproses approval di SAP: ' . $e->getMessage(), [], 500);
        }
    }
}
