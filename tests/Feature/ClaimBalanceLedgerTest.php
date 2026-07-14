<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Distributor;
use App\Models\MstProgram;
use App\Models\TrxProgramResult;
use App\Models\TrxProgramUpload;
use App\Models\TrxProgramUploadBatch;
use App\Models\TrxProgramWithdraw;
use App\Models\TrxClaimBalanceLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClaimBalanceLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected User $distributorUser;
    protected User $adminUser;
    protected Role $distributorRole;
    protected Role $adminRole;
    protected Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->distributorRole = Role::create([
            'name' => 'distributor',
            'is_active' => true,
            'accessible_systems' => ['distributor'],
        ]);

        $this->adminRole = Role::create([
            'name' => 'administrator',
            'is_active' => true,
            'accessible_systems' => ['distributor', 'ekspedisi'],
        ]);

        // Create distributor
        $this->distributor = Distributor::create([
            'code_customer' => 'C110003074',
            'name' => 'PT XYZ',
            'status' => 1,
        ]);

        // Create users
        $this->distributorUser = User::create([
            'name' => 'Distributor User',
            'username' => 'distuser',
            'email' => 'dist@example.com',
            'password' => Hash::make('password123'),
            'code_customer' => 'C110003074',
            'role_id' => $this->distributorRole->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'adminuser',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test getting ledger history requires authentication.
     */
    public function test_get_ledger_requires_auth(): void
    {
        $response = $this->getJson('/api/distributor-channel/v1/claims/balance-ledger');
        $response->assertStatus(401);
    }

    /**
     * Test distributor can fetch their own ledger but no adjustment creation.
     */
    public function test_distributor_access_rules(): void
    {
        // Populate some ledger data
        TrxClaimBalanceLedger::create([
            'customer_code' => 'C110003074',
            'ref_number' => 'WD-001',
            'transaction_date' => now()->toDateString(),
            'type' => 'WITHDRAW',
            'debit' => 0.00,
            'credit' => 100000.00,
            'running_balance' => -100000.00,
        ]);

        // Get ledger should succeed
        $response = $this->actingAs($this->distributorUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/claims/balance-ledger');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('PT XYZ', $response->json('data.data.0.customer_name'));
        $this->assertNull($response->json('data.data.0.depo'));

        // Post adjustment should succeed (no longer forbidden)
        $responseAdj = $this->actingAs($this->distributorUser, 'sanctum')
            ->postJson('/api/distributor-channel/v1/claims/balance-ledger/adjustment', [
                'customer_code' => 'C110003074',
                'adjustment_type' => 'DEBIT',
                'amount' => 500000.00,
                'description' => 'Bonus',
                'type' => 'CORRECTION',
            ]);

        $responseAdj->assertStatus(201);
    }

    /**
     * Test admin can create manual balance adjustment.
     */
    public function test_admin_can_create_adjustment(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/distributor-channel/v1/claims/balance-ledger/adjustment', [
                'customer_code' => 'C110003074',
                'adjustment_type' => 'DEBIT',
                'amount' => 500000.00,
                'description' => 'Manual Correction',
                'type' => 'CORRECTION',
                'ref_number' => 'REF-CUSTOM-123',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
            ]);

        $response->assertStatus(201);
        $this->assertEquals(500000.00, $response->json('data.debit'));
        $this->assertEquals(500000.00, $response->json('data.running_balance'));
        $this->assertEquals('REF-CUSTOM-123', $response->json('data.ref_number'));
        $this->assertEquals('2026-07-01', $response->json('data.claim_start'));
        $this->assertEquals('2026-07-31', $response->json('data.claim_end'));

        // Check DB
        $this->assertDatabaseHas('trx_claim_balance_ledger', [
            'customer_code' => 'C110003074',
            'type' => 'CORRECTION',
            'ref_number' => 'REF-CUSTOM-123',
            'debit' => 500000.00,
            'running_balance' => 500000.00,
            'description' => 'Manual Correction',
            'claim_start' => '2026-07-01',
            'claim_end' => '2026-07-31',
        ]);
    }

    /**
     * Test admin can create manual balance adjustment without amount.
     */
    public function test_admin_can_create_adjustment_without_amount(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/distributor-channel/v1/claims/balance-ledger/adjustment', [
                'customer_code' => 'C110003074',
                'adjustment_type' => 'DEBIT',
                'description' => 'Correction without amount',
                'type' => 'CORRECTION',
            ]);

        $response->assertStatus(201);
        $this->assertEquals(0.00, $response->json('data.debit'));
        $this->assertEquals(0.00, $response->json('data.running_balance'));

        // Check DB
        $this->assertDatabaseHas('trx_claim_balance_ledger', [
            'customer_code' => 'C110003074',
            'type' => 'CORRECTION',
            'debit' => 0.00,
            'running_balance' => 0.00,
            'description' => 'Correction without amount',
        ]);
    }

    /**
     * Test claim verification inserts to balance ledger.
     */
    public function test_claim_verification_updates_ledger(): void
    {
        // Create program
        $program = MstProgram::create([
            'program_code' => 'PRG-01',
            'program_name' => 'Program A',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'status' => 'ACTIVE',
        ]);

        // Create upload batch
        $batch = TrxProgramUploadBatch::create([
            'batch_no' => 'B-001',
            'file_name' => 'upload.xlsx',
            'uploaded_by' => 'admin',
        ]);

        // Create upload record
        $upload = TrxProgramUpload::create([
            'batch_id' => $batch->id,
            'customer_code' => 'C110003074',
            'item_code' => 'E65',
            'qty_kg' => 10.00,
            'customer_type' => 'GT',
            'transaction_date' => '2026-06-15',
        ]);

        // Create result record
        $result = TrxProgramResult::create([
            'upload_id' => $upload->id,
            'program_id' => $program->id,
            'customer_code' => 'C110003074',
            'total_diskon' => 250000.00,
            'status' => 'VALID_PROGRAM',
            'is_verified' => false,
        ]);

        // Verify result via endpoint
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/distributor-channel/v1/claims/results/verify', [
                'ids' => [$result->id],
                'is_verified' => true,
                'claim_type' => 'BULANAN',
            ]);

        $response->assertStatus(200);

        // Check ledger — ref_number = human-readable batch_no, batch_id = FK integer
        $this->assertDatabaseHas('trx_claim_balance_ledger', [
            'customer_code' => 'C110003074',
            'type'          => 'CLAIM',
            'ref_number'    => 'B-001',
            'batch_id'      => $batch->id,
            'debit'         => 250000.00,
            'claim_type'    => 'BULANAN',
            'running_balance' => 250000.00,
        ]);

        // Verify summary is updated using ledger
        $responseSummary = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/claims/reward-summary?customer_codes=C110003074');

        $responseSummary->assertStatus(200);
        $this->assertEquals(250000.00, $responseSummary->json('data.total_verified'));
        $this->assertEquals(250000.00, $responseSummary->json('data.available_balance'));

        // Fetch ledger list and verify batch_no is populated from relationship
        $responseLedger = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/claims/balance-ledger?customer_codes=C110003074');
        $responseLedger->assertStatus(200);
        $this->assertEquals('B-001', $responseLedger->json('data.data.0.batch_no'));
    }

    /**
     * Test withdrawal creation and rejection correctly updates ledger.
     */
    public function test_withdrawals_update_ledger(): void
    {
        // Set initial balance with adjustment
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/distributor-channel/v1/claims/balance-ledger/adjustment', [
                'customer_code' => 'C110003074',
                'adjustment_type' => 'DEBIT',
                'amount' => 1000000.00,
                'description' => 'Initial Balance',
                'type' => 'CORRECTION',
            ]);

        // Request withdraw via auth user (distributor)
        $responseWd = $this->actingAs($this->distributorUser, 'sanctum')
            ->postJson('/api/distributor-channel/v1/claims/withdraws', [
                'amount' => 400000.00,
            ]);

        $responseWd->assertStatus(201);
        $withdrawId = $responseWd->json('data.id');

        // Check ledger (balance should be 600,000)
        $this->assertDatabaseHas('trx_claim_balance_ledger', [
            'customer_code' => 'C110003074',
            'type' => 'WITHDRAW',
            'credit' => 400000.00,
            'running_balance' => 600000.00,
        ]);

        // Reject withdraw via admin
        $responseReject = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/distributor-channel/v1/claims/withdraws/{$withdrawId}/status", [
                'status' => 'REJECTED',
            ]);

        $responseReject->assertStatus(200);

        // Check ledger again (should refund 400,000, so running balance is 1,000,000)
        $this->assertDatabaseHas('trx_claim_balance_ledger', [
            'customer_code' => 'C110003074',
            'type' => 'CORRECTION',
            'debit' => 400000.00,
            'running_balance' => 1000000.00,
            'description' => 'Pengembalian dana penarikan ditolak: ' . $responseWd->json('data.withdraw_no'),
        ]);
    }
}
