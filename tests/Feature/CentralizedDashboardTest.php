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

class CentralizedDashboardTest extends TestCase
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

    public function test_admin_can_access_admin_summary_but_distributor_cannot(): void
    {
        // Admin - Should be 200
        $responseAdmin = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/dashboard/admin/summary');

        $responseAdmin->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'sales_summary',
                    'order_statuses',
                    'claims_summary'
                ]
            ]);

        // Distributor - Should be 403
        $responseDist = $this->actingAs($this->distUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/dashboard/admin/summary');

        $responseDist->assertStatus(403);
    }

    public function test_admin_can_access_admin_charts_but_distributor_cannot(): void
    {
        // Admin - Should be 200
        $responseAdmin = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/dashboard/admin/charts');

        $responseAdmin->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'daily_sales_trend',
                    'top_products',
                    'top_distributors'
                ]
            ]);

        // Distributor - Should be 403
        $responseDist = $this->actingAs($this->distUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/dashboard/admin/charts');

        $responseDist->assertStatus(403);
    }

    public function test_distributor_can_access_distributor_summary_but_admin_cannot(): void
    {
        // Distributor - Should be 200
        $responseDist = $this->actingAs($this->distUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/dashboard/distributor/summary');

        $responseDist->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'sales_summary',
                    'order_statuses',
                    'daily_sales_trend',
                    'top_products',
                    'rewards' => [
                        'total_accrued',
                        'available_balance',
                        'pending_verification',
                        'withdrawn'
                    ]
                ]
            ]);

        // Admin - Should be 403
        $responseAdmin = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/dashboard/distributor/summary');

        $responseAdmin->assertStatus(403);
    }
}
