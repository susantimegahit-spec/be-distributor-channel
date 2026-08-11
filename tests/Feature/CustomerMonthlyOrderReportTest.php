<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Distributor;
use App\Models\Item;
use App\Models\CustomerMonthlyOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerMonthlyOrderReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $sbyUser;
    protected Distributor $sbyDistributor;
    protected Distributor $jktDistributor;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        // Create distributors
        $this->sbyDistributor = Distributor::create([
            'code_customer' => 'CUST-SBY-001',
            'name' => 'Distributor Sby PT',
            'depo' => 'SURABAYA',
        ]);

        $this->jktDistributor = Distributor::create([
            'code_customer' => 'CUST-JKT-001',
            'name' => 'Distributor Jkt PT',
            'depo' => 'JAKARTA',
        ]);

        // Create item with brand
        $this->item = Item::create([
            'item_code' => 'SKU-TEST-001',
            'item_name' => 'Barang Test 1',
            'price' => 50000,
            'sales_uom' => 'CTN',
            'brand' => 'BRAND-A',
        ]);

        // Create users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->sbyUser = User::create([
            'name' => 'Sby User',
            'username' => 'distsby',
            'email' => 'distsby@example.com',
            'password' => bcrypt('password123'),
            'code_customer' => 'CUST-SBY-001',
            'is_active' => true,
        ]);

        // Create monthly orders
        // 2025: Sby Order
        $order1 = CustomerMonthlyOrder::create([
            'distributor_id' => $this->sbyDistributor->id,
            'card_code' => 'CUST-SBY-001',
            'customer_name' => 'Distributor Sby PT',
            'order_no' => 'CMO-2025-001',
            'doc_date' => '2025-08-01',
            'doc_total' => 100000.00,
            'status' => 'APPROVED',
        ]);
        $order1->details()->create([
            'item_code' => 'SKU-TEST-001',
            'quantity' => 2.00,
            'unit_price' => 50000.00,
            'line_total' => 100000.00,
        ]);

        // 2026: Sby Order
        $order2 = CustomerMonthlyOrder::create([
            'distributor_id' => $this->sbyDistributor->id,
            'card_code' => 'CUST-SBY-001',
            'customer_name' => 'Distributor Sby PT',
            'order_no' => 'CMO-2026-001',
            'doc_date' => '2026-08-01',
            'doc_total' => 150000.00,
            'status' => 'APPROVED',
        ]);
        $order2->details()->create([
            'item_code' => 'SKU-TEST-001',
            'quantity' => 3.00,
            'unit_price' => 50000.00,
            'line_total' => 150000.00,
        ]);

        // 2026: Jkt Order
        $order3 = CustomerMonthlyOrder::create([
            'distributor_id' => $this->jktDistributor->id,
            'card_code' => 'CUST-JKT-001',
            'customer_name' => 'Distributor Jkt PT',
            'order_no' => 'CMO-2026-002',
            'doc_date' => '2026-09-01',
            'doc_total' => 200000.00,
            'status' => 'SUBMITTED',
        ]);
        $order3->details()->create([
            'item_code' => 'SKU-TEST-001',
            'quantity' => 4.00,
            'unit_price' => 50000.00,
            'line_total' => 200000.00,
        ]);
    }

    public function test_admin_can_retrieve_report_by_depo(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/customer-monthly-orders/reports/by-depo');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Laporan CMO per depo berhasil diambil.',
            ])
            ->assertJsonCount(2, 'data');

        $response->assertJsonPath('data.0.depo', 'SURABAYA')
            ->assertJsonPath('data.0.total_amount', 250000)
            ->assertJsonPath('data.0.total_orders', 2)
            ->assertJsonPath('data.1.depo', 'JAKARTA')
            ->assertJsonPath('data.1.total_amount', 200000)
            ->assertJsonPath('data.1.total_orders', 1);
    }

    public function test_distributor_only_retrieves_their_own_depo_in_report(): void
    {
        $response = $this->actingAs($this->sbyUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/customer-monthly-orders/reports/by-depo');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.depo', 'SURABAYA')
            ->assertJsonPath('data.0.total_amount', 250000)
            ->assertJsonPath('data.0.total_orders', 2);
    }

    public function test_report_by_depo_filters(): void
    {
        // Filter by status = APPROVED
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/customer-monthly-orders/reports/by-depo?status=APPROVED');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.depo', 'SURABAYA')
            ->assertJsonPath('data.0.total_amount', 250000);

        // Filter by year = 2026
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/customer-monthly-orders/reports/by-depo?year=2026');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.depo', 'JAKARTA')
            ->assertJsonPath('data.0.total_amount', 200000)
            ->assertJsonPath('data.1.depo', 'SURABAYA')
            ->assertJsonPath('data.1.total_amount', 150000);

        // Filter by month = 9
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/customer-monthly-orders/reports/by-depo?month=9');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.depo', 'JAKARTA')
            ->assertJsonPath('data.0.total_amount', 200000);
    }

    public function test_admin_can_retrieve_report_by_year_summary(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/customer-monthly-orders/reports/by-year');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Laporan CMO per tahun/bulan berhasil diambil.',
            ])
            ->assertJsonCount(2, 'data');

        $response->assertJsonPath('data.0.year', 2026)
            ->assertJsonPath('data.0.total_amount', 350000)
            ->assertJsonPath('data.0.total_orders', 2)
            ->assertJsonPath('data.1.year', 2025)
            ->assertJsonPath('data.1.total_amount', 100000)
            ->assertJsonPath('data.1.total_orders', 1);
    }

    public function test_admin_can_retrieve_report_by_year_monthly_breakdown(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/customer-monthly-orders/reports/by-year?year=2026');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'data');

        $response->assertJsonPath('data.0.month', 8)
            ->assertJsonPath('data.0.total_amount', 150000)
            ->assertJsonPath('data.1.month', 9)
            ->assertJsonPath('data.1.total_amount', 200000);
    }

    public function test_admin_can_retrieve_detailed_report(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/customer-monthly-orders/reports/detailed');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Laporan CMO detail per bulan berhasil diambil.',
            ])
            ->assertJsonCount(3, 'data'); // 3 distinct monthly order details (Sby 2025, Sby 2026, Jkt 2026)

        // Validate top item (Jkt 2026: 200000 > Sby 2026: 150000 > Sby 2025: 100000)
        $response->assertJsonPath('data.0.depo', 'JAKARTA')
            ->assertJsonPath('data.0.item_code', 'SKU-TEST-001')
            ->assertJsonPath('data.0.brand', 'BRAND-A')
            ->assertJsonPath('data.0.total_qty', 4)
            ->assertJsonPath('data.0.total_amount', 200000);
    }

    public function test_detailed_report_filters(): void
    {
        // Filter by depo = SURABAYA
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/customer-monthly-orders/reports/detailed?depo=SURABAYA');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Filter by brand = BRAND-A & year = 2026 & month = 8
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/customer-monthly-orders/reports/detailed?brand=BRAND-A&year=2026&month=8');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.depo', 'SURABAYA')
            ->assertJsonPath('data.0.year', 2026)
            ->assertJsonPath('data.0.month', 8)
            ->assertJsonPath('data.0.total_amount', 150000);
    }
}
