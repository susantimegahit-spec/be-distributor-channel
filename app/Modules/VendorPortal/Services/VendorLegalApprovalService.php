<?php

namespace App\Modules\VendorPortal\Services;

use App\Modules\VendorPortal\Models\Vendor;
use App\Modules\VendorPortal\Models\VendorDocument;
use App\Modules\VendorPortal\Models\VendorUser;
use App\Modules\VendorPortal\Models\VendorApprovalHistory;
use App\Modules\VendorPortal\Models\VendorCredentialsDispatchLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Mail\VendorCredentialsMail;
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

            // 1. Update status vendor
            $vendor->update([
                'registration_status' => 'APPROVED',
                'legal_approval_status' => 'APPROVED',
                'legal_approved_by' => $actorId,
                'legal_approved_at' => $now,
                'legal_notes' => $legalNotes,
                'sap_vendor_code' => $options['sap_vendor_code'] ?? $vendor->sap_vendor_code,
            ]);

            // 2. Generate Kredensial Login Vendor
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

            // 3. Auto-sync ke tabel ekspedisi.expeditions jika tipe vendor adalah EXPEDITION
            $expeditionCreated = null;
            if (strtoupper($vendor->vendor_type) === 'EXPEDITION') {
                $expeditionCreated = $this->syncToExpeditionMaster($vendor);
            }

            // 4. Kirim Email Kredensial Resmi ke Vendor & Catat Log Dispatch
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

            // 5. Catat Audit Trail Approval
            VendorApprovalHistory::create([
                'vendor_id' => $vendor->id,
                'action' => 'APPROVED',
                'from_status' => 'PENDING_LEGAL_APPROVAL',
                'to_status' => 'APPROVED',
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'notes' => $legalNotes . ' Vendor login credentials generated successfully.',
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
    protected function syncToExpeditionMaster(Vendor $vendor): ?object
    {
        try {
            $ekspedisiConn = config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_ekspedisi';
            if (!Schema::connection($ekspedisiConn)->hasTable('expeditions')) {
                return null;
            }

            $expeditionCode = 'EXP-' . strtoupper(Str::slug(substr($vendor->company_name, 0, 8), '')) . '-' . sprintf('%04d', $vendor->id);

            // Cek apakah sudah ada ekspedisi dengan email atau kode yang sama
            $existing = DB::connection($ekspedisiConn)->table('expeditions')
                ->where('email', $vendor->company_email)
                ->orWhere('expedition_name', $vendor->company_name)
                ->first();

            if ($existing) {
                $vendor->update(['expedition_id' => $existing->id]);
                return $existing;
            }

            $expeditionId = DB::connection($ekspedisiConn)->table('expeditions')->insertGetId([
                'expedition_code' => $expeditionCode,
                'expedition_name' => $vendor->company_name,
                'address' => $vendor->address,
                'city' => $vendor->city,
                'province' => $vendor->province,
                'postal_code' => $vendor->postal_code,
                'pic_name' => $vendor->pic_name,
                'pic_phone' => $vendor->pic_phone,
                'email' => $vendor->company_email,
                'status' => 'ACTIVE',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $vendor->update(['expedition_id' => $expeditionId]);

            return (object)[
                'id' => $expeditionId,
                'expedition_code' => $expeditionCode,
                'expedition_name' => $vendor->company_name,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Auto-sync expedition failed for vendor {$vendor->id}: " . $e->getMessage());
            return null;
        }
    }
}
