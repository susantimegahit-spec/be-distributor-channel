<?php

namespace App\Modules\VendorPortal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\VendorPortal\Requests\RegisterVendorRequest;
use App\Modules\VendorPortal\Services\VendorRegistrationService;
use App\Modules\VendorPortal\Models\Vendor;
use App\Modules\VendorPortal\Models\VendorDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorRegistrationController extends Controller
{
    protected VendorRegistrationService $registrationService;

    public function __construct(VendorRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    /**
     * Endpoint publik pendaftaran mitra vendor (Ekspedisi / Distributor / dll).
     */
    public function register(RegisterVendorRequest $request): JsonResponse
    {
        // Cek apakah email sudah terdaftar
        $existing = Vendor::where('company_email', strtolower(trim($request->input('company_email'))))
            ->whereIn('registration_status', ['APPROVED', 'PENDING_LEGAL_APPROVAL'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Company email is already registered in the vendor system.',
                'errors' => [
                    'company_email' => ['Company email is already in use by vendor with code ' . $existing->vendor_code],
                ],
            ], 422);
        }

        $vendor = $this->registrationService->register($request->validated(), $request->allFiles());

        return response()->json([
            'success' => true,
            'message' => 'Vendor registration submitted successfully. Pending legal document verification by PT Susanti Megah legal team.',
            'data' => [
                'vendor_code' => $vendor->vendor_code,
                'company_name' => $vendor->company_name,
                'company_npwp' => $vendor->company_npwp,
                'vendor_type' => $vendor->vendor_type,
                'registration_status' => $vendor->registration_status,
                'uploaded_documents_count' => $vendor->documents->count(),
                'created_at' => $vendor->created_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * Endpoint pengecekan ketersediaan email perusahaan secara real-time di form FE.
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $email = strtolower(trim($request->query('email', '')));
        if (!$email) {
            return response()->json(['available' => false, 'message' => 'Email parameter is required.'], 400);
        }

        $exists = Vendor::where('company_email', $email)->exists();

        return response()->json([
            'available' => !$exists,
            'email' => $email,
            'message' => $exists ? 'Email is already registered.' : 'Email is available.',
        ]);
    }

    /**
     * Endpoint publik untuk mengunggah ulang dokumen revisi.
     */
    public function reuploadDocument(Request $request, $documentId): JsonResponse
    {
        $request->validate([
            'vendor_code' => 'required|string',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notes' => 'nullable|string|max:1000',
            'document_number' => 'nullable|string|max:100',
        ], [
            'vendor_code.required' => 'Vendor code is required.',
            'file.required' => 'Replacement document file is required.',
            'file.mimes' => 'Document file must be a PDF, JPG, JPEG, or PNG.',
            'file.max' => 'Document file size must not exceed 10MB.',
        ]);

        $document = VendorDocument::with('vendor')->find($documentId);
        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found.',
            ], 404);
        }

        if (!$document->vendor || strtoupper(trim($document->vendor->vendor_code)) !== strtoupper(trim($request->input('vendor_code')))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid vendor code or unauthorized document access.',
            ], 403);
        }

        if ($document->vendor->registration_status === 'APPROVED') {
            return response()->json([
                'success' => false,
                'message' => 'This vendor has already been approved. Document replacement is not allowed.',
            ], 422);
        }

        $reuploadedDoc = $this->registrationService->reuploadDocument(
            $document,
            $request->file('file'),
            $request->input('notes'),
            $request->input('document_number')
        );

        return response()->json([
            'success' => true,
            'message' => 'Document re-uploaded successfully. Pending legal document verification.',
            'data' => $reuploadedDoc,
        ]);
    }
}
