<?php

namespace App\Modules\VendorPortal\Services;

use App\Modules\VendorPortal\Models\Vendor;
use App\Modules\VendorPortal\Models\VendorDocument;
use App\Modules\VendorPortal\Models\VendorUser;
use App\Modules\VendorPortal\Models\VendorApprovalHistory;
use App\Modules\VendorPortal\Models\VendorCredentialsDispatchLog;
use App\Models\User;
use App\Models\Expedition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Mail\VendorCredentialsMail;
use App\Mail\VendorRegistrationRejectedMail;
use Carbon\Carbon;

class VendorLegalApprovalService
{
    /**
     * Menyetujui pendaftaran vendor oleh tim legal:
     * 1. Update status vendor menjadi APPROVED
     * 2. Auto-generate akun user vendor di vendor_users
     * 3. Auto-sync ke master ekspedisi jika tipe vendor EXPEDITION
     * 4. Catat riwayat audit approval & log dispatch kredensial
     */
    public function approve(Vendor $vendor, ?User $legalUser = null, array $options = []): array
    {
        $conn = config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
        return DB::connection($conn)->transaction(function () use ($vendor, $legalUser, $options) {
            $now = Carbon::now();
            $actorId = $legalUser?->id;
            $actorName = $legalUser?->name ?? 'PT Susanti Megah Legal Team';
            $legalNotes = $options['legal_notes'] ?? 'Legal documents verified and approved.';

            // 1. Sinkronisasi data vendor ke SAP B1 (/api/addvendor)
            $sapSyncResult = $this->syncVendorToSap($vendor, $options, $legalUser);
            if (!$sapSyncResult['success'] || empty($sapSyncResult['card_code'])) {
                $errorMessage = $sapSyncResult['message'] ?: 'Failed to register vendor in SAP Business One.';
                throw new \RuntimeException("Failed to sync vendor to SAP: {$errorMessage}");
            }

            $sapCardCode = $sapSyncResult['card_code'];

            // 2. Update status vendor
            $vendor->update([
                'registration_status' => 'APPROVED',
                'legal_approval_status' => 'APPROVED',
                'legal_approved_by' => $actorId,
                'legal_approved_at' => $now,
                'legal_notes' => $legalNotes,
                'sap_vendor_code' => $sapCardCode,
            ]);
            $vendor->sap_vendor_code = $sapCardCode;

            // 3. Generate Kredensial Login Vendor
            $plainPassword = $options['initial_password'] ?? Str::password(10, true, true, false);

            $vendorUser = VendorUser::updateOrCreate(
                [
                    'vendor_id' => $vendor->id,
                    'email' => $vendor->company_email,
                ],
                [
                    'name' => $vendor->pic_name ?: $vendor->company_name,
                    'password' => Hash::make($plainPassword),
                    'role' => 'VENDOR_ADMIN',
                    'status' => 'ACTIVE',
                    'must_change_password' => true,
                    'initial_password_sent_at' => $now,
                ]
            );

            // 4. Auto-sync ke tabel ekspedisi.expeditions jika tipe vendor adalah EXPEDITION
            $expeditionCreated = null;
            if (strtoupper($vendor->vendor_type) === 'EXPEDITION') {
                $expeditionCreated = $this->syncToExpeditionMaster($vendor);
            }

            // 5. Kirim Email Kredensial Resmi ke Vendor & Catat Log Dispatch
            $loginUrl = url('/vendor-portal');
            $dispatchStatus = 'SENT';
            $dispatchError = null;

            try {
                Mail::to($vendor->company_email)
                    ->send(new VendorCredentialsMail($vendor, $vendorUser, $plainPassword, $loginUrl));
            } catch (\Throwable $mailException) {
                $dispatchStatus = 'FAILED';
                $dispatchError = $mailException->getMessage();
                Log::warning("Failed to dispatch vendor credentials email to {$vendor->company_email}: " . $mailException->getMessage());
            }

            VendorCredentialsDispatchLog::create([
                'vendor_user_id' => $vendorUser->id,
                'vendor_id' => $vendor->id,
                'recipient_email' => $vendor->company_email,
                'dispatch_channel' => 'EMAIL',
                'dispatch_status' => $dispatchStatus,
                'sent_at' => $now,
                'error_message' => $dispatchError,
            ]);

            // 6. Catat Audit Trail Approval
            $auditNotes = $legalNotes . ' Vendor login credentials generated successfully. SAP CardCode: ' . $sapCardCode . ' (Synced).';

            VendorApprovalHistory::create([
                'vendor_id' => $vendor->id,
                'action' => 'APPROVED',
                'from_status' => 'PENDING_LEGAL_APPROVAL',
                'to_status' => 'APPROVED',
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'notes' => $auditNotes,
                'created_at' => $now,
            ]);

            return [
                'vendor' => $vendor->fresh(['documents', 'users']),
                'user' => $vendorUser,
                'generated_credentials' => [
                    'email' => $vendorUser->email,
                    'initial_password' => $plainPassword,
                    'portal_login_url' => url('/vendor-portal'),
                ],
                'expedition_linked' => $expeditionCreated,
                'sap_sync' => [
                    'success'    => $sapSyncResult['success'],
                    'card_code'  => $sapSyncResult['card_code'],
                    'message'    => $sapSyncResult['message'],
                    'error_code' => $sapSyncResult['error_code'],
                ],
            ];
        });
    }

