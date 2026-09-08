<?php

namespace App\Modules\VendorPortal\Services;

use App\Modules\VendorPortal\Models\Vendor;
use App\Modules\VendorPortal\Models\VendorDocument;
use App\Modules\VendorPortal\Models\VendorApprovalHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VendorRegistrationService
{
    /**
     * Mendaftarkan vendor baru beserta unggahan dokumen legalitas.
     */
    public function register(array $data, array $files = []): Vendor
    {
        $conn = config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
        return DB::connection($conn)->transaction(function () use ($data, $files) {
            // Normalisasi tipe vendor
            $vendorType = strtoupper(trim($data['vendor_type'] ?? 'EXPEDITION'));

            // Generate Kode Vendor Otomatis: VND-YYYYMM-XXXX
            $datePrefix = Carbon::now()->format('Ym');
            $latestVendor = Vendor::where('vendor_code', 'LIKE', "VND-{$datePrefix}-%")
                ->orderBy('id', 'desc')
                ->first();

            $nextSequence = 1;
            if ($latestVendor && preg_match('/VND-\d{6}-(\d{4})/', $latestVendor->vendor_code, $matches)) {
                $nextSequence = (int)$matches[1] + 1;
            }
            $vendorCode = sprintf('VND-%s-%04d', $datePrefix, $nextSequence);

            // Simpan Profil Vendor
            $vendor = Vendor::create([
                'vendor_code' => $vendorCode,
                'vendor_type' => $vendorType,
                'company_name' => trim($data['company_name']),
                'company_email' => strtolower(trim($data['company_email'])),
                'company_phone' => $data['company_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'province' => $data['province'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'pic_name' => trim($data['pic_name']),
                'pic_phone' => trim($data['pic_phone']),
                'pic_email' => isset($data['pic_email']) ? strtolower(trim($data['pic_email'])) : null,
                'terms_agreed' => filter_var($data['terms_agreed'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'terms_agreed_at' => Carbon::now(),
                'registration_status' => 'PENDING_LEGAL_APPROVAL',
                'legal_approval_status' => 'PENDING',
            ]);

            // Simpan Dokumen Legalitas yang diunggah
            $documentKeys = [
                'akta' => 'AKTA',
                'document_akta' => 'AKTA',
                'nib' => 'NIB',
                'document_nib' => 'NIB',
                'npwp' => 'NPWP',
                'document_npwp' => 'NPWP',
                'support' => 'SUPPORT',
                'document_support' => 'SUPPORT',
            ];

            $processedDocTypes = [];

            foreach ($documentKeys as $fileKey => $docType) {
                if (isset($files[$fileKey]) && $files[$fileKey]->isValid() && !in_array($docType, $processedDocTypes)) {
                    $file = $files[$fileKey];
                    $extension = $file->getClientOriginalExtension();
                    $safeFileName = sprintf('%s_%s_%s.%s', strtolower($docType), $vendorCode, Str::random(6), $extension);
                    $path = $file->storeAs("vendor_documents/{$vendorCode}", $safeFileName, 'local');

                    VendorDocument::create([
                        'vendor_id' => $vendor->id,
                        'document_type' => $docType,
                        'document_number' => $data["{$fileKey}_number"] ?? $data[strtolower($docType) . '_number'] ?? null,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'file_mime' => $file->getMimeType(),
                        'verification_status' => 'PENDING',
                    ]);

                    $processedDocTypes[] = $docType;
                }
            }

            // Catat Riwayat Approval Awal
            VendorApprovalHistory::create([
                'vendor_id' => $vendor->id,
                'action' => 'REGISTRATION_SUBMITTED',
                'from_status' => 'DRAFT',
                'to_status' => 'PENDING_LEGAL_APPROVAL',
                'actor_id' => null,
                'actor_name' => $vendor->pic_name . ' (Calon Mitra)',
                'notes' => 'Pendaftaran vendor berhasil dikirim. Menunggu verifikasi dokumen oleh tim legal.',
                'created_at' => Carbon::now(),
            ]);

            return $vendor->load('documents');
        });
    }
}
