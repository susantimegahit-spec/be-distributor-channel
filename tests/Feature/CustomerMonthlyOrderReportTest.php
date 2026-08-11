<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Distributor;
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
        CustomerMonthlyOrder::create([
            'distributor_id' => $this->sbyDistributor->id,
            'card_code' => 'CUST-SBY-001',
            'customer_name' => 'Distributor Sby PT',
            'order_no' => 'CMO-2025-001',
            'doc_date' => '2025-08-01',
            'doc_total' => 100000.00,
            'status' => 'APPROVED',
        ]);

        // 2026: Sby Order
        CustomerMonthlyOrder::create([
            'distributor_id' => $this->sbyDistributor->id,
            'card_code' => 'CUST-SBY-001',
            'customer_name' => 'Distributor Sby PT',
            'order_no' => 'CMO-2026-001',
            'doc_date' => '2026-08-01',
            'doc_total' => 150000.00,
            'status' => 'APPROVED',
        ]);

        // 2026: Jkt Order
        CustomerMonthlyOrder::create([
            'distributor_id' => $this->jktDistributor->id,
            'card_code' => 'CUST-JKT-001',
            'customer_name' => 'Distributor Jkt PT',
            'order_no' => 'CMO-2026-002',
            'doc_date' => '2026-09-01',
            'doc_total' => 200000.00,
            'status' => 'SUBMITTED',
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

        // Should be ordered by total_amount desc (Jakarta: 200000.00 > Surabaya: 250000.00? No, Surabaya total is 100000 + 150000 = 250000, so Surabaya should be first)
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
            ->assertJsonPath('data.0.depo', 'JAKARTA') // Jkt: 200000 > Sby 2026: 150000
            ->assertJsonPath('data.0.total_amount', 200000)
            ->assertJsonPath('data.1.depo', 'SURABAYA')
            ->assertJsonPath('data.1.total_amount', 150000);
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

        // Ordered by year desc (2026 then 2025)
        $response->assertJsonPath('data.0.year', 2026)
            ->assertJsonPath('data.0.total_amount', 350000) // 150000 + 200000
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

        // Sorted by month asc: August (8) -> Sept (9)
        $response->assertJsonPath('data.0.month', 8)
            ->assertJsonPath('data.0.total_amount', 150000)
            ->assertJsonPath('data.1.month', 9)
            ->assertJsonPath('data.1.total_amount', 200000);
    }
}
