<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Modules\VendorPortal\Models\Vendor;
use App\Modules\VendorPortal\Models\VendorUser;
use App\Modules\VendorPortal\Services\VendorLegalApprovalService;

class VendorPortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_can_check_email_availability()
    {
        $response = $this->getJson('/api/distributor-channel/vendor-portal/check-email?email=newvendor@example.com');
        $response->assertStatus(200)
            ->assertJson([
                'available' => true,
                'email' => 'newvendor@example.com',
            ]);
    }

    public function test_can_register_new_vendor_with_documents()
    {
        $payload = [
            'vendor_type' => 'expedition',
            'company_name' => 'PT Cepat Aman Logistik',
            'company_email' => 'contact@cepataman.com',
            'company_phone' => '021-5551234',
            'company_npwp' => '01.234.567.8-901.000',
            'address' => 'Jl. Daan Mogot KM 12',
            'city' => 'Jakarta Barat',
            'province' => 'DKI Jakarta',
            'postal_code' => '11840',
            'pic_name' => 'Hendro Wijaya',
            'pic_phone' => '081298765432',
            'terms_agreed' => true,
            'akta' => UploadedFile::fake()->create('akta_perusahaan.pdf', 500, 'application/pdf'),
            'nib' => UploadedFile::fake()->create('nib_usaha.pdf', 300, 'application/pdf'),
            'npwp' => UploadedFile::fake()->create('npwp_perusahaan.png', 200, 'image/png'),
            'support' => UploadedFile::fake()->create('surat_rekomendasi.pdf', 400, 'application/pdf'),
        ];

        $response = $this->postJson('/api/distributor-channel/vendor-portal/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'company_name' => 'PT Cepat Aman Logistik',
                    'company_npwp' => '01.234.567.8-901.000',
                    'vendor_type' => 'EXPEDITION',
                    'registration_status' => 'PENDING_LEGAL_APPROVAL',
                    'uploaded_documents_count' => 4,
                ],
            ]);

        $conn = config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
        $this->assertDatabaseHas('vendors', [
            'company_email' => 'contact@cepataman.com',
            'company_npwp' => '01.234.567.8-901.000',
            'vendor_type' => 'EXPEDITION',
            'registration_status' => 'PENDING_LEGAL_APPROVAL',
        ], $conn);

        $vendor = Vendor::where('company_email', 'contact@cepataman.com')->first();
        $this->assertNotNull($vendor);
        $this->assertCount(4, $vendor->documents);
        $this->assertCount(1, $vendor->approvalHistories);
    }

    public function test_can_register_new_vendor_with_dynamic_documents_array()
    {
        $payload = [
            'vendor_type' => 'expedition',
            'company_name' => 'PT Tukang Kirim',
            'company_email' => 'ogaming.otong@gmail.com',
            'company_npwp' => '24234535345',
            'address' => 'Street',
            'pic_name' => 'Bro Cahyo',
            'pic_phone' => '08123424234',
            'terms_agreed' => 1,
            'documents' => [
                ['document_type' => 'akta', 'file' => UploadedFile::fake()->create('akta.pdf', 100, 'application/pdf')],
                ['document_type' => 'sk_akta_pendirian', 'file' => UploadedFile::fake()->create('sk_pendirian.pdf', 100, 'application/pdf')],
                ['document_type' => 'akta_perubahan', 'file' => UploadedFile::fake()->create('akta_perubahan.pdf', 100, 'application/pdf')],
                ['document_type' => 'sk_akta_perubahan', 'file' => UploadedFile::fake()->create('sk_perubahan.pdf', 100, 'application/pdf')],
                ['document_type' => 'nib', 'file' => UploadedFile::fake()->create('nib.pdf', 100, 'application/pdf')],
                ['document_type' => 'npwp', 'file' => UploadedFile::fake()->create('npwp.png', 100, 'image/png')],
                ['document_type' => 'ktp_direktur', 'file' => UploadedFile::fake()->create('ktp.jpg', 100, 'image/jpeg')],
                ['document_type' => 'sertifikat_halal', 'file' => UploadedFile::fake()->create('halal.pdf', 100, 'application/pdf')],
                ['document_type' => 'pakta_integritas', 'file' => UploadedFile::fake()->create('pakta.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
                ['document_type' => 'peraturan_kerjasama', 'file' => UploadedFile::fake()->create('pks.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
            ],
        ];

        $response = $this->postJson('/api/distributor-channel/vendor-portal/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'company_name' => 'PT Tukang Kirim',
                    'uploaded_documents_count' => 10,
                ],
            ]);

        $vendor = Vendor::where('company_email', 'ogaming.otong@gmail.com')->first();
        $this->assertNotNull($vendor);
        $this->assertCount(10, $vendor->documents);

        $uploadedTypes = $vendor->documents->pluck('document_type')->toArray();
        $this->assertContains('AKTA', $uploadedTypes);
        $this->assertContains('SK_AKTA_PENDIRIAN', $uploadedTypes);
        $this->assertContains('AKTA_PERUBAHAN', $uploadedTypes);
        $this->assertContains('SK_AKTA_PERUBAHAN', $uploadedTypes);
        $this->assertContains('NIB', $uploadedTypes);
        $this->assertContains('NPWP', $uploadedTypes);
        $this->assertContains('KTP_DIREKTUR', $uploadedTypes);
        $this->assertContains('SERTIFIKAT_HALAL', $uploadedTypes);
        $this->assertContains('PAKTA_INTEGRITAS', $uploadedTypes);
        $this->assertContains('PERATURAN_KERJASAMA', $uploadedTypes);
    }

    public function test_registration_fails_for_duplicate_active_email()
    {
        Vendor::create([
            'vendor_code' => 'VND-202609-0099',
            'vendor_type' => 'EXPEDITION',
            'company_name' => 'PT Cepat Aman Logistik',
            'company_email' => 'contact@cepataman.com',
            'pic_name' => 'Hendro Wijaya',
            'pic_phone' => '081298765432',
            'terms_agreed' => true,
            'registration_status' => 'APPROVED',
            'legal_approval_status' => 'APPROVED',
        ]);

        $payload = [
            'vendor_type' => 'expedition',
            'company_name' => 'PT Cepat Aman Logistik Duplicate',
            'company_email' => 'contact@cepataman.com',
            'pic_name' => 'Bambang',
            'pic_phone' => '0812999999',
            'terms_agreed' => true,
        ];

        $response = $this->postJson('/api/distributor-channel/vendor-portal/register', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_email']);
    }

    public function test_legal_team_can_approve_vendor_and_generate_credentials()
    {
        Mail::fake();

        $vendor = Vendor::create([
            'vendor_code' => 'VND-202609-0001',
            'vendor_type' => 'EXPEDITION',
            'company_name' => 'PT Samudera Logistik Nusantara',
            'company_email' => 'legal@samuderalogistik.com',
            'pic_name' => 'Iwan Fals',
            'pic_phone' => '081122334455',
            'terms_agreed' => true,
            'registration_status' => 'PENDING_LEGAL_APPROVAL',
            'legal_approval_status' => 'PENDING',
        ]);

        $approvalService = new VendorLegalApprovalService();
        $result = $approvalService->approve($vendor, null, [
            'legal_notes' => 'Dokumen NIB dan Akta terverifikasi sah.',
            'initial_password' => 'PassVendor123!',
        ]);

        $this->assertEquals('APPROVED', $result['vendor']->registration_status);
        $this->assertEquals('APPROVED', $result['vendor']->legal_approval_status);
        $this->assertNotNull($result['user']);
        $this->assertEquals('legal@samuderalogistik.com', $result['user']->email);
        $this->assertEquals('PassVendor123!', $result['generated_credentials']['initial_password']);

        Mail::assertSent(\App\Mail\VendorCredentialsMail::class, function ($mail) {
            return $mail->hasTo('legal@samuderalogistik.com') && $mail->plainPassword === 'PassVendor123!';
        });

        // Test login with generated credentials
        $loginResponse = $this->postJson('/api/distributor-channel/vendor-portal/login', [
            'email' => 'legal@samuderalogistik.com',
            'password' => 'PassVendor123!',
            'vendor_type' => 'expedition',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'dashboard_url' => '/vendor-portal/dashboard/expedition',
                    'user' => [
                        'email' => 'legal@samuderalogistik.com',
                        'role' => 'VENDOR_ADMIN',
                    ],
                ],
            ]);
    }

    public function test_vendor_login_fails_when_pending_approval()
    {
        $vendor = Vendor::create([
            'vendor_code' => 'VND-202609-0002',
            'vendor_type' => 'DISTRIBUTOR',
            'company_name' => 'PT Distributor Pending',
            'company_email' => 'pending@distributor.com',
            'pic_name' => 'Agus',
            'pic_phone' => '0819999888',
            'terms_agreed' => true,
            'registration_status' => 'PENDING_LEGAL_APPROVAL',
            'legal_approval_status' => 'PENDING',
        ]);

        VendorUser::create([
            'vendor_id' => $vendor->id,
            'name' => 'Agus',
            'email' => 'pending@distributor.com',
            'password' => 'secret123',
            'role' => 'VENDOR_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/distributor-channel/vendor-portal/login', [
            'email' => 'pending@distributor.com',
            'password' => 'secret123',
            'vendor_type' => 'distributor',
        ]);

        $response->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);
    }

    public function test_legal_team_can_reject_vendor()
    {
        Mail::fake();

        $vendor = Vendor::create([
            'vendor_code' => 'VND-202609-0003',
            'vendor_type' => 'EXPEDITION',
            'company_name' => 'PT Ekspedisi Fiktif',
            'company_email' => 'fake@ekspedisi.com',
            'pic_name' => 'Doni',
            'pic_phone' => '0813333333',
            'terms_agreed' => true,
            'registration_status' => 'PENDING_LEGAL_APPROVAL',
            'legal_approval_status' => 'PENDING',
        ]);

        $approvalService = new VendorLegalApprovalService();
        $rejectedVendor = $approvalService->reject($vendor, null, 'Perusahaan tidak memiliki NIB valid.');

        $this->assertEquals('REJECTED', $rejectedVendor->registration_status);
        $this->assertEquals('REJECTED', $rejectedVendor->legal_approval_status);
        $this->assertEquals('Perusahaan tidak memiliki NIB valid.', $rejectedVendor->legal_notes);

        Mail::assertSent(\App\Mail\VendorRegistrationRejectedMail::class, function ($mail) use ($vendor) {
            return $mail->hasTo('fake@ekspedisi.com') &&
                   $mail->vendor->id === $vendor->id &&
                   $mail->rejectionReason === 'Perusahaan tidak memiliki NIB valid.';
        });
    }

    public function test_legal_team_can_verify_individual_document()
    {
        $vendor = Vendor::create([
            'vendor_code' => 'VND-202609-0004',
            'vendor_type' => 'EXPEDITION',
            'company_name' => 'PT Mitra Terverifikasi',
            'company_email' => 'verified@ekspedisi.com',
            'pic_name' => 'Bambang',
            'pic_phone' => '081222222',
            'terms_agreed' => true,
            'registration_status' => 'PENDING_LEGAL_APPROVAL',
            'legal_approval_status' => 'PENDING',
        ]);

        $doc = \App\Modules\VendorPortal\Models\VendorDocument::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'NIB',
            'document_number' => '1234567890',
            'file_path' => 'vendor_documents/test.pdf',
            'file_name' => 'test.pdf',
            'verification_status' => 'PENDING',
        ]);

        $response = $this->postJson("/api/distributor-channel/v1/vendor-management/documents/{$doc->id}/verify", [
            'status' => 'VALID',
            'notes' => 'NIB document is verified and valid.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $doc->id,
                    'verification_status' => 'VALID',
                    'verification_notes' => 'NIB document is verified and valid.',
                    'notes' => 'NIB document is verified and valid.',
                ],
            ]);

        $this->assertEquals('VALID', $doc->fresh()->verification_status);
        $this->assertEquals('NIB document is verified and valid.', $doc->fresh()->notes);
    }

    public function test_vendor_can_reupload_revised_document()
    {
        Storage::fake('public');

        $vendor = Vendor::create([
            'vendor_code' => 'VND-202609-0005',
            'vendor_type' => 'EXPEDITION',
            'company_name' => 'PT Reupload Test',
            'company_email' => 'reupload@ekspedisi.com',
            'pic_name' => 'Candra',
            'pic_phone' => '081555555',
            'terms_agreed' => true,
            'registration_status' => 'REVISION_REQUIRED',
            'legal_approval_status' => 'REVISION',
        ]);

        $doc = \App\Modules\VendorPortal\Models\VendorDocument::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'AKTA',
            'document_number' => 'AKTA-001',
            'file_path' => 'vendor_documents/old_akta.pdf',
            'file_name' => 'old_akta.pdf',
            'verification_status' => 'NEEDS_REVISION',
            'notes' => 'Please upload the latest amendment page.',
        ]);

        $newFile = UploadedFile::fake()->create('akta_perubahan_2026.pdf', 500, 'application/pdf');

        $response = $this->postJson("/api/distributor-channel/v1/vendor-portal/documents/{$doc->id}/reupload", [
            'vendor_code' => 'VND-202609-0005',
            'file' => $newFile,
            'notes' => 'Uploaded the latest 2026 amendment page.',
            'document_number' => 'AKTA-001-REV',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $doc->id,
                    'verification_status' => 'PENDING',
                    'notes' => 'Uploaded the latest 2026 amendment page.',
                    'document_number' => 'AKTA-001-REV',
                ],
            ]);

        $this->assertEquals('PENDING', $doc->fresh()->verification_status);
        $this->assertEquals('PENDING_LEGAL_APPROVAL', $vendor->fresh()->registration_status);
    }

    public function test_authenticated_vendor_can_reupload_document_needing_revision()
    {
        Storage::fake('public');

        $vendor = Vendor::create([
            'vendor_code' => 'VND-202609-0007',
            'vendor_type' => 'EXPEDITION',
            'company_name' => 'PT Auth Reupload Test',
            'company_email' => 'auth_reupload@ekspedisi.com',
            'pic_name' => 'Dimas',
            'pic_phone' => '081777777',
            'terms_agreed' => true,
            'registration_status' => 'APPROVED',
            'legal_approval_status' => 'APPROVED',
        ]);

        $vendorUser = \App\Modules\VendorPortal\Models\VendorUser::create([
            'vendor_id' => $vendor->id,
            'name' => 'Dimas Vendor',
            'email' => 'auth_reupload@ekspedisi.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'VENDOR_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $doc = \App\Modules\VendorPortal\Models\VendorDocument::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'NIB',
            'document_number' => 'NIB-888',
            'file_path' => 'vendor_documents/old_nib.pdf',
            'file_name' => 'old_nib.pdf',
            'verification_status' => 'NEEDS_REVISION',
            'notes' => 'Nomor NIB belum terdaftar di OSS.',
        ]);

        $newFile = UploadedFile::fake()->create('nib_revisi.pdf', 300, 'application/pdf');

        // Authenticated vendor calls reupload without sending vendor_code
        $response = $this->actingAs($vendorUser, 'sanctum')
            ->postJson("/api/distributor-channel/v1/vendor-portal/documents/{$doc->id}/reupload", [
                'file' => $newFile,
                'notes' => 'Sudah diverifikasi dan diperbarui di OSS.',
                'document_number' => 'NIB-888-REV',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $doc->id,
                    'verification_status' => 'PENDING',
                    'notes' => 'Sudah diverifikasi dan diperbarui di OSS.',
                    'document_number' => 'NIB-888-REV',
                ],
            ]);

        $this->assertEquals('PENDING', $doc->fresh()->verification_status);
    }

    public function test_preview_document_endpoint_returns_file_stream()
    {
        $vendor = Vendor::create([
            'vendor_code' => 'VND-202609-0006',
            'vendor_type' => 'EXPEDITION',
            'company_name' => 'PT Preview Test',
            'company_email' => 'preview@ekspedisi.com',
            'pic_name' => 'Eko',
            'pic_phone' => '081666666',
            'terms_agreed' => true,
            'registration_status' => 'PENDING_LEGAL_APPROVAL',
            'legal_approval_status' => 'PENDING',
        ]);

        // Create a real temporary test file in storage
        $testDir = storage_path('app/public/vendor_documents/VND-202609-0006');
        if (!file_exists($testDir)) {
            mkdir($testDir, 0755, true);
        }
        $testFilePath = $testDir . '/sample.pdf';
        file_put_contents($testFilePath, '%PDF-1.4 sample test content');

        $doc = \App\Modules\VendorPortal\Models\VendorDocument::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'NIB',
            'document_number' => 'NIB-999',
            'file_path' => 'vendor_documents/VND-202609-0006/sample.pdf',
            'file_name' => 'sample.pdf',
            'file_mime' => 'application/pdf',
            'verification_status' => 'PENDING',
        ]);

        $response = $this->get("/api/distributor-channel/v1/vendor-management/documents/{$doc->id}/preview");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // Cleanup test file
        @unlink($testFilePath);
        @rmdir($testDir);
    }

    public function test_me_endpoint_returns_vendor_profile_with_uploaded_documents()
    {
        $vendor = Vendor::create([
            'vendor_code'           => 'VND-202609-0010',
            'vendor_type'           => 'EXPEDITION',
            'company_name'          => 'PT Profil Test',
            'company_email'         => 'profil@test.com',
            'pic_name'              => 'Budi',
            'pic_phone'             => '0812345678',
            'terms_agreed'          => true,
            'registration_status'   => 'APPROVED',
            'legal_approval_status' => 'APPROVED',
        ]);

        \App\Modules\VendorPortal\Models\VendorDocument::create([
            'vendor_id'           => $vendor->id,
            'document_type'       => 'AKTA',
            'document_number'     => 'AKTA-001',
            'file_path'           => 'vendor_documents/VND-202609-0010/akta.pdf',
            'file_name'           => 'akta.pdf',
            'file_mime'           => 'application/pdf',
            'file_size'           => 1024,
            'verification_status' => 'VALID',
        ]);

        \App\Modules\VendorPortal\Models\VendorDocument::create([
            'vendor_id'           => $vendor->id,
            'document_type'       => 'NIB',
            'document_number'     => 'NIB-001',
            'file_path'           => 'vendor_documents/VND-202609-0010/nib.pdf',
            'file_name'           => 'nib.pdf',
            'file_mime'           => 'application/pdf',
            'file_size'           => 2048,
            'verification_status' => 'VALID',
        ]);

        $user = VendorUser::create([
            'vendor_id'            => $vendor->id,
            'name'                 => 'Budi PIC',
            'email'                => 'profil@test.com',
            'password'             => \Illuminate\Support\Facades\Hash::make('OldPassword123!'),
            'role'                 => 'VENDOR_ADMIN',
            'status'               => 'ACTIVE',
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/distributor-channel/vendor-portal/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id'                   => $user->id,
                        'name'                 => 'Budi PIC',
                        'email'                => 'profil@test.com',
                        'must_change_password' => true,
                    ],
                    'vendor' => [
                        'vendor_code' => 'VND-202609-0010',
                    ],
                ],
            ]);

        $docs = $response->json('data.documents');
        $this->assertIsArray($docs);
        $this->assertCount(2, $docs);
        $this->assertEquals('AKTA', $docs[0]['document_type']);
        $this->assertEquals('NIB', $docs[1]['document_type']);
    }

    public function test_can_change_vendor_password()
    {
        $vendor = Vendor::create([
            'vendor_code'           => 'VND-202609-0011',
            'vendor_type'           => 'EXPEDITION',
            'company_name'          => 'PT Password Test',
            'company_email'         => 'pwd@test.com',
            'pic_name'              => 'Andi',
            'pic_phone'             => '0812345678',
            'terms_agreed'          => true,
            'registration_status'   => 'APPROVED',
            'legal_approval_status' => 'APPROVED',
        ]);

        $user = VendorUser::create([
            'vendor_id'            => $vendor->id,
            'name'                 => 'Andi PIC',
            'email'                => 'pwd@test.com',
            'password'             => \Illuminate\Support\Facades\Hash::make('OldPassword123!'),
            'role'                 => 'VENDOR_ADMIN',
            'status'               => 'ACTIVE',
            'must_change_password' => true,
        ]);

        // 1. Wrong current password -> fails
        $failResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/distributor-channel/vendor-portal/change-password', [
                'current_password'          => 'WrongPass123!',
                'new_password'              => 'NewPassword123!',
                'new_password_confirmation' => 'NewPassword123!',
            ]);
        $failResponse->assertStatus(422);

        // 2. Same password -> fails
        $sameResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/distributor-channel/vendor-portal/change-password', [
                'current_password'          => 'OldPassword123!',
                'new_password'              => 'OldPassword123!',
                'new_password_confirmation' => 'OldPassword123!',
            ]);
        $sameResponse->assertStatus(422);

        // 3. Valid password change -> success
        $successResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/distributor-channel/vendor-portal/change-password', [
                'current_password'          => 'OldPassword123!',
                'new_password'              => 'NewPassword123!',
                'new_password_confirmation' => 'NewPassword123!',
            ]);

        $successResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully.',
                'data' => [
                    'must_change_password' => false,
                ],
            ]);

        $this->assertFalse((bool) $user->fresh()->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword123!', $user->fresh()->password));
    }
}
