<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Distributor;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SalesOrderSapIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Role $role;
    protected Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::create([
            'name' => 'distributor',
            'is_active' => true,
        ]);

        $this->distributor = Distributor::create([
            'code_customer' => 'C110003074',
            'name' => 'LESAFFRE SARI',
            'status' => 1,
        ]);

        $this->user = User::create([
            'name' => 'Distributor User',
            'username' => 'distuser',
            'email' => 'dist@example.com',
            'password' => Hash::make('password123'),
            'code_customer' => 'C110003074',
            'is_active' => true,
        ]);
    }

    /**
     * Test sending Sales Order successfully to SAP.
     */
    public function test_send_sales_order_to_sap_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create draft order first
        $order = SalesOrder::create([
            'order_no' => 'SO-20260611-0001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'LESAFFRE SARI',
            'doc_date' => '2026-02-25',
            'doc_due_date' => '2026-02-25',
            'slp_code' => 1,
            'cntct_code' => -1,
            'doc_total' => 50000,
            'status' => 'DRAFT',
            'id_discount' => 'DISC123',
        ]);
        $order->details()->create([
            'item_code' => 'E65',
            'quantity' => 10,
            'unit_price' => 5000,
            'line_total' => 50000,
            'whs_code' => 'FG04',
            'vat_group' => 'S1',
            'disc_percent' => 0,
            'uom_entry' => 1,
        ]);

        // Mock SAP addso endpoint
        Http::fake([
            '103.18.133.187:3100/api/addso' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Sales Order added successfully',
                'Result' => [
                    [
                        'DocEntry' => 9999,
                        'DocNum' => 'SO9999'
                    ]
                ]
            ], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/distributor-channel/v1/sales-orders/{$order->id}/post-sap");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sales order berhasil dikirim ke SAP.',
                'data' => [
                    'ErrorCode' => 0,
                    'Message' => 'Sales Order added successfully',
                ]
            ]);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'APPROVED',
            'sap_doc_entry' => 9999,
            'sap_doc_num' => 'SO9999',
            'sap_error' => null,
        ]);

        $this->assertDatabaseHas('sales_order_integration_logs', [
            'sales_order_id' => $order->id,
            'status' => 'SUCCESS',
            'error_message' => null,
        ]);
    }

    /**
     * Test sending Sales Order failing in SAP.
     */
    public function test_send_sales_order_to_sap_failure(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create draft order first
        $order = SalesOrder::create([
            'order_no' => 'SO-20260611-0002',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'LESAFFRE SARI',
            'doc_date' => '2026-02-25',
            'doc_total' => 50000,
            'status' => 'DRAFT',
        ]);
        $order->details()->create([
            'item_code' => 'E65',
            'quantity' => 10,
            'unit_price' => 5000,
            'line_total' => 50000,
        ]);

        // Mock SAP addso failure
        Http::fake([
            '103.18.133.187:3100/api/addso' => Http::response([
                'ErrorCode' => -1001,
                'Message' => 'Failed to create Sales Order in SAP',
            ], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/distributor-channel/v1/sales-orders/{$order->id}/post-sap");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'API SAP addso mengembalikan error: Failed to create Sales Order in SAP',
            ]);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'FAILED',
            'sap_error' => 'API SAP addso mengembalikan error: Failed to create Sales Order in SAP',
        ]);

        $this->assertDatabaseHas('sales_order_integration_logs', [
            'sales_order_id' => $order->id,
            'status' => 'FAILED',
            'error_message' => 'API SAP addso mengembalikan error: Failed to create Sales Order in SAP',
        ]);
    }
}
