<?php

namespace App\Modules\DocumentApproval\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DocumentApproval;
use App\Modules\DocumentApproval\Resources\ApprovalDetailResource;
use App\Modules\DocumentApproval\Services\ApprovalActionService;
use App\Modules\DocumentApproval\Services\ApprovalDetailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentApprovalController extends Controller
{
    protected ApprovalDetailService $detailService;
    protected ApprovalActionService $actionService;

    public function __construct(
        ApprovalDetailService $detailService,
        ApprovalActionService $actionService
    ) {
        $this->detailService = $detailService;
        $this->actionService = $actionService;
    }

    /**
     * Get list of approvals with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DocumentApproval::with(['documentType', 'requester'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->status));
        }

        if ($request->filled('type_code')) {
            $query->whereHas('documentType', function ($q) use ($request) {
                $q->where('code', strtoupper($request->type_code));
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sap_doc_num', 'like', "%{$search}%")
                  ->orWhere('requester_name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        $approvals = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Approvals retrieved successfully',
            'data' => $approvals->items(),
            'pagination' => [
                'current_page' => $approvals->currentPage(),
                'last_page' => $approvals->lastPage(),
                'per_page' => $approvals->perPage(),
                'total' => $approvals->total(),
            ]
        ]);
    }

    /**
     * Get single dynamic approval detail (Rendered DTO).
     */
    public function show(int $id): JsonResponse
    {
        try {
            $detail = $this->detailService->getDetail($id);
            return response()->json([
                'success' => true,
                'message' => 'Document detail rendered successfully',
                'data' => new ApprovalDetailResource($detail),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Approve a document.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $approval = $this->actionService->approve($id, $request->user(), $request->input('notes'));
            return response()->json([
                'success' => true,
                'message' => 'Document approved successfully',
                'data' => $approval,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject a document.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $approval = $this->actionService->reject($id, $request->user(), $request->input('reason'));
            return response()->json([
                'success' => true,
                'message' => 'Document rejected',
                'data' => $approval,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Revise / Return a document.
     */
    public function revise(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => 'required|string|max:500',
        ]);

        try {
            $approval = $this->actionService->revise($id, $request->user(), $request->input('notes'));
            return response()->json([
                'success' => true,
                'message' => 'Document returned for revision',
                'data' => $approval,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Preview un-submitted SAP document by Type Code and DocEntry.
     */
    public function preview(string $typeCode, int $docEntry): JsonResponse
    {
        try {
            $detail = $this->detailService->previewByDocEntry($typeCode, $docEntry);
            return response()->json([
                'success' => true,
                'message' => 'Document preview generated',
                'data' => new ApprovalDetailResource($detail),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
