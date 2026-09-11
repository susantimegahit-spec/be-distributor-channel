<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\Expedition;
use App\Models\ExpeditionRate;
use App\Models\Warehouse;
use App\Models\CustomerShipto;
use App\Modules\VendorPortal\Models\Vendor;
use App\Modules\VendorPortal\Models\VendorUser;
use App\Modules\VendorPortal\Services\VendorLegalApprovalService;

class VendorRateSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Storage::fake('local');
    }

    protected function createApprovedExpeditionVendor(): array
    {
        $vendor = Vendor::create([
            'vendor_code'           => 'VND-202609-8888',
            'vendor_type'           => 'EXPEDITION',
            'company_name'          => 'PT Ekspedisi Nusantara Express',
            'company_email'         => 'nusantara@express.co.id',
            'company_phone'         => '081234567890',
            'company_npwp'          => '01.234.567.8-999.000',
            'address'               => 'Jl. Logistik Utama No. 99',
            'city'                  => 'Surabaya',
            'province'              => 'Jawa Timur',
            'postal_code'           => '60123',
            'pic_name'              => 'Budi Santoso',
            'pic_phone'             => '081298765432',
            'terms_agreed'          => true,
            'registration_status'   => 'PENDING_LEGAL_APPROVAL',
            'legal_approval_status' => 'PENDING',
        ]);

        $approvalService = app(VendorLegalApprovalService::class);
        $result = $approvalService->approve($vendor, null, [
            'initial_password' => 'PassNusantara123!',
            'skip_sap_sync'    => true,
            'sap_vendor_code'  => 'VN10001',
        ]);

        $vendor->refresh();
        $vendorUser = $result['user'];

        return [$vendor, $vendorUser, 'PassNusantara123!'];
    }

    public function test_approved_expedition_vendor_is_synced_bidirectionally()
    {
        [$vendor, $vendorUser] = $this->createApprovedExpeditionVendor();

        $this->assertEquals('APPROVED', $vendor->registration_status);
        $this->assertNotNull($vendor->expedition_id);

        $expedition = Expedition::find($vendor->expedition_id);
        $this->assertNotNull($expedition);
        $this->assertEquals($vendor->id, $expedition->vendor_id);
        $this->assertEquals($vendor->company_name, $expedition->expedition_name);
        $this->assertEquals($vendor->company_email, $expedition->email);
        $this->assertEquals($vendor->company_npwp, $expedition->npwp);

        // Test Eloquent relations
        $this->assertEquals($expedition->id, $vendor->expedition->id);
        $this->assertEquals($vendor->id, $expedition->vendor->id);
    }

    public function test_approved_vendor_can_download_rate_submission_template()
    {
        [$vendor, $vendorUser] = $this->createApprovedExpeditionVendor();

        $response = $this->actingAs($vendorUser, 'sanctum')
            ->get('/api/distributor-channel/vendor-portal/rates/template');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringNotContainsString('Origin Code', $content);
        $this->assertStringContainsString('Origin Name', $content);
        $this->assertStringNotContainsString('Destination City', $content);
        $this->assertStringContainsString('Min Weight (Kg)', $content);
        $this->assertStringContainsString('Max Weight (Kg)', $content);
        $this->assertStringContainsString('Lead Time', $content);
    }

    public function test_approved_vendor_can_manually_submit_rate()
    {
        [$vendor, $vendorUser] = $this->createApprovedExpeditionVendor();

        $warehouse = Warehouse::create([
            'whs_code' => 'WHS-SBY-01',
            'whs_name' => 'Gudang Surabaya Rungkut',
        ]);

        $shipto = CustomerShipto::create([
            'card_code' => 'CUST-MLG-01',
            'name'      => 'Depo Malang Kota',
            'alias'     => 'MLG-KOTA',
            'city'      => 'Malang',
        ]);

        $payload = [
            'warehouse_id'     => $warehouse->id,
            'destination_id'   => $shipto->id,
            'transport_mode'   => 'DARAT',
            'service_type'     => 'TRUCKING CDD',
            'min_tonnage'      => 0,
            'max_tonnage'      => 5000,
            'price'            => 1850000,
            'eta_days'         => 1,
            'remarks'          => 'Tarif khusus rute Surabaya - Malang armada CDD',
        ];

        $response = $this->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/distributor-channel/vendor-portal/rates', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'expedition_id'   => $vendor->expedition_id,
                    'warehouse_id'    => $warehouse->id,
                    'destination_id'  => $shipto->id,
                    'price'           => 1850000,
                    'approval_status' => 'PENDING',
                    'flag'            => false,
                ],
            ]);

        $this->assertDatabaseHas('expedition_rates', [
            'expedition_id'   => $vendor->expedition_id,
            'warehouse_id'    => $warehouse->id,
            'destination_id'  => $shipto->id,
            'price'           => 1850000,
            'approval_status' => 'PENDING',
            'flag'            => false,
        ]);
    }

    public function test_approved_vendor_can_list_submitted_rates()
    {
        [$vendor, $vendorUser] = $this->createApprovedExpeditionVendor();

        $warehouse = Warehouse::create([
            'whs_code' => 'WHS-JKT-01',
            'whs_name' => 'Gudang Marunda Jakarta',
        ]);

        $shipto = CustomerShipto::create([
            'card_code' => 'CUST-BDG-01',
            'name'      => 'Toko Mitra Bandung',
            'city'      => 'Bandung',
        ]);

        ExpeditionRate::create([
            'expedition_id'   => $vendor->expedition_id,
            'warehouse_id'    => $warehouse->id,
            'destination_id'  => $shipto->id,
            'transport_mode'  => 'DARAT',
            'service_type'    => 'FUSO',
            'min_tonnage'     => 0,
            'max_tonnage'     => 8000,
            'price'           => 3200000,
            'approval_status' => 'PENDING',
            'flag'            => false,
            'status'          => 'ACTIVE',
        ]);

        $response = $this->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/distributor-channel/vendor-portal/rates');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 1,
                ],
            ]);
    }

    public function test_approved_vendor_can_upload_rates_csv()
    {
        [$vendor, $vendorUser] = $this->createApprovedExpeditionVendor();

        $warehouse = Warehouse::create([
            'whs_code' => 'WHS-GRS-01',
            'whs_name' => 'Gudang Manyar Gresik',
        ]);

        $shipto = CustomerShipto::create([
            'card_code' => 'CUST-SMG-01',
            'name'      => 'Distributor Semarang',
            'city'      => 'Semarang',
        ]);

        $csvContent = "No,Origin Name,Destination,Transport Mode,Min Weight (Kg),Max Weight (Kg),Service Type,Rate,Lead Time\n"
                    . "1,Gudang Manyar Gresik,{$shipto->card_code},DARAT,0,15000,WINGBOX,4500000,2\n";

        $file = UploadedFile::fake()->createWithContent('rates_upload.csv', $csvContent);

        // Upload with pop-up period '2026-08-15'
        $response = $this->actingAs($vendorUser, 'sanctum')
            ->post('/api/distributor-channel/vendor-portal/rates/upload', [
                'file'    => $file,
                'periode' => '2026-08-15',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'processed_count' => 1,
                    'created_count'   => 1,
                ],
            ]);

        $batchId = $response->json('data.upload_batch_id');
        $this->assertNotEmpty($batchId);

        $rate = ExpeditionRate::where('expedition_id', $vendor->expedition_id)->first();
        $this->assertNotNull($rate);
        $this->assertEquals($warehouse->id, $rate->warehouse_id);
        $this->assertEquals($shipto->id, $rate->destination_id);
        $this->assertEquals(4500000, $rate->price);
        $this->assertEquals(2, $rate->eta_days);
        $this->assertEquals('2026-08-15', $rate->valid_from->format('Y-m-d'));
        $this->assertEquals('2026-08-15', $rate->valid_until->format('Y-m-d'));
        $this->assertEquals('PENDING', $rate->approval_status);
        $this->assertFalse((bool) $rate->flag);
    }

    public function test_approved_vendor_can_list_rate_headers_and_batch_details()
    {
        [$vendor, $vendorUser] = $this->createApprovedExpeditionVendor();

        $warehouse = Warehouse::create([
            'whs_code' => 'WHS-SBY-02',
            'whs_name' => 'Gudang Margomulyo Surabaya',
        ]);

        $shipto = CustomerShipto::create([
            'card_code' => 'CUST-SBY-01',
            'name'      => 'Distributor Surabaya Timur',
            'city'      => 'Surabaya',
        ]);

        $batchId = 'BATCH-RATE-TEST-001';

        ExpeditionRate::create([
            'expedition_id'   => $vendor->expedition_id,
            'warehouse_id'    => $warehouse->id,
            'destination_id'  => $shipto->id,
            'transport_mode'  => 'DARAT',
            'service_type'    => 'CDD',
            'min_tonnage'     => 0,
            'max_tonnage'     => 5000,
            'price'           => 1200000,
            'eta_days'        => 1,
            'valid_from'      => '2026-08-15',
            'valid_until'     => '2027-08-15',
            'status'          => 'ACTIVE',
            'flag'            => false,
            'approval_status' => 'PENDING',
            'remarks'         => 'Pengajuan rute batch test',
            'upload_batch_id' => $batchId,
        ]);

        // 1. Test GET /rates/headers
        $headersResponse = $this->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/distributor-channel/vendor-portal/rates/headers');

        $headersResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Rate submission headers retrieved successfully.',
                'meta'    => [
                    'total' => 1,
                ],
            ]);

        $headerItem = $headersResponse->json('data.0');
        $this->assertEquals($batchId, $headerItem['batch_id']);
        $this->assertEquals('2026-08-15', $headerItem['valid_from']);
        $this->assertEquals('2027-08-15', $headerItem['valid_until']);
        $this->assertEquals(1, $headerItem['total_routes']);
        $this->assertEquals('PENDING', $headerItem['approval_status']);

        // 2. Test GET /rates/headers/{batchId} (detail endpoint)
        $detailResponse = $this->actingAs($vendorUser, 'sanctum')
            ->getJson("/api/distributor-channel/vendor-portal/rates/headers/{$batchId}");

        $detailResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'header' => [
                        'batch_id'        => $batchId,
                        'valid_from'      => '2026-08-15',
                        'valid_until'     => '2027-08-15',
                        'approval_status' => 'PENDING',
                        'total_routes'    => 1,
                    ],
                ],
            ]);

        $detailRow = $detailResponse->json('data.details.0');
        $this->assertEquals('WHS-SBY-02', $detailRow['origin_code']);
        $this->assertEquals('CUST-SBY-01', $detailRow['destination_code']);
        $this->assertEquals('Surabaya', $detailRow['destination_city']);
        $this->assertEquals(1, $detailRow['leadtime']);
        $this->assertEquals(1200000, $detailRow['rate']);
    }

    public function test_unapproved_vendor_cannot_access_rates()
    {
        $vendor = Vendor::create([
            'vendor_code'           => 'VND-202609-7777',
            'vendor_type'           => 'EXPEDITION',
            'company_name'          => 'PT Pending Vendor',
            'company_email'         => 'pending@vendor.com',
            'pic_name'              => 'Pending Guy',
            'pic_phone'             => '0811111111',
            'terms_agreed'          => true,
            'registration_status'   => 'PENDING_LEGAL_APPROVAL',
            'legal_approval_status' => 'PENDING',
        ]);

        $user = VendorUser::create([
            'vendor_id' => $vendor->id,
            'name'      => 'Pending Guy',
            'email'     => 'pending@vendor.com',
            'password'  => Hash::make('password123'),
            'role'      => 'VENDOR_ADMIN',
            'status'    => 'ACTIVE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/distributor-channel/vendor-portal/rates');

        $response->assertStatus(403);
    }
}