    /**
     * Menolak permohonan pendaftaran vendor oleh tim legal.
     */
    public function reject(Vendor $vendor, ?User $legalUser, string $rejectionReason): Vendor
    {
        $conn = config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
        return DB::connection($conn)->transaction(function () use ($vendor, $legalUser, $rejectionReason) {
            $now = Carbon::now();
            $actorId = $legalUser?->id;
            $actorName = $legalUser?->name ?? 'PT Susanti Megah Legal Team';

            $fromStatus = $vendor->registration_status;

            $vendor->update([
                'registration_status' => 'REJECTED',
                'legal_approval_status' => 'REJECTED',
                'legal_approved_by' => $actorId,
                'legal_approved_at' => $now,
                'legal_notes' => $rejectionReason,
            ]);

            VendorApprovalHistory::create([
                'vendor_id' => $vendor->id,
                'action' => 'REJECTED',
                'from_status' => $fromStatus,
                'to_status' => 'REJECTED',
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'notes' => 'Vendor registration rejected. Reason: ' . $rejectionReason,
                'created_at' => $now,
            ]);

            // Kirim Email Notifikasi Penolakan Resmi ke Vendor
            try {
                Mail::to($vendor->company_email)
                    ->send(new VendorRegistrationRejectedMail($vendor, $rejectionReason));
            } catch (\Throwable $mailException) {
                Log::warning("Failed to dispatch vendor rejection email to {$vendor->company_email}: " . $mailException->getMessage());
            }

            return $vendor->fresh();
        });
    }

    /**
     * Meminta revisi dokumen ke vendor.
     */
    public function requestRevision(Vendor $vendor, ?User $legalUser, string $revisionNotes, array $documentTypes = []): Vendor
    {
        $conn = config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
        return DB::connection($conn)->transaction(function () use ($vendor, $legalUser, $revisionNotes, $documentTypes) {
            $now = Carbon::now();
            $actorId = $legalUser?->id;
            $actorName = $legalUser?->name ?? 'PT Susanti Megah Legal Team';

            $fromStatus = $vendor->registration_status;

            $vendor->update([
                'registration_status' => 'REVISION_REQUIRED',
                'legal_approval_status' => 'REVISION',
                'legal_notes' => $revisionNotes,
            ]);

            // Update status dokumen yang perlu direvisi jika ditentukan
            if (!empty($documentTypes)) {
                $vendor->documents()->whereIn('document_type', $documentTypes)->update([
                    'verification_status' => 'NEEDS_REVISION',
                    'verification_notes' => $revisionNotes,
                    'notes' => $revisionNotes,
                ]);
            }

            VendorApprovalHistory::create([
                'vendor_id' => $vendor->id,
                'action' => 'REVISION_REQUESTED',
                'from_status' => $fromStatus,
                'to_status' => 'REVISION_REQUIRED',
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'notes' => 'Document revision requested by legal team: ' . $revisionNotes,
                'created_at' => $now,
            ]);

            return $vendor->fresh(['documents']);
        });
    }

