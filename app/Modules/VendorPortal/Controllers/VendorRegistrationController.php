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

        $payload = array_merge($request->all(), $request->validated());
        $vendor = $this->registrationService->register($payload, $request->allFiles());

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
                'uploaded_documents' => $vendor->documents->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'document_type' => $doc->document_type,
                        'file_name' => $doc->file_name,
                        'file_size' => $doc->file_size,
                        'verification_status' => $doc->verification_status,
                    ];
                }),
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
        $authUser = auth('sanctum')->user();
        $vendorCodeInput = $request->input('vendor_code');

        if (!$vendorCodeInput && $authUser && $authUser->vendor) {
            $vendorCodeInput = $authUser->vendor->vendor_code;
        }

        $rules = [
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notes' => 'nullable|string|max:1000',
            'document_number' => 'nullable|string|max:100',
        ];

        if (!$authUser) {
            $rules['vendor_code'] = 'required|string';
        }

        $request->validate($rules, [
            'vendor_code.required' => 'Vendor code is required when unauthenticated.',
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

        if ($authUser && $authUser->vendor_id) {
            if ($document->vendor_id !== $authUser->vendor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized document access.',
                ], 403);
            }
        } elseif (!$document->vendor || strtoupper(trim($document->vendor->vendor_code)) !== strtoupper(trim((string) $vendorCodeInput))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid vendor code or unauthorized document access.',
            ], 403);
        }

        if ($document->vendor->registration_status === 'APPROVED' && $document->verification_status !== 'NEEDS_REVISION') {
            return response()->json([
                'success' => false,
                'message' => 'This document has already been verified and approved. Document re-upload is only allowed when revision is requested.',
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

    /**
     * Get list of downloadable vendor document templates.
     */
    public function listTemplates(Request $request): JsonResponse
    {
        $templates = [
            [
                'slug' => 'pakta-integritas',
                'title' => 'Pakta Integritas Vendor Ekspedisi',
                'filename' => 'PAKTA INTEGRITAS VENDOR EKSPEDISI - A4.docx',
                'format' => 'docx',
                'description' => 'Template resmi Pakta Integritas bermeterai untuk calon mitra ekspedisi.',
            ],
            [
                'slug' => 'peraturan-kerjasama',
                'title' => 'Peraturan Kerjasama Ekspedisi',
                'filename' => 'PERATURAN KERJASAMA EKSPEDISI.docx',
                'format' => 'docx',
                'description' => 'Dokumen panduan regulasi & SOP kerjasama operasional armada ekspedisi PT Susanti Megah.',
            ],
        ];

        $items = array_map(function ($tpl) {
            $filePath = $this->resolveTemplatePath($tpl['filename']);
            $isAvailable = $filePath && file_exists($filePath);

            return [
                'slug' => $tpl['slug'],
                'title' => $tpl['title'],
                'filename' => $tpl['filename'],
                'format' => $tpl['format'],
                'description' => $tpl['description'],
                'is_available' => $isAvailable,
                'file_size' => $isAvailable ? filesize($filePath) : null,
                'download_url' => url("/api/distributor-channel/v1/vendor-portal/templates/{$tpl['slug']}"),
                'static_url' => url("/templates/vendor/" . rawurlencode($tpl['filename'])),
            ];
        }, $templates);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Vendor document templates retrieved successfully.',
            'data' => $items,
        ]);
    }

    /**
     * Download specific vendor template by slug or filename.
     */
    public function downloadTemplate(string $slug)
    {
        $map = [
            'pakta-integritas' => 'PAKTA INTEGRITAS VENDOR EKSPEDISI - A4.docx',
            'pakta_integritas' => 'PAKTA INTEGRITAS VENDOR EKSPEDISI - A4.docx',
            'pakta' => 'PAKTA INTEGRITAS VENDOR EKSPEDISI - A4.docx',
            'peraturan-kerjasama' => 'PERATURAN KERJASAMA EKSPEDISI.docx',
            'peraturan_kerjasama' => 'PERATURAN KERJASAMA EKSPEDISI.docx',
            'pks' => 'PERATURAN KERJASAMA EKSPEDISI.docx',
        ];

        $filename = $map[strtolower(trim($slug))] ?? $slug;

        // Prevent directory traversal
        $filename = basename($filename);

        $filePath = $this->resolveTemplatePath($filename);

        if (!$filePath || !file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'status_code' => 404,
                'message' => "Template file '{$filename}' is not yet uploaded on the server.",
                'errors' => (object) [],
            ], 404);
        }

        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Resolve template file path from public or storage folder.
     */
    protected function resolveTemplatePath(string $filename): ?string
    {
        // 1. Check in public/templates/vendor/
        $publicPath = public_path("templates/vendor/{$filename}");
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        // 2. Check in storage/app/public/templates/vendor/
        $storagePath = storage_path("app/public/templates/vendor/{$filename}");
        if (file_exists($storagePath)) {
            return $storagePath;
        }

        return null;
    }
}

