<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Distributor;
use App\Models\SalesOrder;
use App\Models\Item;
use App\Models\SalesOrderDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SalesOrderDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $distUser;
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
        $this->distUser = User::create([
            'name' => 'Distributor User',
            'username' => 'distuser',
            'email' => 'dist@example.com',
            'password' => Hash::make($this->password),
            'code_customer' => 'C110003074',
            'is_active' => true,
        ]);

        // Create admin user (no code_customer)
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'adminuser',
            'email' => 'admin@example.com',
            'password' => Hash::make($this->password),
            'is_active' => true,
        ]);

        // Create master item
        Item::create([
            'item_code' => 'E65',
            'item_name' => 'TOP 250 M @ 10 KG / BAL',
            'status' => 1,
        ]);

        // Seed Master Approvals
        $this->seed(\Database\Seeders\MasterApprovalSeeder::class);

        // Create a few sales orders for this month
        $so = SalesOrder::create([
            'order_no' => 'SO-001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => now()->toDateString(),
            'doc_total' => 150000.00,
            'status' => 'APPROVED',
            'approval_id' => 6,
        ]);

        SalesOrderDetail::create([
            'sales_order_id' => $so->id,
            'item_code' => 'E65',
            'quantity' => 10.0000,
            'unit_price' => 15000.00,
            'line_total' => 150000.00,
        ]);
    }

    public function test_admin_dashboard_returns_all_metrics(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/sales-orders/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'sales_summary' => [
                        'total_revenue_this_month',
                        'total_volume_kg_this_month',
                        'total_orders_this_month',
                    ],
                    'order_statuses',
                    'daily_sales_trend',
                    'top_products',
                    'top_distributors'
                ]
            ]);

        $this->assertEquals(150000.00, $response->json('data.sales_summary.total_revenue_this_month'));
        $this->assertEquals(10.00, $response->json('data.sales_summary.total_volume_kg_this_month'));
        $this->assertEquals(1, $response->json('data.sales_summary.total_orders_this_month'));
        $this->assertNotEmpty($response->json('data.top_distributors'));
    }

    public function test_distributor_dashboard_filters_by_distributor(): void
    {
        $response = $this->actingAs($this->distUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/sales-orders/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'sales_summary' => [
                        'total_revenue_this_month',
                        'total_volume_kg_this_month',
                        'total_orders_this_month',
                    ],
                    'order_statuses',
                    'daily_sales_trend',
                    'top_products'
                ]
            ]);

        // Distributor must NOT see top_distributors section
        $response->assertJsonMissingPath('data.top_distributors');
    }
}
