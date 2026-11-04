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
                'company_npwp' => $data['company_npwp'] ?? null,
                'nik' => $data['nik'] ?? null,
                'address' => $data['address'] ?? null,
                'village' => $data['village'] ?? ($data['desa'] ?? null),
                'district' => $data['district'] ?? ($data['kecamatan'] ?? null),
                'city' => $data['city'] ?? ($data['kota'] ?? null),
                'regencies' => $data['regencies'] ?? ($data['regency'] ?? ($data['kabupaten'] ?? null)),
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
            $processedFiles = [];

            // 1. Format Array Dinamis dari FE:
            // documents[0][document_type] = 'akta', documents[0][file] = binary, documents[0][notes] = '...'
            $documentsInput = $data['documents'] ?? [];
            if (is_string($documentsInput)) {
                $documentsInput = json_decode($documentsInput, true) ?: [];
            }
            $documentsFiles = $files['documents'] ?? [];

            if (is_array($documentsInput) || is_array($documentsFiles)) {
                $allIndices = array_unique(array_merge(
                    is_array($documentsInput) ? array_keys($documentsInput) : [],
                    is_array($documentsFiles) ? array_keys($documentsFiles) : []
                ));

                foreach ($allIndices as $idx) {
                    $meta = is_array($documentsInput) && isset($documentsInput[$idx]) ? $documentsInput[$idx] : [];
                    $fileEntry = is_array($documentsFiles) && isset($documentsFiles[$idx]) ? $documentsFiles[$idx] : null;

                    $uploadedFile = null;
                    if ($fileEntry instanceof \Illuminate\Http\UploadedFile) {
                        $uploadedFile = $fileEntry;
                    } elseif (is_array($fileEntry) && isset($fileEntry['file']) && $fileEntry['file'] instanceof \Illuminate\Http\UploadedFile) {
                        $uploadedFile = $fileEntry['file'];
                    } elseif (isset($meta['file']) && $meta['file'] instanceof \Illuminate\Http\UploadedFile) {
                        $uploadedFile = $meta['file'];
                    }

                    if ($uploadedFile && $uploadedFile->isValid()) {
                        $rawType = $meta['document_type'] ?? (is_string($idx) && !is_numeric($idx) ? $idx : 'OTHER');
                        $docType = strtoupper(trim((string) $rawType));
                        if (!$docType) {
                            $docType = 'OTHER';
                        }

                        $extension = $uploadedFile->getClientOriginalExtension() ?: $uploadedFile->guessExtension() ?: 'bin';
                        $safeFileName = sprintf('%s_%s_%s.%s', strtolower($docType), $vendorCode, Str::random(6), $extension);
                        $path = $uploadedFile->storeAs("vendor_documents/{$vendorCode}", $safeFileName, 'public');

                        VendorDocument::create([
                            'vendor_id' => $vendor->id,
                            'document_type' => $docType,
                            'document_number' => $meta['document_number'] ?? null,
                            'file_path' => $path,
                            'file_name' => $uploadedFile->getClientOriginalName(),
                            'file_size' => $uploadedFile->getSize(),
                            'file_mime' => $uploadedFile->getMimeType(),
                            'verification_status' => 'PENDING',
                            'notes' => $meta['notes'] ?? null,
                        ]);

                        $processedFiles[] = spl_object_hash($uploadedFile);
                    }
                }
            }

            // 2. Format Flat File Uploads (misal: $files['akta'], $files['sk_akta_pendirian'], dll)
            foreach ($files as $fileKey => $file) {
                if ($fileKey === 'documents') {
                    continue;
                }

                if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                    if (in_array(spl_object_hash($file), $processedFiles)) {
                        continue;
                    }

                    $cleanKey = preg_replace('/^document_/', '', $fileKey);
                    $docType = strtoupper(trim($cleanKey));

                    $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
                    $safeFileName = sprintf('%s_%s_%s.%s', strtolower($docType), $vendorCode, Str::random(6), $extension);
                    $path = $file->storeAs("vendor_documents/{$vendorCode}", $safeFileName, 'public');

                    VendorDocument::create([
                        'vendor_id' => $vendor->id,
                        'document_type' => $docType,
                        'document_number' => $data["{$fileKey}_number"] ?? $data[strtolower($docType) . '_number'] ?? null,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'file_mime' => $file->getMimeType(),
                        'verification_status' => 'PENDING',
                        'notes' => $data["{$fileKey}_notes"] ?? $data[strtolower($docType) . '_notes'] ?? null,
                    ]);

                    $processedFiles[] = spl_object_hash($file);
                }
            }

            // Catat Riwayat Approval Awal
            VendorApprovalHistory::create([
                'vendor_id' => $vendor->id,
                'action' => 'REGISTRATION_SUBMITTED',
                'from_status' => 'DRAFT',
                'to_status' => 'PENDING_LEGAL_APPROVAL',
                'actor_id' => null,
                'actor_name' => $vendor->pic_name . ' (Prospective Partner)',
                'notes' => 'Vendor registration submitted successfully. Pending legal document verification.',
                'created_at' => Carbon::now(),
            ]);

            return $vendor->load('documents');
        });
    }

    /**
     * Mengunggah ulang berkas dokumen yang diminta revisi oleh tim legal.
     */
    public function reuploadDocument(VendorDocument $document, $file, ?string $notes = null, ?string $documentNumber = null): VendorDocument
    {
        $conn = config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
        return DB::connection($conn)->transaction(function () use ($document, $file, $notes, $documentNumber) {
            $vendor = $document->vendor;
            $vendorCode = $vendor->vendor_code;
            $docType = $document->document_type;

            // Hapus file lama jika ada di disk public
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $extension = $file->getClientOriginalExtension();
            $safeFileName = sprintf('%s_%s_%s.%s', strtolower($docType), $vendorCode, Str::random(6), $extension);
            $path = $file->storeAs("vendor_documents/{$vendorCode}", $safeFileName, 'public');

            $document->update([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_mime' => $file->getMimeType(),
                'document_number' => $documentNumber ?? $document->document_number,
                'verification_status' => 'PENDING',
                'notes' => $notes ?? $document->notes,
                'verified_by' => null,
                'verified_at' => null,
            ]);

            // Jika tidak ada lagi dokumen yang berstatus NEEDS_REVISION, kembalikan status vendor ke PENDING_LEGAL_APPROVAL
            $hasUnresolvedRevision = $vendor->documents()
                ->where('id', '!=', $document->id)
                ->where('verification_status', 'NEEDS_REVISION')
                ->exists();

            if (!$hasUnresolvedRevision && in_array($vendor->registration_status, ['REVISION_REQUIRED', 'PENDING_LEGAL_APPROVAL'])) {
                $vendor->update([
                    'registration_status' => 'PENDING_LEGAL_APPROVAL',
                    'legal_approval_status' => 'PENDING',
                ]);
            }

            // Catat Riwayat Audit Re-upload
            VendorApprovalHistory::create([
                'vendor_id' => $vendor->id,
                'action' => 'DOCUMENT_REUPLOADED',
                'from_status' => 'REVISION_REQUIRED',
                'to_status' => $vendor->registration_status,
                'actor_id' => null,
                'actor_name' => $vendor->pic_name . ' (Vendor Partner)',
                'notes' => "Document {$docType} re-uploaded." . ($notes ? " Notes: {$notes}" : ''),
                'created_at' => Carbon::now(),
            ]);

            return $document->fresh();
        });
    }
}
