<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Distributor;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncSapOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Distributor $distributor;
    protected Distributor $otherDistributor;
    protected string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\MasterApprovalSeeder::class);

        // Create distributor A
        $this->distributor = Distributor::create([
            'code_customer' => 'C110003074',
            'name' => 'PT XYZ',
            'status' => 1,
        ]);

        $this->user = User::create([
            'name' => 'Distributor User',
            'username' => 'distuser',
            'email' => 'dist@example.com',
            'password' => Hash::make($this->password),
            'code_customer' => 'C110003074',
            'is_active' => true,
        ]);

        // Create distributor B
        $this->otherDistributor = Distributor::create([
            'code_customer' => 'C110003075',
            'name' => 'PT OTHER',
            'status' => 1,
        ]);

        $this->otherUser = User::create([
            'name' => 'Other User',
            'username' => 'otheruser',
            'email' => 'other@example.com',
            'password' => Hash::make($this->password),
            'code_customer' => 'C110003075',
            'is_active' => true,
        ]);
    }

    public function test_distributor_can_sync_own_order_status(): void
    {
        $order = SalesOrder::create([
            'order_no' => 'SO-TEST-001',
            'distributor_id' => $this->distributor->id,
            'card_code' => $this->distributor->code_customer,
            'customer_name' => $this->distributor->name,
            'doc_date' => now(),
            'status' => 'ORDER_APPROVED',
            'sap_doc_num' => '260130002',
            'approval_id' => 6,
        ]);

        Http::fake([
            'http://103.18.133.187:3100/api/Status' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'NOSO' => '260130002',
                        'StatusOrder' => 'open',
                        'Nomor' => '260130002',
                        'Doc' => 'AR'
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/sales-orders/{$order->id}/sync-sap");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $order->refresh();
        $this->assertEquals('open', $order->sap_status);
        $this->assertEquals('AR', $order->sap_last_doc_type);
        $this->assertEquals('260130002', $order->sap_last_doc_num);
        $this->assertEquals('ARRIVED', $order->status);
    }

    public function test_distributor_cannot_sync_others_order_status(): void
    {
        // Order belongs to other distributor
        $order = SalesOrder::create([
            'order_no' => 'SO-TEST-002',
            'distributor_id' => $this->otherDistributor->id,
            'card_code' => $this->otherDistributor->code_customer,
            'customer_name' => $this->otherDistributor->name,
            'doc_date' => now(),
            'status' => 'ORDER_APPROVED',
            'sap_doc_num' => '260130003',
            'approval_id' => 6,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/sales-orders/{$order->id}/sync-sap");

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Sales order tidak ditemukan.');
    }

    public function test_batch_sync_artisan_command(): void
    {
        $order1 = SalesOrder::create([
            'order_no' => 'SO-TEST-003',
            'distributor_id' => $this->distributor->id,
            'card_code' => $this->distributor->code_customer,
            'customer_name' => $this->distributor->name,
            'doc_date' => now(),
            'status' => 'ORDER_APPROVED',
            'sap_doc_num' => '260130004',
            'approval_id' => 6,
        ]);

        $order2 = SalesOrder::create([
            'order_no' => 'SO-TEST-004',
            'distributor_id' => $this->distributor->id,
            'card_code' => $this->distributor->code_customer,
            'customer_name' => $this->distributor->name,
            'doc_date' => now(),
            'status' => 'ORDER_APPROVED',
            'sap_doc_num' => '260130005',
            'approval_id' => 6,
        ]);

        Http::fake([
            'http://103.18.133.187:3100/api/Status' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'NOSO' => '260130004',
                        'StatusOrder' => 'open',
                        'Nomor' => 'DO-9999',
                        'Doc' => 'DO'
                    ],
                    [
                        'NOSO' => '260130005',
                        'StatusOrder' => 'Closed',
                        'Nomor' => 'INV-8888',
                        'Doc' => 'AR'
                    ]
                ]
            ], 200)
        ]);

        $this->artisan('sap:sync-order-status')
            ->expectsOutput('Starting SAP status synchronization...')
            ->expectsOutput('Batch sinkronisasi status selesai. Total updated: 2')
            ->assertExitCode(0);

        $order1->refresh();
        $this->assertEquals('open', $order1->sap_status);
        $this->assertEquals('DO', $order1->sap_last_doc_type);
        $this->assertEquals('DO-9999', $order1->sap_last_doc_num);
        $this->assertEquals('DELIVERY', $order1->status);

        $order2->refresh();
        $this->assertEquals('Closed', $order2->sap_status);
        $this->assertEquals('AR', $order2->sap_last_doc_type);
        $this->assertEquals('INV-8888', $order2->sap_last_doc_num);
        $this->assertEquals('ARRIVED', $order2->status);
    }

    public function test_sync_all_route(): void
    {
        $order1 = SalesOrder::create([
            'order_no' => 'SO-TEST-103',
            'distributor_id' => $this->distributor->id,
            'card_code' => $this->distributor->code_customer,
            'customer_name' => $this->distributor->name,
            'doc_date' => now(),
            'status' => 'ORDER_APPROVED',
            'sap_doc_num' => '260130104',
            'approval_id' => 6,
        ]);

        $order2 = SalesOrder::create([
            'order_no' => 'SO-TEST-104',
            'distributor_id' => $this->distributor->id,
            'card_code' => $this->distributor->code_customer,
            'customer_name' => $this->distributor->name,
            'doc_date' => now(),
            'status' => 'ORDER_APPROVED',
            'sap_doc_num' => '260130105',
            'approval_id' => 6,
        ]);

        Http::fake([
            'http://103.18.133.187:3100/api/Status' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'NOSO' => '260130104',
                        'StatusOrder' => 'open',
                        'Nomor' => 'DO-9999',
                        'Doc' => 'DO'
                    ],
                    [
                        'NOSO' => '260130105',
                        'StatusOrder' => 'Closed',
                        'Nomor' => 'INV-8888',
                        'Doc' => 'AR'
                    ]
                ]
            ], 200)
        ]);

        $response = $this->postJson("/api/distributor-channel/v1/sales-orders/sync-all");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.updated_count', 2);

        $order1->refresh();
        $this->assertEquals('open', $order1->sap_status);
        $this->assertEquals('DO', $order1->sap_last_doc_type);
        $this->assertEquals('DO-9999', $order1->sap_last_doc_num);
        $this->assertEquals('DELIVERY', $order1->status);

        $order2->refresh();
        $this->assertEquals('Closed', $order2->sap_status);
        $this->assertEquals('AR', $order2->sap_last_doc_type);
        $this->assertEquals('INV-8888', $order2->sap_last_doc_num);
        $this->assertEquals('ARRIVED', $order2->status);
    }
}
