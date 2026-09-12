<?php

namespace App\Modules\VendorPortal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\VendorPortal\Models\Vendor;
use App\Modules\VendorPortal\Models\VendorDocument;
use App\Modules\VendorPortal\Requests\LegalApprovalRequest;
use App\Modules\VendorPortal\Requests\LegalRejectRequest;
use App\Modules\VendorPortal\Requests\LegalRevisionRequest;
use App\Modules\VendorPortal\Services\VendorLegalApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VendorLegalApprovalController extends Controller
{
    protected VendorLegalApprovalService $approvalService;

    public function __construct(VendorLegalApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * Menampilkan daftar permohonan registrasi vendor.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::with(['documents', 'users', 'legalApprover', 'expedition'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->input('status') !== 'ALL') {
            $query->where('registration_status', $request->input('status'));
        }

        if ($request->has('vendor_type') && $request->input('vendor_type') !== 'ALL') {
            $query->where('vendor_type', strtoupper($request->input('vendor_type')));
        }

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = '%' . $request->input('search') . '%';
            $likeOp = config('database.default') === 'sqlite' ? 'LIKE' : 'ILIKE';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('company_name', $likeOp, $search)
                  ->orWhere('vendor_code', $likeOp, $search)
                  ->orWhere('sap_vendor_code', $likeOp, $search)
                  ->orWhere('company_email', $likeOp, $search)
                  ->orWhere('company_npwp', $likeOp, $search)
                  ->orWhere('pic_name', $likeOp, $search);
            });
        }

        $perPage = (int)$request->input('per_page', 15);
        $vendors = $query->paginate($perPage);
        Vendor::preloadRegionNames($vendors->items());

        return response()->json([
            'success' => true,
            'data' => $vendors->items(),
            'meta' => [
                'current_page' => $vendors->currentPage(),
                'last_page' => $vendors->lastPage(),
                'per_page' => $vendors->perPage(),
                'total' => $vendors->total(),
            ],
        ]);
    }

    /**
     * Menampilkan detail lengkap vendor dan berkas legalitas.
     */
    public function show($id): JsonResponse
    {
        $vendor = Vendor::with(['documents', 'users', 'approvalHistories', 'legalApprover', 'expedition'])->find($id);

        if (!$vendor) {
            return response()->json(['success' => false, 'message' => 'Vendor not found.'], 404);
        }

        Vendor::preloadRegionNames([$vendor]);

        return response()->json([
            'success' => true,
            'data' => $vendor,
        ]);
    }

    /**
     * Approval legal pendaftaran vendor.
     */
    public function approve(LegalApprovalRequest $request, $id): JsonResponse
    {
        $vendor = Vendor::find($id);
        if (!$vendor) {
            return response()->json(['success' => false, 'message' => 'Vendor not found.'], 404);
        }

        if ($vendor->registration_status === 'APPROVED') {
            return response()->json([
                'success' => false,
                'message' => 'This vendor has already been approved.',
            ], 422);
        }

        try {
            $result = $this->approvalService->approve($vendor, $request->user(), $request->validated());
        } catch (\Throwable $e) {
            Log::error("Vendor legal approval failed for vendor {$vendor->id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vendor registration approved successfully. Login credentials have been generated and dispatched.',
            'data' => [
                'vendor_code' => $result['vendor']->vendor_code,
                'company_name' => $result['vendor']->company_name,
                'registration_status' => $result['vendor']->registration_status,
                'legal_approval_status' => $result['vendor']->legal_approval_status,
                'sap_vendor_code' => $result['vendor']->sap_vendor_code,
                'sap_sync' => $result['sap_sync'] ?? null,
                'credentials' => $result['generated_credentials'],
                'expedition_linked' => $result['expedition_linked'],
            ],
        ]);
    }

    /**
     * Penolakan pendaftaran vendor oleh tim legal.
     */
    public function reject(LegalRejectRequest $request, $id): JsonResponse
    {
        $vendor = Vendor::find($id);
        if (!$vendor) {
            return response()->json(['success' => false, 'message' => 'Vendor not found.'], 404);
        }

        $rejectedVendor = $this->approvalService->reject($vendor, $request->user(), $request->input('rejection_reason'));

        return response()->json([
            'success' => true,
            'message' => 'Vendor registration rejected successfully.',
            'data' => [
                'vendor_code' => $rejectedVendor->vendor_code,
                'registration_status' => $rejectedVendor->registration_status,
                'legal_notes' => $rejectedVendor->legal_notes,
            ],
        ]);
    }

    /**
     * Permintaan revisi dokumen ke vendor.
     */
    public function requestRevision(LegalRevisionRequest $request, $id): JsonResponse
    {
        $vendor = Vendor::find($id);
        if (!$vendor) {
            return response()->json(['success' => false, 'message' => 'Vendor not found.'], 404);
        }

        $revisedVendor = $this->approvalService->requestRevision(
            $vendor,
            $request->user(),
            $request->input('revision_notes'),
            $request->input('document_types', [])
        );

        return response()->json([
            'success' => true,
            'message' => 'Document revision request sent successfully to vendor.',
            'data' => [
                'vendor_code' => $revisedVendor->vendor_code,
                'registration_status' => $revisedVendor->registration_status,
                'legal_notes' => $revisedVendor->legal_notes,
            ],
        ]);
    }

    /**
     * Verifikasi status berkas dokumen tertentu (VALID, NEEDS_REVISION, INVALID).
     */
    public function verifyDocument(Request $request, $documentId): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:VALID,NEEDS_REVISION,INVALID',
            'notes' => 'nullable|string|max:1000',
        ], [
            'status.in' => 'Status must be VALID, NEEDS_REVISION, or INVALID.',
        ]);

        $document = VendorDocument::find($documentId);
        if (!$document) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }

        $verified = $this->approvalService->verifyDocument(
            $document,
            $request->user(),
            $request->input('status'),
            $request->input('notes')
        );

        return response()->json([
            'success' => true,
            'message' => 'Document verification status updated successfully.',
            'data' => $verified,
        ]);
    }

    /**
     * Preview berkas dokumen legalitas (Streaming inline PDF / Image).
     */
    public function previewDocument($documentId)
    {
        $document = VendorDocument::find($documentId);
        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => "Document with ID {$documentId} not found.",
            ], 404);
        }

        $cleanPath = ltrim($document->file_path, '/');
        $filePath = null;

        // Cek kandidat lokasi file pada disk storage server
        $candidatePaths = [
            storage_path('app/public/' . $cleanPath),
            storage_path('app/' . $cleanPath),
            public_path('storage/' . $cleanPath),
            public_path($cleanPath),
        ];

        foreach ($candidatePaths as $candidate) {
            if (file_exists($candidate) && is_file($candidate)) {
                $filePath = $candidate;
                break;
            }
        }

        if (!$filePath) {
            return response()->json([
                'success' => false,
                'message' => "Physical file not found on server storage for document ID {$documentId}.",
                'file_path' => $document->file_path,
                'file_url' => $document->file_url,
            ], 404);
        }

        $mime = $document->file_mime ?: (@mime_content_type($filePath) ?: 'application/octet-stream');
        $fileName = $document->file_name ?: basename($filePath);

        return response()->file($filePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
