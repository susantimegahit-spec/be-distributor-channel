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
                ]
            ]);

        $this->assertDatabaseHas('sales_orders', [
            'card_code' => 'C110003074',
            'status' => 'DRAFT',
            'doc_total' => 50000,
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
                ]
            ]);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'po_number' => 'PO-UPDATED',
            'doc_total' => 100000,
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
     * Test creating a sales order directly with WAITING_APPROVAL status.
     */
    public function test_create_sales_order_waiting_approval(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $payload = [
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-02-25',
            'slp_code' => 0,
            'status' => 'WAITING_APPROVAL',
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
                    'status' => 'WAITING_APPROVAL',
                ]
            ]);

        $this->assertDatabaseHas('sales_orders', [
            'card_code' => 'C110003074',
            'status' => 'WAITING_APPROVAL',
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
}
