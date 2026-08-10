<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\DistributorApiKey;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalCustomerMonthlyOrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected Distributor $distributor;
    protected string $rawApiKey;
    protected DistributorApiKey $apiKeyRecord;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->distributor = Distributor::create([
            'code_customer' => 'CUST-TEST-001',
            'name' => 'Distributor Test PT',
        ]);

        $this->rawApiKey = 'susanti_sec_' . Str::random(40);
        $hashedKey = DistributorApiKey::hashKey($this->rawApiKey);

        $this->apiKeyRecord = DistributorApiKey::create([
            'distributor_id' => $this->distributor->id,
            'name' => 'ERP System Test',
            'key_prefix' => substr($this->rawApiKey, 0, 15),
            'api_key_hash' => $hashedKey,
            'is_active' => true,
        ]);
        $this->apiKeyRecord->distributors()->attach($this->distributor->id);

        $this->item = Item::create([
            'item_code' => 'SKU-TEST-001',
            'item_name' => 'Barang Test 1',
            'price' => 50000,
            'sales_uom' => 'CTN',
        ]);
    }

    public function test_fails_without_api_key(): void
    {
        $response = $this->postJson('/api/distributor-channel/v1/external/customer-monthly-orders', []);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_fails_with_invalid_api_key(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid_key_xyz')
            ->postJson('/api/distributor-channel/v1/external/customer-monthly-orders', []);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_creates_cmo_successfully_via_external_api(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->create('po.pdf', 100, 'application/pdf');

        $payload = [
            'card_code' => 'CUST-TEST-001',
            'distributor_ref_no' => 'PO-DIST-2026-001',
            'doc_date' => '2026-08-07',
            'lines' => json_encode([
                [
                    'item_code' => 'SKU-TEST-001',
                    'quantity' => 10,
                    'unit_price' => 50000,
                ],
            ]),
            'attachment' => $file,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rawApiKey)
            ->post('/api/distributor-channel/v1/external/customer-monthly-orders', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'is_duplicate' => false,
            ]);

        $this->assertDatabaseHas('customer_monthly_orders', [
            'distributor_id' => $this->distributor->id,
            'distributor_ref_no' => 'PO-DIST-2026-001',
            'created_via' => 'DISTRIBUTOR_API',
        ]);
    }

    public function test_idempotency_returns_existing_order_for_duplicate_ref_no(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->create('po.pdf', 100, 'application/pdf');

        $payload = [
            'card_code' => 'CUST-TEST-001',
            'distributor_ref_no' => 'PO-DIST-2026-RETRY',
            'doc_date' => '2026-08-07',
            'lines' => json_encode([
                [
                    'item_code' => 'SKU-TEST-001',
                    'quantity' => 5,
                    'unit_price' => 50000,
                ],
            ]),
            'attachment' => $file,
        ];

        // First call -> 201 Created
        $response1 = $this->withHeader('Authorization', 'Bearer ' . $this->rawApiKey)
            ->post('/api/distributor-channel/v1/external/customer-monthly-orders', $payload);

        $response1->assertStatus(201);

        // Second call with same distributor_ref_no -> 200 OK (Idempotent response)
        $response2 = $this->withHeader('Authorization', 'Bearer ' . $this->rawApiKey)
            ->post('/api/distributor-channel/v1/external/customer-monthly-orders', $payload);

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_duplicate' => true,
            ]);
    }

    public function test_fails_validation_for_non_existent_item(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->create('po.pdf', 100, 'application/pdf');

        $payload = [
            'card_code' => 'CUST-TEST-001',
            'distributor_ref_no' => 'PO-DIST-INVALID-SKU',
            'doc_date' => '2026-08-07',
            'lines' => json_encode([
                [
                    'item_code' => 'SKU-NON-EXISTENT',
                    'quantity' => 10,
                ],
            ]),
            'attachment' => $file,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rawApiKey)
            ->post('/api/distributor-channel/v1/external/customer-monthly-orders', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_auto_populates_eta_date_and_addresses(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->create('po.pdf', 100, 'application/pdf');

        $payload = [
            'card_code' => 'CUST-TEST-001',
            'distributor_ref_no' => 'PO-DIST-AUTO-POPULATE',
            'doc_date' => '2026-08-10',
            'lines' => json_encode([
                [
                    'item_code' => 'SKU-TEST-001',
                    'quantity' => 2,
                    'unit_price' => 50000,
                ],
            ]),
            'attachment' => $file,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rawApiKey)
            ->post('/api/distributor-channel/v1/external/customer-monthly-orders', $payload);

        $response->assertStatus(201);

        // Verify eta_date is auto-calculated to doc_date + 7 days (2026-08-17)
        $order = \App\Models\CustomerMonthlyOrder::where('distributor_ref_no', 'PO-DIST-AUTO-POPULATE')->first();
        $this->assertNotNull($order);
        $this->assertEquals('2026-08-10', \Carbon\Carbon::parse($order->doc_date)->format('Y-m-d'));
        $this->assertEquals('2026-08-17', \Carbon\Carbon::parse($order->eta_date)->format('Y-m-d'));
    }

    public function test_creates_cmo_with_attachment_upload(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $file = \Illuminate\Http\UploadedFile::fake()->create('document_po.pdf', 200, 'application/pdf');

        $payload = [
            'card_code' => 'CUST-TEST-001',
            'distributor_ref_no' => 'PO-DIST-WITH-ATTACHMENT',
            'doc_date' => '2026-08-10',
            'lines' => json_encode([
                [
                    'item_code' => 'SKU-TEST-001',
                    'quantity' => 5,
                    'unit_price' => 50000,
                ],
            ]),
            'attachment' => $file,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rawApiKey)
            ->post('/api/distributor-channel/v1/external/customer-monthly-orders', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'is_duplicate' => false,
            ]);

        $order = \App\Models\CustomerMonthlyOrder::where('distributor_ref_no', 'PO-DIST-WITH-ATTACHMENT')->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->attachments);
        $this->assertEquals('document_po.pdf', $order->attachments->first()->file_name);
    }

    public function test_fails_validation_when_attachment_missing(): void
    {
        $payload = [
            'card_code' => 'CUST-TEST-001',
            'distributor_ref_no' => 'PO-DIST-NO-ATTACHMENT',
            'doc_date' => '2026-08-10',
            'lines' => json_encode([
                [
                    'item_code' => 'SKU-TEST-001',
                    'quantity' => 5,
                    'unit_price' => 50000,
                ],
            ]),
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rawApiKey)
            ->post('/api/distributor-channel/v1/external/customer-monthly-orders', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('errors.attachment.0', 'Dokumen bukti PO fisik (attachment) dalam format PDF wajib diunggah.');
    }
}
