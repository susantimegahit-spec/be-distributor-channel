<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
                    'vendor_type' => 'EXPEDITION',
                    'registration_status' => 'PENDING_LEGAL_APPROVAL',
                    'uploaded_documents_count' => 4,
                ],
            ]);

        $conn = config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
        $this->assertDatabaseHas('vendors', [
            'company_email' => 'contact@cepataman.com',
            'vendor_type' => 'EXPEDITION',
            'registration_status' => 'PENDING_LEGAL_APPROVAL',
        ], $conn);

        $vendor = Vendor::where('company_email', 'contact@cepataman.com')->first();
        $this->assertNotNull($vendor);
        $this->assertCount(4, $vendor->documents);
        $this->assertCount(1, $vendor->approvalHistories);
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
    }
}
