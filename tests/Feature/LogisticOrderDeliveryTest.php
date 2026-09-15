<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Item;
use App\Models\MasterLeadtime;
use App\Models\SalesOrder;
use App\Models\SalesOrderLogisticLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LogisticOrderDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Distributor $distributor;
    protected SalesOrder $soWaitingFinance;
    protected SalesOrder $soOrderApproved;
    protected SalesOrder $soDraft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->distributor = Distributor::create([
            'code_customer' => 'CUST-001',
            'name' => 'PT Mitra Distribusi',
            'depo' => 'SURABAYA',
        ]);

        $this->user = User::create([
            'name' => 'Tim Logistik',
            'username' => 'logistik1',
            'email' => 'logistik@example.com',
            'password' => bcrypt('password123'),
            'role' => 'logistic',
            'code_customer' => 'CUST-001',
            'is_active' => true,
        ]);

        Item::firstOrCreate(
            ['item_code' => 'SKU-001'],
            ['item_name' => 'Garam Meja 250gr', 'sal_unit_msr' => 'CTN']
        );

        MasterLeadtime::create([
            'origin_warehouse_code' => 'WHS-SBY',
            'origin_warehouse_name' => 'Gudang Surabaya',
            'destination_code' => 'CUST-001',
            'destination_name' => 'Mitra Sby',
            'destination_city' => 'SURABAYA',
            'avg_lead_time_days' => 3,
            'status' => 'ACTIVE',
        ]);

        $this->soDraft = SalesOrder::create([
            'order_no' => 'SO-DRAFT-001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'CUST-001',
            'customer_name' => 'PT Mitra Distribusi',
            'doc_date' => '2026-09-10',
            'req_due_date' => '2026-09-15',
            'doc_due_date' => '2026-09-15',
            'eta_date' => '2026-09-18',
            'status' => 'DRAFT',
            'logistic_status' => 'PENDING',
        ]);

        $this->soWaitingFinance = SalesOrder::create([
            'order_no' => 'SO-WF-001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'CUST-001',
            'customer_name' => 'PT Mitra Distribusi',
            'doc_date' => '2026-09-11',
            'req_due_date' => '2026-09-16',
            'doc_due_date' => '2026-09-16',
            'eta_date' => '2026-09-19',
            'status' => 'WAITING_FINANCE',
            'logistic_status' => 'PENDING',
        ]);

        $this->soOrderApproved = SalesOrder::create([
            'order_no' => 'SO-OA-001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'CUST-001',
            'customer_name' => 'PT Mitra Distribusi',
            'doc_date' => '2026-09-12',
            'req_due_date' => '2026-09-17',
            'doc_due_date' => '2026-09-17',
            'eta_date' => '2026-09-20',
            'status' => 'ORDER_APPROVED',
            'logistic_status' => 'PENDING',
        ]);
    }

    public function test_get_logistic_orders_only_returns_waiting_finance_and_order_approved(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/distributor-channel/v1/logistic/orders');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Delivery orders retrieved successfully.',
            ]);

        $items = $response->json('data');
        $orderNumbers = collect($items)->pluck('order_no')->all();

        $this->assertContains('SO-WF-001', $orderNumbers);
        $this->assertContains('SO-OA-001', $orderNumbers);
        $this->assertNotContains('SO-DRAFT-001', $orderNumbers);

        $orderApprovedItem = collect($items)->firstWhere('order_no', 'SO-OA-001');
        $this->assertEquals(3, $orderApprovedItem['leadtime_info']['benchmark_lead_time_days']);
        $this->assertTrue($orderApprovedItem['can_logistic_approve']);
        $this->assertFalse($orderApprovedItem['can_sales_approve_reschedule']);
    }

    public function test_logistic_can_approve_order_when_order_approved(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/logistic/orders/{$this->soOrderApproved->id}/approve", [
                'notes' => 'Armada truk dan jadwal pengiriman sudah siap.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logistics delivery schedule confirmed and approved successfully.',
                'data' => [
                    'logistic_status' => 'APPROVED',
                ],
            ]);

        $freshSo = $this->soOrderApproved->fresh();
        $this->assertEquals('APPROVED', $freshSo->logistic_status);

        $log = SalesOrderLogisticLog::where('sales_order_id', $this->soOrderApproved->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('LOGISTIC_APPROVED', $log->action);
        $this->assertEquals('APPROVED', $log->to_status);
        $this->assertEquals($this->user->id, $log->user_id);
    }

    public function test_logistic_cannot_approve_order_when_waiting_finance(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/logistic/orders/{$this->soWaitingFinance->id}/approve", [
                'notes' => 'Armada siap.',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertEquals('PENDING', $this->soWaitingFinance->fresh()->logistic_status);
    }

    public function test_logistic_can_reschedule_order_with_notes_and_dates(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/logistic/orders/{$this->soOrderApproved->id}/reschedule", [
                'proposed_delivery_date' => '2026-09-21',
                'proposed_eta_date' => '2026-09-24',
                'notes' => 'Armada truk ke Surabaya penuh hingga tanggal 20, diusulkan kirim tanggal 21.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Delivery schedule reschedule request submitted successfully. Awaiting sales admin review.',
                'data' => [
                    'logistic_status' => 'RESCHEDULE_REQUESTED',
                    'proposed_delivery_date' => '2026-09-21',
                    'proposed_eta_date' => '2026-09-24',
                ],
            ]);

        $freshSo = $this->soOrderApproved->fresh();
        $this->assertEquals('RESCHEDULE_REQUESTED', $freshSo->logistic_status);
        $this->assertEquals('2026-09-21', $freshSo->proposed_delivery_date->format('Y-m-d'));
        $this->assertEquals('2026-09-24', $freshSo->proposed_eta_date->format('Y-m-d'));

        // Original req_due_date remains unchanged until approved
        $this->assertEquals('2026-09-17', $freshSo->req_due_date->format('Y-m-d'));

        $log = SalesOrderLogisticLog::where('sales_order_id', $this->soOrderApproved->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('LOGISTIC_RESCHEDULE', $log->action);
        $this->assertEquals('RESCHEDULE_REQUESTED', $log->to_status);
    }

    public function test_admin_sales_can_approve_reschedule_and_updates_dates(): void
    {
        // First reschedule
        $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/logistic/orders/{$this->soOrderApproved->id}/reschedule", [
                'proposed_delivery_date' => '2026-09-22',
                'proposed_eta_date' => '2026-09-25',
                'notes' => 'Penyesuaian jadwal muat pabrik.',
            ]);

        // Then approve reschedule
        $response = $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/logistic/orders/{$this->soOrderApproved->id}/approve", [
                'notes' => 'Distributor setuju tanggal 22 September.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Rescheduled delivery schedule approved successfully. Delivery due date and ETA have been updated.',
                'data' => [
                    'logistic_status' => 'RESCHEDULE_APPROVED',
                    'req_due_date' => '2026-09-22',
                    'doc_due_date' => '2026-09-22',
                    'eta_date' => '2026-09-25',
                ],
            ]);

        $freshSo = $this->soOrderApproved->fresh();
        $this->assertEquals('RESCHEDULE_APPROVED', $freshSo->logistic_status);
        $this->assertEquals('2026-09-22', $freshSo->req_due_date->format('Y-m-d'));
        $this->assertEquals('2026-09-22', $freshSo->doc_due_date->format('Y-m-d'));
        $this->assertEquals('2026-09-25', $freshSo->eta_date->format('Y-m-d'));

        $logs = SalesOrderLogisticLog::where('sales_order_id', $this->soOrderApproved->id)->get();
        $this->assertCount(2, $logs);
        $this->assertEquals('ADMIN_SALES_APPROVED_RESCHEDULE', $logs->last()->action);
    }

    public function test_get_order_monitoring_logs(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/logistic/orders/{$this->soOrderApproved->id}/approve", [
                'notes' => 'Jadwal armada confirm.',
            ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/logistic/orders/{$this->soOrderApproved->id}/logs");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Delivery monitoring logs retrieved successfully.',
            ]);

        $logs = $response->json('data.logs');
        $this->assertCount(1, $logs);
        $this->assertEquals('LOGISTIC_APPROVED', $logs[0]['action']);
        $this->assertEquals('Tim Logistik', $logs[0]['user_name']);
    }

    public function test_get_dashboard_delivery_orders_returns_summary_and_tab_filtering(): void
    {
        // Approve soOrderApproved
        $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/logistic/orders/{$this->soOrderApproved->id}/approve", [
                'notes' => 'Armada ready.',
            ]);

        // Call Dashboard API with tab=approved
        $response = $this->actingAs($this->user)
            ->getJson('/api/distributor-channel/v1/logistic/orders/dashboard?tab=approved');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dashboard delivery orders retrieved successfully.',
            ]);

        $summary = $response->json('data.summary');
        $this->assertNotNull($summary);
        $this->assertArrayHasKey('total_orders', $summary);
        $this->assertArrayHasKey('pending_count', $summary);
        $this->assertArrayHasKey('logistic_approved_count', $summary);
        $this->assertArrayHasKey('reschedule_requested_count', $summary);
        $this->assertArrayHasKey('admin_approved_count', $summary);
        $this->assertArrayHasKey('total_approved_count', $summary);
        $this->assertArrayHasKey('total_reschedule_count', $summary);

        $this->assertGreaterThanOrEqual(1, $summary['logistic_approved_count']);
        $this->assertGreaterThanOrEqual(1, $summary['total_approved_count']);

        $orders = $response->json('data.orders');
        $orderNumbers = collect($orders)->pluck('order_no')->all();
        $this->assertContains('SO-OA-001', $orderNumbers);
        $this->assertNotContains('SO-WF-001', $orderNumbers); // SO-WF-001 is PENDING, should not appear in tab=approved
    }
}