    /**
     * Verifikasi status satu berkas dokumen tertentu (VALID, NEEDS_REVISION, INVALID).
     */
    public function verifyDocument(VendorDocument $document, ?User $legalUser, string $status, ?string $notes = null): VendorDocument
    {
        $conn = config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
        return DB::connection($conn)->transaction(function () use ($document, $legalUser, $status, $notes) {
            $now = Carbon::now();
            $actorId = $legalUser?->id;
            $actorName = $legalUser?->name ?? 'PT Susanti Megah Legal Team';

            $document->update([
                'verification_status' => strtoupper(trim($status)),
                'verified_by' => $actorId,
                'verified_at' => $now,
                'verification_notes' => $notes,
                'notes' => $notes,
            ]);

            // Catat riwayat audit approval untuk dokumen ini
            VendorApprovalHistory::create([
                'vendor_id' => $document->vendor_id,
                'action' => 'DOCUMENT_' . strtoupper(trim($status)),
                'from_status' => $document->vendor?->registration_status ?? 'IN_REVIEW',
                'to_status' => $document->vendor?->registration_status ?? 'IN_REVIEW',
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'notes' => "Document {$document->document_type} verified as {$status}." . ($notes ? " Notes: {$notes}" : ''),
                'created_at' => $now,
            ]);

            return $document->fresh();
        });
    }

