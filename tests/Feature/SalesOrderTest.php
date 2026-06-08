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
}
