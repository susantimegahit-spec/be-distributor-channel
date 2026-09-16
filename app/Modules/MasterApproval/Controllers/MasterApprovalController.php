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

        return $this->successResponse($masterApprovals, 'Master approval list retrieved successfully.');
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
        $forceRefresh = $request->has('refresh') ? $request->boolean('refresh') : true;

        try {
            $stages = $this->masterApprovalService->getStagesFromSap($payload, $userId, $forceRefresh);
            $message = empty($stages) ? 'Data not found.' : 'Approval stages retrieved successfully from SAP.';

            return $this->successResponse($stages, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve approval stages from SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get originators list from SAP API (/api/GetOriginator).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getOriginators(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $payload = $request->except(['refresh', 'force_refresh']);
        $forceRefresh = $request->has('refresh') ? $request->boolean('refresh') : true;

        try {
            $originators = $this->masterApprovalService->getOriginatorsFromSap($payload, $userId, $forceRefresh);
            $message = empty($originators) ? 'Data not found.' : 'Originators list retrieved successfully from SAP.';

            return $this->successResponse($originators, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve originators from SAP: ' . $e->getMessage(), [], 500);
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
        $forceRefresh = $request->has('refresh') ? $request->boolean('refresh') : true;

        try {
            $approvals = $this->masterApprovalService->getApprovalsFromSap($payload, $userId, $forceRefresh);
            $message = empty($approvals) ? 'Data not found.' : 'Approval list retrieved successfully from SAP.';

            return $this->successResponse($approvals, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve approval list from SAP: ' . $e->getMessage(), [], 500);
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
            'approvalRequestCode.required' => 'The approvalRequestCode (WddCode) field is required.',
            'Username.required'            => 'The Username field is required.',
            'Password.required'            => 'The Password field is required.',
            'Status.required'              => 'The approval Status field is required (Y or N).',
            'Status.in'                    => 'The Status field must be either Y (Approve) or N (Reject).',
            'Remarks.required_if'          => 'The Remarks field is required when status is N (Reject).',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        try {
            $result = $this->masterApprovalService->processApprovalSap($input, $userId);
            $actionLabel = ($input['Status'] === 'Y') ? 'approved' : 'rejected';

            return $this->successResponse($result, "Approval document successfully {$actionLabel} in SAP.");
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process approval in SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get owner / my document approval list from SAP API (/api/GetListByOwnerId).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getOwnerDocuments(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $payload = $request->all();
        $forceRefresh = $request->has('refresh') ? $request->boolean('refresh') : true;

        try {
            $data = $this->masterApprovalService->getOwnerDocumentsFromSap($payload, $userId, $forceRefresh);
            $message = empty($data) ? 'Data not found.' : 'Owner document approval list retrieved successfully.';

            return $this->successResponse($data, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve owner document approval list from SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get document approval detail by object code from SAP API (/api/GetDetailByObjectCode).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDocumentDetail(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $payload = $request->all();
        $forceRefresh = $request->has('refresh') ? $request->boolean('refresh') : true;

        // Validation for CustomQuery / doc_entry
        $customQuery = $payload['CustomQuery'] ?? $payload['custom_query'] ?? $payload['doc_entry'] ?? $payload['DocEntry'] ?? $payload['id'] ?? null;
        if (empty($customQuery)) {
            return $this->errorResponse('CustomQuery or doc_entry parameter is required.', [], 422);
        }

        try {
            $data = $this->masterApprovalService->getDocumentDetailFromSap($payload, $userId, $forceRefresh);
            $isEmpty = empty($data['header']) && empty($data['items']);
            $message = $isEmpty ? 'Data not found.' : 'Document approval detail retrieved successfully.';

            return $this->successResponse($data, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve document approval detail from SAP: ' . $e->getMessage(), [], 500);
        }
    }
}
