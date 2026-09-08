<?php

namespace App\Modules\VendorPortal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\VendorPortal\Requests\RegisterVendorRequest;
use App\Modules\VendorPortal\Services\VendorRegistrationService;
use App\Modules\VendorPortal\Models\Vendor;
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
                'message' => 'Email perusahaan ini sudah terdaftar dalam sistem vendor.',
                'errors' => [
                    'company_email' => ['Email perusahaan sudah digunakan oleh vendor dengan kode ' . $existing->vendor_code],
                ],
            ], 422);
        }

        $vendor = $this->registrationService->register($request->validated(), $request->allFiles());

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran vendor berhasil dikirim. Menunggu verifikasi dokumen legalitas dari tim legal PT Susanti Megah.',
            'data' => [
                'vendor_code' => $vendor->vendor_code,
                'company_name' => $vendor->company_name,
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
            return response()->json(['available' => false, 'message' => 'Email parameter is required'], 400);
        }

        $exists = Vendor::where('company_email', $email)->exists();

        return response()->json([
            'available' => !$exists,
            'email' => $email,
            'message' => $exists ? 'Email sudah terdaftar' : 'Email tersedia',
        ]);
    }
}
