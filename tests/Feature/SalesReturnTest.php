<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Warehouse;
use App\Models\Distributor;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\SalesReturn;
use App\Models\SalesReturnDetail;
use App\Models\SalesReturnAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SalesReturnTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Distributor $distributor;
    protected SalesOrder $salesOrder;
    protected SalesOrderDetail $salesOrderDetail;
    protected string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create([
            'id' => 1,
            'name' => 'administrator',
            'is_active' => true,
            'accessible_systems' => ['distributor'],
        ]);

        Role::create([
            'id' => 2,
            'name' => 'distributor',
            'is_active' => true,
            'accessible_systems' => ['distributor'],
        ]);

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
            'role_id' => 2,
            'is_active' => true,
        ]);

        $this->seed(\Database\Seeders\MasterApprovalSeeder::class);

        // Create Sales Order
        $this->salesOrder = SalesOrder::create([
            'order_no' => 'SO-20260608-0001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => '2026-02-25',
            'slp_code' => 0,
            'doc_total' => 5000,
            'status' => 'ARRIVED',
            'use_balance' => false,
        ]);

        // Create Sales Order Detail
        $this->salesOrderDetail = $this->salesOrder->details()->create([
            'item_code' => 'E65',
            'quantity' => 10,
            'unit_price' => 500,
            'line_total' => 5000,
        ]);
    }

    public function test_get_sales_returns_contains_file_url(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create Sales Return
        $salesReturn = SalesReturn::create([
            'return_no' => 'RET/202607/0001',
            'sales_order_id' => $this->salesOrder->id,
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'reason' => 'Damaged goods',
            'doc_total' => 5000,
            'status' => 'waiting_admin_sales',
            'submitted_at' => now(),
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        // Create Sales Return Detail
        $salesReturnDetail = SalesReturnDetail::create([
            'sales_return_id' => $salesReturn->id,
            'sales_order_detail_id' => $this->salesOrderDetail->id,
            'item_code' => 'E65',
            'quantity' => 1,
            'unit_price' => 5000,
            'line_total' => 5000,
            'status' => 'waiting_admin_sales',
        ]);

        // Create Sales Return Attachment
        $attachment = SalesReturnAttachment::create([
            'sales_return_id' => $salesReturn->id,
            'file_name' => 'evidence.jpg',
            'file_path' => 'returns/evidence.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
            'uploaded_by' => $this->user->id,
        ]);

        // Test Index Route
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/sales-returns');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertEquals('evidence.jpg', $data[0]['sales_return']['attachments'][0]['file_name']);
        $this->assertEquals(asset('storage/returns/evidence.jpg'), $data[0]['sales_return']['attachments'][0]['file_url']);

        // Test Show Route
        $showResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/sales-returns/' . $salesReturn->id);

        $showResponse->assertStatus(200);
        $showData = $showResponse->json('data');

        $this->assertEquals('evidence.jpg', $showData['attachments'][0]['file_name']);
        $this->assertEquals(asset('storage/returns/evidence.jpg'), $showData['attachments'][0]['file_url']);
    }

    public function test_approve_waiting_finance_integrates_sap_with_series(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Ensure user is admin (not distributor) to pass role check
        $this->user->update(['role_id' => 1]); // Admin / internal role

        // Set series_name on sales order
        $this->salesOrder->update(['series_name' => 'SBY26-07']);

        // Create return warehouse in default database
        Warehouse::create([
            'whs_code' => 'RT01',
            'whs_name' => 'Gudang Retur Surabaya',
            'status' => 1,
        ]);

        // Create Sales Return at waiting_finance stage
        $salesReturn = SalesReturn::create([
            'return_no' => 'RET/202607/0002',
            'sales_order_id' => $this->salesOrder->id,
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'reason' => 'Damaged goods',
            'doc_total' => 5000,
            'status' => 'waiting_finance',
            'submitted_at' => now(),
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        // Create Sales Return Detail with do_num and baseline saved
        $salesReturnDetail = SalesReturnDetail::create([
            'sales_return_id' => $salesReturn->id,
            'sales_order_detail_id' => $this->salesOrderDetail->id,
            'item_code' => 'E65',
            'quantity' => 1,
            'unit_price' => 5000,
            'line_total' => 5000,
            'do_num' => '260710004',
            'baseline' => 0,
        ]);

        // Mock SAP HTTP APIs
        \Illuminate\Support\Facades\Http::fake([
            'http://103.18.133.187:3100/api/getSeriesret' => \Illuminate\Support\Facades\Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    ['Series' => '4095']
                ]
            ], 200),
            'http://103.18.133.187:3100/api/addretur' => \Illuminate\Support\Facades\Http::response([
                'ErrorCode' => 0,
                'Message' => 'Success',
                'Result' => [
                    [
                        'DocEntry' => 12345,
                        'DocNum' => '260750001'
                    ]
                ]
            ], 200)
        ]);

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/distributor-channel/v1/sales-returns/{$salesReturn->id}/approve");

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'approved');
        $response->assertJsonPath('data.sap_doc_entry', 12345);
        $response->assertJsonPath('data.sap_doc_num', '260750001');

        // Check database state
        $this->assertDatabaseHas('sales_returns', [
            'id' => $salesReturn->id,
            'status' => 'approved',
            'sap_doc_entry' => 12345,
            'sap_doc_num' => '260750001',
        ]);

        // Assert HTTP requests
        \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->url() === 'http://103.18.133.187:3100/api/getSeriesret' &&
                   $request['CustomQuery'] === 'SBY';
        });

        \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->url() === 'http://103.18.133.187:3100/api/addretur' &&
                   $request['NoDO'] === 260710004 &&
                   $request['Series'] === 4095 &&
                   $request['Lines'][0]['BaseLine'] === 0 &&
                   $request['Lines'][0]['Quantity'] === 1.0 &&
                   $request['Lines'][0]['WhsCode'] === 'RT01';
        });
    }
}
