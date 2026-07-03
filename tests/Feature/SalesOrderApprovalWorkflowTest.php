<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\RoleMenu;
use App\Models\Distributor;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SalesOrderApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Distributor $distributor;
    protected User $distributorUser;
    protected User $omUser;
    protected User $asmUser;
    protected User $adminSalesUser;
    protected User $financeUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed Master Approvals
        $this->seed(\Database\Seeders\MasterApprovalSeeder::class);

        // Create distributor
        $this->distributor = Distributor::create([
            'code_customer' => 'C110003074',
            'name' => 'PT XYZ',
            'status' => 1,
        ]);

        // Create Roles
        $roles = [];
        $names = ['distributor', 'om', 'asm', 'admin_sales', 'finance'];
        foreach ($names as $idx => $name) {
            $role = Role::create([
                'name' => $name,
                'is_active' => true,
            ]);

            // Link roles to their respective approval stages (index + 1)
            RoleMenu::create([
                'role_id' => $role->id,
                'menu' => [],
                'approval_id' => $idx + 1, // distributor = 1, om = 2, asm = 3, admin_sales = 4, finance = 5
            ]);

            $roles[$name] = $role;
        }

        // Create Users
        $this->distributorUser = User::create([
            'role_id' => $roles['distributor']->id,
            'name' => 'Distributor User',
            'username' => 'distuser',
            'email' => 'dist@example.com',
            'password' => Hash::make('password'),
            'code_customer' => 'C110003074',
            'is_active' => true,
        ]);

        $this->omUser = User::create([
            'role_id' => $roles['om']->id,
            'name' => 'OM User',
            'username' => 'omuser',
            'email' => 'om@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->asmUser = User::create([
            'role_id' => $roles['asm']->id,
            'name' => 'ASM User',
            'username' => 'asmuser',
            'email' => 'asm@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->adminSalesUser = User::create([
            'role_id' => $roles['admin_sales']->id,
            'name' => 'Admin Sales User',
            'username' => 'adminsalesuser',
            'email' => 'adminsales@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->financeUser = User::create([
            'role_id' => $roles['finance']->id,
            'name' => 'Finance User',
            'username' => 'financeuser',
            'email' => 'finance@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Mock SAP endpoints
        Http::fake([
            '103.18.133.187:3100/api/addso' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Success',
                'Result' => [['DocEntry' => 1234, 'DocNum' => 'SO1234']]
            ], 200),
        ]);
    }

    /**
     * Test full approval workflow happy path.
     */
    public function test_full_approval_happy_path(): void
    {
        Mail::fake();

        // 1. Create Draft (Distributor)
        $order = SalesOrder::create([
            'order_no' => 'SO-TEST-001',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => now(),
            'status' => 'DRAFT',
            'approval_id' => SalesOrder::STAGE_DRAFT,
            'doc_total' => 100000,
        ]);
        $order->details()->create([
            'item_code' => 'E65',
            'quantity' => 10,
            'unit_price' => 10000,
            'line_total' => 100000,
        ]);

        // 2. Submit to OM (Distributor)
        \Laravel\Sanctum\Sanctum::actingAs($this->distributorUser);
        $response = $this->putJson("/api/distributor-channel/v1/sales-orders/{$order->id}", [
            'action' => 'submit'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'WAITING_OM',
            'approval_id' => SalesOrder::STAGE_WAITING_OM,
        ]);

        // 3. Approve by OM -> Transitions to WAITING_ASM and sends Email
        \Laravel\Sanctum\Sanctum::actingAs($this->omUser);
        $response = $this->putJson("/api/distributor-channel/v1/sales-orders/{$order->id}", [
            'action' => 'approve'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'WAITING_ASM',
            'approval_id' => SalesOrder::STAGE_WAITING_ASM,
        ]);

        Mail::assertSent(\App\Mail\AsmApprovalNotificationMail::class);

        // 4. Approve by ASM -> Transitions to WAITING_ADMIN_SALES
        \Laravel\Sanctum\Sanctum::actingAs($this->asmUser);
        $response = $this->putJson("/api/distributor-channel/v1/sales-orders/{$order->id}", [
            'action' => 'approve'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'WAITING_ADMIN_SALES',
            'approval_id' => SalesOrder::STAGE_WAITING_ADMIN_SALES,
        ]);

        // 5. Admin Sales fills discounts -> Transitions to WAITING_FINANCE
        \Laravel\Sanctum\Sanctum::actingAs($this->adminSalesUser);
        $response = $this->postJson("/api/distributor-channel/v1/sales-orders/{$order->id}/save-discounts", [
            'lines' => [
                [
                    'item_code' => 'E65',
                    'disc_percent' => 10,
                ]
            ]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'WAITING_FINANCE',
            'approval_id' => SalesOrder::STAGE_WAITING_FINANCE,
            'doc_total' => 90000, // 10% discount applied
            'sales_pic_id' => $this->adminSalesUser->id,
        ]);

        // 6. Approve by Finance -> Transitions to COMPLETED & posts to SAP
        \Laravel\Sanctum\Sanctum::actingAs($this->financeUser);
        $response = $this->putJson("/api/distributor-channel/v1/sales-orders/{$order->id}", [
            'action' => 'approve'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'ORDER_APPROVED',
            'approval_id' => SalesOrder::STAGE_COMPLETED,
            'sap_doc_entry' => 1234,
            'sap_doc_num' => 'SO1234',
            'sales_pic_id' => $this->adminSalesUser->id,
        ]);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->url() === 'http://103.18.133.187:3100/api/addso' &&
                $request['UserId'] === $this->adminSalesUser->id &&
                $request['AddonId'] === 2;
        });
    }

    /**
     * Test workflow reject rollbacks.
     */
    public function test_workflow_rejections(): void
    {
        // Create order in WAITING_ASM stage
        $order = SalesOrder::create([
            'order_no' => 'SO-TEST-002',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => now(),
            'status' => 'WAITING_ASM',
            'approval_id' => SalesOrder::STAGE_WAITING_ASM,
            'doc_total' => 100000,
        ]);

        // Reject by ASM -> should rollback to DRAFT
        \Laravel\Sanctum\Sanctum::actingAs($this->asmUser);
        $response = $this->putJson("/api/distributor-channel/v1/sales-orders/{$order->id}", [
            'action' => 'reject',
            'notes' => 'Alasan tolak ASM'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'DRAFT',
            'approval_id' => SalesOrder::STAGE_DRAFT,
            'reject_reason' => 'Alasan tolak ASM',
        ]);
    }

    /**
     * Test role unauthorized checks.
     */
    public function test_role_unauthorized_checks(): void
    {
        // Create order in WAITING_ASM stage
        $order = SalesOrder::create([
            'order_no' => 'SO-TEST-003',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => now(),
            'status' => 'WAITING_ASM',
            'approval_id' => SalesOrder::STAGE_WAITING_ASM,
            'doc_total' => 100000,
        ]);

        // OM User (approval_id = 2) tries to approve WAITING_ASM (approval_id = 3) -> should fail
        \Laravel\Sanctum\Sanctum::actingAs($this->omUser);
        $response = $this->putJson("/api/distributor-channel/v1/sales-orders/{$order->id}", [
            'action' => 'approve'
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Anda tidak memiliki akses untuk menyetujui sales order pada tahap ini.',
        ]);
    }

    /**
     * Test that workflow rejection without notes defaults to 'Rejected'.
     */
    public function test_workflow_rejection_without_notes_defaults_to_rejected(): void
    {
        // Create order in WAITING_ASM stage
        $order = SalesOrder::create([
            'order_no' => 'SO-TEST-004',
            'distributor_id' => $this->distributor->id,
            'card_code' => 'C110003074',
            'customer_name' => 'PT XYZ',
            'doc_date' => now(),
            'status' => 'WAITING_ASM',
            'approval_id' => SalesOrder::STAGE_WAITING_ASM,
            'doc_total' => 100000,
        ]);

        // Reject by ASM without sending notes -> should rollback to DRAFT and set reject_reason to 'Rejected'
        \Laravel\Sanctum\Sanctum::actingAs($this->asmUser);
        $response = $this->putJson("/api/distributor-channel/v1/sales-orders/{$order->id}", [
            'action' => 'reject',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'DRAFT',
            'approval_id' => SalesOrder::STAGE_DRAFT,
            'reject_reason' => 'Rejected',
        ]);
    }
}