    /**
     * Sinkronisasi data vendor ke tabel master ekspedisi.expeditions.
     */
    public function syncToExpeditionMaster(Vendor $vendor): ?Expedition
    {
        try {
            $ekspedisiConn = config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_ekspedisi';
            $hasTable = Schema::connection($ekspedisiConn)->hasTable('expeditions') 
                || Schema::connection($ekspedisiConn)->hasTable('ekspedisi.expeditions');
            if (!$hasTable) {
                return null;
            }

            $expeditionCode = 'EXP-' . strtoupper(Str::slug(substr($vendor->company_name, 0, 8), '')) . '-' . sprintf('%04d', $vendor->id);

            // Cek apakah sudah ada ekspedisi yang terhubung dengan vendor ini atau memiliki email/nama sama
            $expedition = Expedition::where('vendor_id', $vendor->id)
                ->orWhere(function ($q) use ($vendor) {
                    $q->where('email', $vendor->company_email)
                      ->orWhere('expedition_name', $vendor->company_name);
                })
                ->first();

            $payload = [
                'vendor_id' => $vendor->id,
                'expedition_name' => $vendor->company_name,
                'address' => $vendor->address,
                'city' => $vendor->city,
                'province' => $vendor->province,
                'postal_code' => $vendor->postal_code,
                'pic_name' => $vendor->pic_name,
                'pic_phone' => $vendor->pic_phone,
                'email' => $vendor->company_email,
                'npwp' => $vendor->company_npwp,
                'status' => 'ACTIVE',
            ];

            if ($expedition) {
                $expedition->update($payload);
            } else {
                $payload['expedition_code'] = $expeditionCode;
                $expedition = Expedition::create($payload);
            }

            if ($vendor->expedition_id !== $expedition->id) {
                $vendor->update(['expedition_id' => $expedition->id]);
            }

            return $expedition;
        } catch (\Throwable $e) {
            Log::warning("Auto-sync expedition failed for vendor {$vendor->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sinkronisasi data vendor ke SAP Business One API endpoint (/api/addvendor).
     */
    public function syncVendorToSap(Vendor $vendor, array $options = [], ?User $legalUser = null): array
    {
        if (!empty($options['skip_sap_sync'])) {
            $fallbackCode = $options['sap_vendor_code'] ?? ($vendor->sap_vendor_code ?: 'VN10001');
            return [
                'success'    => true,
                'card_code'  => $fallbackCode,
                'message'    => 'SAP sync skipped by option',
                'error_code' => 0,
                'payload'    => [],
                'raw'        => null,
            ];
        }

        $sapUrl = config('services.sap.url') ?: env('SAP_API_URL');
        if (empty($sapUrl)) {
            Log::error("Vendor legal approval failed for vendor {$vendor->id}: SAP_API_URL is not configured in .env.");

            return [
                'success'    => false,
                'card_code'  => null,
                'message'    => 'SAP API URL (SAP_API_URL) is not configured in .env.',
                'error_code' => -999,
                'payload'    => [],
                'raw'        => null,
            ];
        }

        $endpoint = rtrim($sapUrl, '/') . '/api/addvendor';

        $taxId = preg_replace('/[^0-9]/', '', (string) ($vendor->company_npwp ?? ''));
        if (empty($taxId)) {
            $taxId = $vendor->company_npwp ?: null;
        }

        $phone = $vendor->company_phone ?: ($vendor->pic_phone ?: null);
        $cellular = $vendor->pic_phone ?: ($vendor->company_phone ?: null);
        // Samakan Phone1 dan Cellular jika salah satu terisi
        $contactPhone = $cellular ?: $phone;

        // Hanya AddonId ("02") dan UserId (id user legal yang login) yang di-hardcode
        $addonId = '02';
        $userId = !empty($options['UserId'])
            ? (string) $options['UserId']
            : ((string) ($legalUser?->id ?? ''));

        $sapPayload = [
            'CardName'     => $vendor->company_name ?: null,
            'Phone1'       => $contactPhone ?: null,
            'Cellular'     => $contactPhone ?: null,
            'EmailAddress' => $vendor->company_email ?: null,
            'FederalTaxID' => $taxId ?: null,
            'AddonId'      => $addonId,
            'UserId'       => $userId,
            'NIK'          => $vendor->nik ?: '0000000000000000',
            'Street'       => $vendor->address ?: null,
            'City'         => $vendor->city ?: ($vendor->regencies ?: null),
            'ZipCode'      => $vendor->postal_code ?: null,
            'Country'      => $vendor->country ?? null,
            'FirstName'    => $vendor->pic_name ?: null,
            'PhoneNumber'  => $contactPhone ?: null,
        ];

        try {
            $response = Http::timeout(20)->post($endpoint, $sapPayload);
            $body = $response->json();
            $statusCode = $response->status();

            $message = (string) ($body['Message'] ?? $body['message'] ?? '');

            $returnedCardCode = null;
            if (preg_match('/CardCode\s*[:=]\s*([A-Za-z0-9_-]+)/i', $message, $matches)) {
                $returnedCardCode = $matches[1];
            } elseif (!empty($body['CardCode'])) {
                $returnedCardCode = $body['CardCode'];
            }

            $isSuccess = $response->successful()
                && isset($body['ErrorCode'])
                && (int) $body['ErrorCode'] === 0
                && !empty($returnedCardCode);

            Log::info("SAP AddVendor response for vendor {$vendor->id}:", [
                'status'  => $statusCode,
                'payload' => $sapPayload,
                'body'    => $body,
            ]);

            return [
                'success'    => $isSuccess,
                'card_code'  => $returnedCardCode,
                'message'    => $message ?: ($isSuccess ? 'Success - [AddVendor]' : 'Failed to add vendor to SAP'),
                'error_code' => $body['ErrorCode'] ?? ($isSuccess ? 0 : -1),
                'payload'    => $sapPayload,
                'raw'        => $body,
            ];
        } catch (\Throwable $e) {
            Log::warning("SAP AddVendor connection error for vendor {$vendor->id}: " . $e->getMessage(), [
                'payload' => $sapPayload,
            ]);

            return [
                'success'    => false,
                'card_code'  => null,
                'message'    => 'Could not connect to SAP API: ' . $e->getMessage(),
                'error_code' => -999,
                'payload'    => $sapPayload,
                'raw'        => null,
            ];
        }
    }
}
