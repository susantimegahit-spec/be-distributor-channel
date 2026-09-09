<?php

namespace Tests\Feature;

use App\Models\CustomerMonthlyOrder;
use App\Models\CustomerMonthlyOrderDetail;
use App\Models\Distributor;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerMonthlyOrderPostToSoTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Distributor $distributor;
    protected CustomerMonthlyOrder $cmo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\MasterApprovalSeeder::class);

        $this->distributor = Distributor::create([
            'code_customer' => 'CUST-001',
            'name' => 'PT Mitra Distribusi',
            'depo' => 'SURABAYA',
        ]);

        $this->user = User::create([
            'name' => 'Distributor User',
            'username' => 'distributor1',
            'email' => 'dist1@example.com',
            'password' => bcrypt('password123'),
            'code_customer' => 'CUST-001',
            'is_active' => true,
        ]);

        Item::create([
            'item_code' => 'SKU-001',
            'item_name' => 'Garam Meja 250gr',
            'sal_unit_msr' => 'CTN',
        ]);

        $this->cmo = CustomerMonthlyOrder::create([
            'order_no' => 'CMO-202609-001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'CUST-001',
            'customer_name' => 'PT Mitra Distribusi',
            'doc_date' => '2026-09-01',
            'doc_due_date' => '2026-09-30',
            'eta_date' => '2026-10-02',
            'status' => 'DRAFT',
            'created_by' => $this->user->id,
        ]);

        CustomerMonthlyOrderDetail::create([
            'customer_monthly_order_id' => $this->cmo->id,
            'item_code' => 'SKU-001',
            'quantity' => 100,
            'unit_price' => 15000,
            'line_total' => 1500000,
        ]);
    }

    public function test_post_cmo_to_sales_order_updates_eta_date_and_request_delivery_date()
    {
        $payload = [
            'eta_date' => '2026-09-26',
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/customer-monthly-orders/{$this->cmo->id}/post-to-so", $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Customer monthly order successfully posted to Sales Order with status WAITING_OM.',
                'data' => [
                    'status' => 'WAITING_OM',
                    'approval_id' => 2,
                    'customer_monthly_order_id' => $this->cmo->id,
                ],
            ]);

        $this->assertStringStartsWith('2026-09-26', $response->json('data.doc_due_date'));
        $this->assertStringStartsWith('2026-09-26', $response->json('data.eta_date'));

        // Verify CMO database state
        $freshCmo = $this->cmo->fresh();
        $this->assertEquals('POSTED', $freshCmo->status);
        $this->assertEquals('2026-09-26', $freshCmo->doc_due_date->format('Y-m-d'));
        $this->assertEquals('2026-09-26', $freshCmo->eta_date->format('Y-m-d'));

        // Verify Sales Order database state
        $salesOrder = SalesOrder::where('customer_monthly_order_id', $this->cmo->id)->first();
        $this->assertNotNull($salesOrder);
        $this->assertEquals('WAITING_OM', $salesOrder->status);
        $this->assertEquals(2, $salesOrder->approval_id);
        $this->assertEquals('2026-09-26', $salesOrder->doc_due_date->format('Y-m-d'));
        $this->assertEquals('2026-09-26', $salesOrder->eta_date->format('Y-m-d'));
    }
}
