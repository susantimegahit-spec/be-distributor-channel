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

        $discount1 = \App\Models\SapDiscountHeader::create([
            'discount_code' => 'DISC123',
            'card_code' => 'C110003074',
            'card_name' => 'LESAFFRE SARI',
            'total_so' => 0,
            'user_id' => $this->user->id,
        ]);
        $discount1->details()->create([
            'type_discount' => 'Diskon Item',
            'percentage' => 0,
            'total_discount' => 3000000,
            'remarks' => 'DISC SEMARAK AWAL THN',
        ]);

        $discount2 = \App\Models\SapDiscountHeader::create([
            'discount_code' => 'NEWDISC',
            'card_code' => 'C110003074',
            'card_name' => 'LESAFFRE SARI',
            'total_so' => 0,
            'user_id' => $this->user->id,
        ]);
        $discount2->details()->create([
            'type_discount' => 'Diskon Item',
            'percentage' => 0,
            'total_discount' => 5000000,
            'remarks' => 'DISC 2',
        ]);

        $this->seed(\Database\Seeders\MasterApprovalSeeder::class);
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
            'series' => '4185',
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

        // Mock SAP addso and addudodiskon endpoints
        Http::fake([
            '103.18.133.187:3100/api/addudodiskon' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Discount added successfully',
            ], 200),
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
            'status' => 'ORDER_APPROVED',
            'sap_doc_entry' => 9999,
            'sap_doc_num' => 'SO9999',
            'series' => '4185',
            'sap_error' => null,
        ]);

        $this->assertDatabaseHas('sales_order_integration_logs', [
            'sales_order_id' => $order->id,
            'status' => 'SUCCESS',
            'error_message' => null,
        ]);
    }

    /**
     * Test sending Sales Order successfully when DocNum is returned in the Message string.
     */
    public function test_send_sales_order_to_sap_success_with_docnum_in_message(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create draft order first
        $order = SalesOrder::create([
            'order_no' => 'SO-20260611-0003',
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
            'whs_code' => 'FG04',
            'vat_group' => 'S1',
            'disc_percent' => 0,
            'uom_entry' => 1,
        ]);

        // Mock SAP addso and addudodiskon endpoints where DocNum is in the Message
        Http::fake([
            '103.18.133.187:3100/api/addudodiskon' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Discount added successfully',
            ], 200),
            '103.18.133.187:3100/api/addso' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Sales Order added successfully. DocNum: 21414212, DocEntry: 123456',
                'Result' => []
            ], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/distributor-channel/v1/sales-orders/{$order->id}/post-sap");

        $response->assertStatus(200);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'ORDER_APPROVED',
            'sap_doc_entry' => 123456,
            'sap_doc_num' => '21414212',
            'sap_error' => null,
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
            'status' => 'DRAFT',
            'sap_error' => 'API SAP addso mengembalikan error: Failed to create Sales Order in SAP',
        ]);

        $this->assertDatabaseHas('sales_order_integration_logs', [
            'sales_order_id' => $order->id,
            'status' => 'FAILED',
            'error_message' => 'API SAP addso mengembalikan error: Failed to create Sales Order in SAP',
        ]);
    }

    /**
     * Test sending Sales Order with payload update successfully to SAP.
     */
    public function test_post_sap_with_payload_update_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create draft order first
        $order = SalesOrder::create([
            'order_no' => 'SO-20260611-0003',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'LESAFFRE SARI',
            'doc_date' => '2026-02-25',
            'doc_due_date' => '2026-02-25',
            'slp_code' => 1,
            'cntct_code' => -1,
            'doc_total' => 50000,
            'status' => 'DRAFT',
        ]);
        $detail = $order->details()->create([
            'item_code' => 'E65',
            'quantity' => 10,
            'unit_price' => 5000,
            'line_total' => 50000,
            'whs_code' => 'FG04',
            'vat_group' => 'S1',
            'disc_percent' => 0,
            'uom_entry' => 1,
        ]);

        // Mock SAP addso and addudodiskon endpoints
        Http::fake([
            '103.18.133.187:3100/api/addudodiskon' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Discount added successfully',
            ], 200),
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

        // Post with payload update, changing WhsCode, SlpCode (empty string test), and IdDiskon
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/distributor-channel/v1/sales-orders/{$order->id}/post-sap", [
            'CardCode' => 'C110003074',
            'NumAtCard' => 'SO-20260611-0003',
            'DocDate' => '2026-02-25',
            'SlpCode' => '', // should normalize to null
            'IdDiskon' => 'DISC123',
            'lines' => [
                [
                    'ItemCode' => 'E65',
                    'Quantity' => 10.0,
                    'WhsCode' => 'RMI02', // changed warehouse
                    'UnitPrice' => 5000.0,
                    'LineTotal' => 50000.0,
                ]
            ]
        ]);

        $response->assertStatus(200);

        // Verify that local DB has updated values
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'ORDER_APPROVED',
            'slp_code' => null, // empty string normalized to null
            'id_discount' => 'DISC123',
        ]);

        $this->assertDatabaseHas('sales_order_details', [
            'sales_order_id' => $order->id,
            'item_code' => 'E65',
            'whs_code' => 'RMI02', // updated
        ]);
    }

    /**
     * Test creating and sending new Sales Order successfully to SAP (No ID).
     */
    public function test_post_new_sap_without_id_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Mock SAP addso and addudodiskon endpoints
        Http::fake([
            '103.18.133.187:3100/api/addudodiskon' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Discount added successfully',
            ], 200),
            '103.18.133.187:3100/api/addso' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Sales Order added successfully',
                'Result' => [
                    [
                        'DocEntry' => 8888,
                        'DocNum' => 'SO8888'
                    ]
                ]
            ], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/distributor-channel/v1/sales-orders/post-sap", [
            'CardCode' => 'C110003074',
            'NumAtCard' => '100003',
            'DocDate' => '2026-06-15',
            'DocDueDate' => '2026-06-30',
            'SlpCode' => '',
            'IdDiskon' => 'NEWDISC',
            'lines' => [
                [
                    'ItemCode' => 'E65',
                    'Quantity' => 20.0,
                    'WhsCode' => 'RMI02',
                    'UnitPrice' => 5000.0,
                    'LineTotal' => 100000.0,
                ]
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sales order berhasil dikirim ke SAP.',
            ]);

        // Verify local DB creation
        $this->assertDatabaseHas('sales_orders', [
            'card_code' => 'C110003074',
            'status' => 'ORDER_APPROVED',
            'sap_doc_entry' => 8888,
            'sap_doc_num' => 'SO8888',
            'id_discount' => 'NEWDISC',
        ]);

        $order = SalesOrder::where('sap_doc_entry', 8888)->first();
        $this->assertNotNull($order);

        $this->assertDatabaseHas('sales_order_details', [
            'sales_order_id' => $order->id,
            'item_code' => 'E65',
            'whs_code' => 'RMI02',
            'quantity' => 20.0,
        ]);
    }

    /**
     * Test sending Sales Order failing during discount integration.
     */
    public function test_post_sap_with_discount_failure(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create draft order first
        $order = SalesOrder::create([
            'order_no' => 'SO-20260611-0004',
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

        // Mock SAP addudodiskon endpoint to return failure
        Http::fake([
            '103.18.133.187:3100/api/addudodiskon' => Http::response([
                'ErrorCode' => -2002,
                'Message' => 'Discount UDO creation failed',
            ], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/distributor-channel/v1/sales-orders/{$order->id}/post-sap");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'API SAP addudodiskon mengembalikan error: Discount UDO creation failed',
            ]);

        // Verify that local order has failed status and details of discount integration failure
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'DRAFT',
            'sap_error' => 'API SAP addudodiskon mengembalikan error: Discount UDO creation failed',
        ]);
    }

    /**
     * Test getting series successfully.
     */
    public function test_get_series_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        Http::fake([
            '103.18.133.187:3100/api/getSeries' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'Series' => '4221',
                        'SeriesName' => 'BJM26-01'
                    ],
                    [
                        'Series' => '4185',
                        'SeriesName' => 'SBY26-01'
                    ]
                ]
            ], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/distributor-channel/v1/sales-orders/series?CustomQuery=20260101");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.1.Series', '4185');
    }

    /**
     * Test getting credit limit successfully.
     */
    public function test_get_credit_limit_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        Http::fake([
            '103.18.133.187:3100/api/getCreditlimit' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'CardCode' => 'C110003175',
                        'CardName' => 'PT SUSANTI MEGAH',
                        'CreditLimit' => 500000000,
                        'SisaCredit' => '250000.000000'
                    ]
                ]
            ], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/distributor-channel/v1/sales-orders/credit-limit?CustomQuery=C110003175");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.CardCode', 'C110003175')
            ->assertJsonPath('data.0.SisaCredit', '250000,000000');
    }
}
