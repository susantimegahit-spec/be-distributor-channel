<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Distributor;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Distributor $distributor;
    protected string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create distributor
        $this->distributor = Distributor::create([
            'code_customer' => 'C110003074',
            'name' => 'PT XYZ',
            'address' => 'Jl. Dummy No. 123',
            'phone' => '021-12345678',
            'email' => 'info@xyz.com',
            'status' => 1,
        ]);

        // Create user for distributor
        $this->user = User::create([
            'name' => 'Distributor User',
            'username' => 'distuser',
            'email' => 'dist@example.com',
            'password' => Hash::make($this->password),
            'code_customer' => 'C110003074',
            'is_active' => true,
        ]);

        // Create master records
        \App\Models\SalesEmployee::create([
            'slp_code' => 0,
            'slp_name' => 'John Doe Sales',
            'status' => 1,
        ]);

        \App\Models\Item::create([
            'item_code' => 'E65',
            'item_name' => 'TOP 250 M @ 10 KG / BAL',
            'suom_entry' => 1,
            'sal_unit_msr' => 'Kg',
            'per_kg' => 10,
            'status' => 1,
        ]);

        \App\Models\Warehouse::create([
            'whs_code' => 'FG04',
            'whs_name' => 'Finished Goods 04',
            'status' => 1,
        ]);

        \App\Models\Vat::create([
            'code' => 'S1',
            'name' => 'PPN 11%',
            'rate' => 11.00,
            'status' => 1,
        ]);

        \App\Models\OcrCode::create([
            'ocr_code' => 'SBY',
            'ocr_name' => 'Surabaya Depo',
            'distribution_target' => 'SBY',
            'status' => 1,
        ]);

        \App\Models\OcrCode::create([
            'ocr_code' => 'GRM',
            'ocr_name' => 'Garam Division',
            'distribution_target' => 'GRM',
            'status' => 1,
        ]);

        \App\Models\OcrCode::create([
            'ocr_code' => 'MKT',
            'ocr_name' => 'Marketing Team',
            'distribution_target' => 'MKT',
            'status' => 1,
        ]);

        $this->seed(\Database\Seeders\MasterApprovalSeeder::class);
    }

    /**
     * Test creating a sales order draft.
     */
    public function test_create_sales_order_draft(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $payload = [
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'po_number' => 'PO1212-ABC',
            'doc_date' => '2026-02-25',
            'doc_due_date' => '2026-02-25',
            'use_balance' => true,
            'slp_code' => 0,
            'cntct_code' => -1,
            'pay_to_code' => 'ALAMAT PENAGIHAN',
            'address' => 'KOMPLEK PURI MUTIARA BLOK B 5-6, SUNTER',
            'ship_to_code' => 'ALAMAT KIRIM',
            'address2' => 'KOMPLEK PURI MUTIARA BLOK B 5-6, SUNTER',
            'comments' => 'Test ADD',
            'id_discount' => '260310699',
            'lines' => [
                [
                    'item_code' => 'E65',
                    'quantity' => 10,
                    'unit_msr' => 'Kg',
                    'uom_entry' => 1,
                    'whs_code' => 'FG04',
                    'unit_price' => 5000,
                    'disc_percent' => 0,
                    'vat_group' => 'S1',
                    'line_total' => 50000,
                    'free_text' => 'Free text sample',
                    'ocr_code' => 'SBY',
                    'ocr_code2' => 'GRM',
                    'ocr_code3' => 'MKT',
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/sales-orders', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sales order draft berhasil dibuat.',
                'data' => [
                    'card_code' => 'C110003074',
                    'status' => 'DRAFT',
                    'doc_total' => 50000,
                    'use_balance' => 1,
                ]
            ]);

        $this->assertDatabaseHas('sales_orders', [
            'card_code' => 'C110003074',
            'status' => 'DRAFT',
            'doc_total' => 50000,
            'use_balance' => 1,
        ]);

        $this->assertDatabaseHas('sales_order_details', [
            'item_code' => 'E65',
            'line_total' => 50000,
        ]);
    }

    /**
     * Test updating a sales order draft.
     */
    public function test_update_sales_order_draft(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create draft order first
        $order = SalesOrder::create([
            'order_no' => 'SO-20260608-0001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-02-25',
            'slp_code' => 0,
            'doc_total' => 50000,
            'status' => 'DRAFT',
            'use_balance' => false,
        ]);
        $order->details()->create([
            'item_code' => 'E65',
            'quantity' => 10,
            'unit_price' => 5000,
            'line_total' => 50000,
        ]);

        $payload = [
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'po_number' => 'PO-UPDATED',
            'doc_date' => '2026-02-25',
            'use_balance' => true,
            'slp_code' => 0,
            'lines' => [
                [
                    'item_code' => 'E65',
                    'quantity' => 20,
                    'unit_price' => 5000,
                    'line_total' => 100000,
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/distributor-channel/v1/sales-orders/' . $order->id, $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sales order draft berhasil diperbarui.',
                'data' => [
                    'po_number' => 'PO-UPDATED',
                    'doc_total' => 100000,
                    'use_balance' => 1,
                ]
            ]);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'po_number' => 'PO-UPDATED',
            'doc_total' => 100000,
            'use_balance' => 1,
        ]);

        $this->assertDatabaseHas('sales_order_details', [
            'sales_order_id' => $order->id,
            'quantity' => 20,
            'line_total' => 100000,
        ]);
    }

    /**
     * Test deleting a sales order draft.
     */
    public function test_delete_sales_order_draft(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create draft order
        $order = SalesOrder::create([
            'order_no' => 'SO-20260608-0002',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-02-25',
            'slp_code' => 0,
            'doc_total' => 50000,
            'status' => 'DRAFT',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/distributor-channel/v1/sales-orders/' . $order->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sales order draft berhasil dihapus.',
            ]);

        $this->assertDatabaseMissing('sales_orders', [
            'id' => $order->id,
        ]);
    }

    /**
     * Test creating a sales order directly with WAITING_OM status via submit action.
     */
    public function test_create_sales_order_waiting_approval(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $payload = [
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-02-25',
            'slp_code' => 0,
            'action' => 'submit',
            'lines' => [
                [
                    'item_code' => 'E65',
                    'quantity' => 10,
                    'unit_price' => 5000,
                    'line_total' => 50000,
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/sales-orders', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'WAITING_OM',
                ]
            ]);

        $this->assertDatabaseHas('sales_orders', [
            'card_code' => 'C110003074',
            'status' => 'WAITING_OM',
        ]);
    }

    /**
     * Test sales order detail, create, and update returns sap discount details.
     */
    public function test_sales_order_with_sap_discount_details(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create SAP discount records
        $discountHeader = \App\Models\SapDiscountHeader::create([
            'discount_code' => 'DISC_TEST_001',
            'card_code' => 'C110003074',
            'card_name' => 'PT XYZ',
            'total_so' => 0.00,
            'user_id' => $this->user->id,
        ]);

        $discountDetail = \App\Models\SapDiscountDetail::create([
            'sap_discount_header_id' => $discountHeader->id,
            'type_discount' => 'Diskon Item',
            'percentage' => 10.00,
            'total_discount' => 1500000.00,
            'remarks' => 'TEST REMARKS',
        ]);

        $payload = [
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-02-25',
            'slp_code' => 0,
            'id_discount' => 'DISC_TEST_001',
            'lines' => [
                [
                    'item_code' => 'E65',
                    'quantity' => 10,
                    'unit_price' => 5000,
                    'line_total' => 50000,
                    'whs_code' => 'FG04',
                    'vat_group' => 'S1',
                    'ocr_code' => 'SBY',
                    'ocr_code2' => 'GRM',
                    'ocr_code3' => 'MKT',
                ]
            ]
        ];

        // 1. Verify CREATE returns sap_discount and master data names
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/sales-orders', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.sap_discount.discount_code', 'DISC_TEST_001')
            ->assertJsonPath('data.sap_discount.details.0.type_discount', 'Diskon Item')
            ->assertJsonPath('data.total_discount', 1500000)
            ->assertJsonPath('data.sales_employee_name', 'John Doe Sales')
            ->assertJsonPath('data.details.0.item_name', 'TOP 250 M @ 10 KG / BAL')
            ->assertJsonPath('data.details.0.whs_name', 'Finished Goods 04')
            ->assertJsonPath('data.details.0.vat_name', 'PPN 11%')
            ->assertJsonPath('data.details.0.ocr_name', 'Surabaya Depo')
            ->assertJsonPath('data.details.0.ocr_name2', 'Garam Division')
            ->assertJsonPath('data.details.0.ocr_name3', 'Marketing Team');

        $orderId = $response->json('data.id');

        // 2. Verify SHOW returns sap_discount and master data names
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/sales-orders/' . $orderId);

        $response->assertStatus(200)
            ->assertJsonPath('data.sap_discount.discount_code', 'DISC_TEST_001')
            ->assertJsonPath('data.sap_discount.details.0.type_discount', 'Diskon Item')
            ->assertJsonPath('data.total_discount', 1500000)
            ->assertJsonPath('data.sales_employee_name', 'John Doe Sales')
            ->assertJsonPath('data.details.0.item_name', 'TOP 250 M @ 10 KG / BAL')
            ->assertJsonPath('data.details.0.whs_name', 'Finished Goods 04')
            ->assertJsonPath('data.details.0.vat_name', 'PPN 11%')
            ->assertJsonPath('data.details.0.ocr_name', 'Surabaya Depo')
            ->assertJsonPath('data.details.0.ocr_name2', 'Garam Division')
            ->assertJsonPath('data.details.0.ocr_name3', 'Marketing Team');

        // 3. Verify UPDATE returns sap_discount and master data names
        $payload['po_number'] = 'NEW_PO_123';
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/distributor-channel/v1/sales-orders/' . $orderId, $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.sap_discount.discount_code', 'DISC_TEST_001')
            ->assertJsonPath('data.sap_discount.details.0.type_discount', 'Diskon Item')
            ->assertJsonPath('data.total_discount', 1500000)
            ->assertJsonPath('data.sales_employee_name', 'John Doe Sales')
            ->assertJsonPath('data.details.0.item_name', 'TOP 250 M @ 10 KG / BAL')
            ->assertJsonPath('data.details.0.whs_name', 'Finished Goods 04')
            ->assertJsonPath('data.details.0.vat_name', 'PPN 11%')
            ->assertJsonPath('data.details.0.ocr_name', 'Surabaya Depo')
            ->assertJsonPath('data.details.0.ocr_name2', 'Garam Division')
            ->assertJsonPath('data.details.0.ocr_name3', 'Marketing Team');
    }

    /**
     * Test creating a sales order draft with a PDF attachment.
     */
    public function test_create_sales_order_with_attachment(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $token = $this->user->createToken('test_token')->plainTextToken;

        $file = \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $payload = [
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'po_number' => 'PO-ATTACH-123',
            'doc_date' => '2026-02-25',
            'slp_code' => 0,
            'lines' => [
                [
                    'item_code' => 'E65',
                    'quantity' => 10,
                    'unit_price' => 5000,
                    'line_total' => 50000,
                ]
            ],
            'attachment' => $file,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/api/distributor-channel/v1/sales-orders', $payload); // multipart form-data needs post() instead of postJson()

        $response->assertStatus(200)
            ->assertJsonPath('data.po_number', 'PO-ATTACH-123')
            ->assertJsonPath('data.attachments.0.file_name', 'document.pdf');

        $orderId = $response->json('data.id');

        $this->assertDatabaseHas('sales_order_attachments', [
            'sales_order_id' => $orderId,
            'file_name' => 'document.pdf',
        ]);

        $attachment = \App\Models\SalesOrderAttachment::where('sales_order_id', $orderId)->first();
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($attachment->file_path);
    }

    /**
     * Test that approving an order at STAGE_WAITING_ADMIN_SALES updates the vat_group.
     */
    public function test_approve_order_updates_vat_group_at_admin_sales_stage(): void
    {
        $salesRole = \App\Models\Role::create([
            'name' => 'admin sales',
            'is_active' => true,
        ]);
        \App\Models\RoleMenu::create([
            'role_id' => $salesRole->id,
            'menu' => ['order-list'],
            'is_active' => true,
            'approval_id' => 4, // STAGE_WAITING_ADMIN_SALES
        ]);
        $salesUser = User::create([
            'name' => 'Admin Sales User',
            'username' => 'salesuser',
            'email' => 'sales@example.com',
            'password' => Hash::make($this->password),
            'role_id' => $salesRole->id,
            'is_active' => true,
        ]);

        \App\Models\Vat::create([
            'code' => 'S2',
            'name' => 'PPN S2',
            'rate' => 11.00,
            'status' => 1,
        ]);

        $order = SalesOrder::create([
            'order_no' => 'SO-TEST-VAT-001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-02-25',
            'slp_code' => 0,
            'doc_total' => 50000,
            'status' => 'WAITING_ADMIN_SALES',
            'approval_id' => 4, // STAGE_WAITING_ADMIN_SALES
        ]);
        $detail = $order->details()->create([
            'item_code' => 'E65',
            'quantity' => 10,
            'unit_price' => 5000,
            'line_total' => 50000,
            'vat_group' => 'S1',
        ]);

        $token = $salesUser->createToken('test_token')->plainTextToken;

        $payload = [
            'action' => 'approve',
            'lines' => [
                [
                    'item_code' => 'E65',
                    'vat_group' => 'S2',
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/distributor-channel/v1/sales-orders/' . $order->id, $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('sales_order_details', [
            'id' => $detail->id,
            'vat_group' => 'S2',
        ]);
    }

    /**
     * Test that saving discounts at STAGE_WAITING_ADMIN_SALES updates the vat_group.
     */
    public function test_save_discounts_updates_vat_group(): void
    {
        $salesRole = \App\Models\Role::create([
            'name' => 'admin sales',
            'is_active' => true,
        ]);
        \App\Models\RoleMenu::create([
            'role_id' => $salesRole->id,
            'menu' => ['order-list'],
            'is_active' => true,
            'approval_id' => 4, // STAGE_WAITING_ADMIN_SALES
        ]);
        $salesUser = User::create([
            'name' => 'Admin Sales User',
            'username' => 'salesuser2',
            'email' => 'sales2@example.com',
            'password' => Hash::make($this->password),
            'role_id' => $salesRole->id,
            'is_active' => true,
        ]);

        \App\Models\Vat::create([
            'code' => 'S2',
            'name' => 'PPN S2',
            'rate' => 11.00,
            'status' => 1,
        ]);

        $order = SalesOrder::create([
            'order_no' => 'SO-TEST-VAT-002',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-02-25',
            'slp_code' => 0,
            'doc_total' => 50000,
            'status' => 'WAITING_ADMIN_SALES',
            'approval_id' => 4, // STAGE_WAITING_ADMIN_SALES
        ]);
        $detail = $order->details()->create([
            'item_code' => 'E65',
            'quantity' => 10,
            'unit_price' => 5000,
            'line_total' => 50000,
            'vat_group' => 'S1',
        ]);

        $token = $salesUser->createToken('test_token')->plainTextToken;

        $payload = [
            'lines' => [
                [
                    'item_code' => 'E65',
                    'vat_group' => 'S2',
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/sales-orders/' . $order->id . '/save-discounts', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('sales_order_details', [
            'id' => $detail->id,
            'vat_group' => 'S2',
        ]);
    }

    /**
     * Test downloading sales order proforma invoice PDF.
     */
    public function test_download_sales_order_pdf(): void
    {
        $order = SalesOrder::create([
            'order_no' => 'SO-TEST-PDF-001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-02-25',
            'slp_code' => 0,
            'doc_total' => 50000,
            'status' => 'DRAFT',
            'approval_id' => 1,
        ]);
        $order->details()->create([
            'item_code' => 'E65',
            'quantity' => 10,
            'unit_price' => 5000,
            'line_total' => 50000,
            'vat_group' => 'S1',
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('/api/distributor-channel/v1/sales-orders/' . $order->id . '/pdf');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotEmpty($response->getContent());
    }

    /**
     * Test updating a sales order draft with a new PDF attachment.
     */
    public function test_update_sales_order_draft_attachment(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        
        // 1. Create a draft with an initial attachment
        $order = \App\Models\SalesOrder::create([
            'order_no' => 'SO-TEST-UPDATE-ATT',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-02-25',
            'status' => 'DRAFT',
            'approval_id' => 1,
            'doc_total' => 50000,
        ]);
        
        $order->details()->create([
            'item_code' => 'E65',
            'quantity' => 10,
            'unit_price' => 5000,
            'line_total' => 50000,
        ]);
        
        // Create initial attachment file
        $oldFile = \Illuminate\Http\UploadedFile::fake()->create('old_doc.pdf', 100, 'application/pdf');
        $oldPath = $oldFile->storeAs('attachments/order', 'old_doc.pdf', 'public');
        
        $order->attachments()->create([
            'file_name' => 'old_doc.pdf',
            'file_path' => $oldPath,
            'file_type' => 'application/pdf',
            'file_size' => 100000,
            'uploaded_by' => $this->user->id,
        ]);

        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($oldPath);

        // 2. Prepare update payload with a new file
        $newFile = \Illuminate\Http\UploadedFile::fake()->create('new_doc.pdf', 200, 'application/pdf');
        
        $payload = [
            '_method' => 'PUT',
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'po_number' => 'PO-ATTACH-UPDATED',
            'doc_date' => '2026-02-25',
            'lines' => [
                [
                    'item_code' => 'E65',
                    'quantity' => 10,
                    'unit_price' => 5000,
                    'line_total' => 50000,
                ]
            ],
            'attachment' => $newFile,
        ];

        $token = $this->user->createToken('test_token')->plainTextToken;

        // Perform request using POST with _method=PUT to emulate multipart PUT
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/api/distributor-channel/v1/sales-orders/' . $order->id, $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.po_number', 'PO-ATTACH-UPDATED')
            ->assertJsonPath('data.attachments.0.file_name', 'new_doc.pdf');

        // 3. Verify old file is deleted and new file exists
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($oldPath);
        
        $newAttachment = \App\Models\SalesOrderAttachment::where('sales_order_id', $order->id)->first();
        $this->assertEquals('new_doc.pdf', $newAttachment->file_name);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($newAttachment->file_path);
    }

    /**
     * Test checking ETA API.
     */
    public function test_check_eta_api(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create a few sales orders with different eta_dates and card_codes
        SalesOrder::create([
            'order_no' => 'SO-ETA-0001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-02-25',
            'eta_date' => '2026-03-01',
            'slp_code' => 0,
            'doc_total' => 50000,
            'status' => 'DRAFT',
        ]);

        SalesOrder::create([
            'order_no' => 'SO-ETA-0002',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-02-25',
            'eta_date' => '2026-03-10',
            'slp_code' => 0,
            'doc_total' => 50000,
            'status' => 'DRAFT',
        ]);

        SalesOrder::create([
            'order_no' => 'SO-ETA-0003',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C999999999',
            'customer_name' => 'PT OTHER',
            'doc_date' => '2026-02-25',
            'eta_date' => '2026-03-05',
            'slp_code' => 0,
            'doc_total' => 50000,
            'status' => 'DRAFT',
        ]);

        // 1. Missing eta_date_request parameter should trigger validation failure (returned as 200 with status_code 422)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/sales-orders/check-eta');

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status_code' => 422,
                'message' => 'The eta date request field is required.',
            ]);

        // 2. Querying with eta_date_request = '2026-03-06' should return orders 1 and 3 (eta_date < 2026-03-06)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/sales-orders/check-eta?eta_date_request=2026-03-06');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data sales order berdasarkan cek ETA berhasil diambil.',
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $orderNumbers = collect($data)->pluck('order_no')->toArray();
        $this->assertContains('SO-ETA-0001', $orderNumbers);
        $this->assertContains('SO-ETA-0003', $orderNumbers);
        $this->assertNotContains('SO-ETA-0002', $orderNumbers);

        // 3. Querying with eta_date_request = '2026-03-06' and customer_code = 'C110003074' should return only order 1
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/sales-orders/check-eta?eta_date_request=2026-03-06&customer_code=C110003074');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('SO-ETA-0001', $data[0]['order_no']);
    }

    /**
     * Test that getting the list of sales orders without a status filter excludes DRAFT orders by default,
     * but includes them if status=DRAFT is explicitly requested.
     */
    public function test_get_sales_orders_list_excludes_drafts_by_default(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Clear existing orders
        SalesOrder::query()->delete();

        // 1. Create a DRAFT order
        SalesOrder::create([
            'order_no' => 'SO-LIST-0001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-07-16',
            'slp_code' => 0,
            'doc_total' => 10000,
            'status' => 'DRAFT',
        ]);

        // 2. Create an APPROVED order
        SalesOrder::create([
            'order_no' => 'SO-LIST-0002',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-07-16',
            'slp_code' => 0,
            'doc_total' => 20000,
            'status' => 'APPROVED',
        ]);

        // 3. Query without status filter -> should only return the APPROVED order
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/sales-orders');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('SO-LIST-0002', $data[0]['order_no']);

        // 4. Query with status=DRAFT -> should return the DRAFT order
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/sales-orders?status=DRAFT');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('SO-LIST-0001', $data[0]['order_no']);
    }
}
