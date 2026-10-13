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

        // Check DB
        $this->assertDatabaseHas('trx_claim_balance_ledger', [
            'customer_code' => 'C110003074',
            'type' => 'CORRECTION',
            'ref_number' => 'REF-CUSTOM-123',
            'debit' => 500000.00,
            'description' => 'Manual Correction',
            'claim_start' => '2026-07-01',
            'claim_end' => '2026-07-31',
        ]);

        // Assert balance
        $responseSummary = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/claims/reward-summary?customer_codes=C110003074');
        $this->assertEquals(500000.00, $responseSummary->json('data.available_balance'));
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

        // Check DB
        $this->assertDatabaseHas('trx_claim_balance_ledger', [
            'customer_code' => 'C110003074',
            'type' => 'CORRECTION',
            'debit' => 0.00,
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
            'upload_id'    => $upload->id,
            'program_id'   => $program->id,
            'customer_code'=> 'C110003074',
            'total_diskon' => 250000.00,
            'status'       => 'VALID_PROGRAM',
            'is_verified'  => false,
        ]);

        // Simulate: distributor already uploaded → a pending ledger row exists (debit=0)
        \App\Models\TrxClaimBalanceLedger::create([
            'customer_code'    => 'C110003074',
            'ref_number'       => 'B-001',
            'batch_id'         => $batch->id,
            'transaction_date' => now()->toDateString(),
            'type'             => 'CLAIM',
            'debit'            => 0.00,
            'credit'           => 0.00,
            'description'      => 'Klaim Program Program A',
        ]);

        // Verify result via endpoint (finance approves)
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/distributor-channel/v1/claims/results/verify', [
                'ids'        => [$result->id],
                'is_verified'=> true,
                'claim_type' => 'BULANAN',
            ]);

        $response->assertStatus(200);

        // Assert only ONE CLAIM row for this batch exists (updated, not duplicated)
        $this->assertDatabaseCount('trx_claim_balance_ledger', 1);

        // Check ledger — ref_number = human-readable batch_no, batch_id = FK integer
        $this->assertDatabaseHas('trx_claim_balance_ledger', [
            'customer_code'   => 'C110003074',
            'type'            => 'CLAIM',
            'ref_number'      => 'B-001',
            'batch_id'        => $batch->id,
            'debit'           => 250000.00,
            'claim_type'      => 'BULANAN',
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
        $this->assertEquals(1, $responseLedger->json('data.total'));
    }

    /**
     * Test withdrawal creation and rejection correctly updates ledger.
     */
    public function test_withdrawals_update_ledger(): void
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

        // Create verified result record
        $result = TrxProgramResult::create([
            'upload_id' => $upload->id,
            'program_id' => $program->id,
            'customer_code' => 'C110003074',
            'total_diskon' => 1000000.00,
            'status' => 'VALID_PROGRAM',
            'is_verified' => true,
        ]);

        // Record verified claim in ledger
        TrxClaimBalanceLedger::create([
            'customer_code'    => 'C110003074',
            'ref_number'       => 'B-001',
            'batch_id'         => $batch->id,
            'transaction_date' => now()->toDateString(),
            'type'             => 'CLAIM',
            'debit'            => 1000000.00,
            'credit'           => 0.00,
            'description'      => 'Klaim Program',
        ]);

        // Request withdraw via auth user (distributor) — starts as PENDING
        $responseWd = $this->actingAs($this->distributorUser, 'sanctum')
            ->postJson('/api/distributor-channel/v1/claims/withdraws', [
                'amount' => 400000.00,
                'lines' => [
                    [
                        'batch_id' => $batch->id,
                        'amount' => 400000.00,
                    ]
                ]
            ]);

        $responseWd->assertStatus(201);
        $withdrawId = $responseWd->json('data.id');

        // Check ledger: pending withdraw should create a ledger record with credit = line amount!
        $this->assertDatabaseHas('trx_claim_balance_ledger', [
            'customer_code'    => 'C110003074',
            'type'             => 'WITHDRAW',
            'batch_id'         => $batch->id,
            'credit'           => 400000.00,
            'referenceable_id' => $withdrawId,
        ]);

        $ledgerId = \App\Models\TrxClaimBalanceLedger::where('referenceable_id', $withdrawId)->value('id');

        // Verify available balance becomes 600,000 in ledger (since credit is 400k pending)
        $responseSummary = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/claims/reward-summary?customer_codes=C110003074');
        $this->assertEquals(600000.00, $responseSummary->json('data.available_balance'));

        // Try to withdraw again with an amount greater than true available balance (1,000,000 - 400,000 pending = 600,000)
        // This should fail due to pending withdrawal amount constraint check
        $this->actingAs($this->distributorUser, 'sanctum')
            ->postJson('/api/distributor-channel/v1/claims/withdraws', [
                'amount' => 700000.00,
                'lines' => [
                    [
                        'batch_id' => $batch->id,
                        'amount' => 700000.00,
                    ]
                ]
            ])
            ->assertStatus(422);

        // Approve withdraw via admin using the LEDGER ID (to test robust ID resolution)
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/distributor-channel/v1/claims/withdraws/{$ledgerId}/status", [
                'status' => 'APPROVED',
            ])
            ->assertStatus(200);

        // Check ledger: now withdrawal credit should be updated to 400k!
        $this->assertDatabaseHas('trx_claim_balance_ledger', [
            'customer_code' => 'C110003074',
            'type'          => 'WITHDRAW',
            'batch_id'      => $batch->id,
            'credit'        => 400000.00,
        ]);

        // Assert ledger available balance is updated to 600k
        $responseSummary = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/claims/reward-summary?customer_codes=C110003074');
        $this->assertEquals(600000.00, $responseSummary->json('data.available_balance'));
    }

    /**
     * Test approved claims endpoint returns only claims with debit > 0
     */
    public function test_get_approved_claims(): void
    {
        // 1. Create a pending claim (debit = 0.00)
        \App\Models\TrxClaimBalanceLedger::create([
            'customer_code'    => 'C110003074',
            'ref_number'       => 'BATCH-PENDING',
            'transaction_date' => '2026-07-14',
            'type'             => 'CLAIM',
            'debit'            => 0.00,
            'credit'           => 0.00,
            'description'      => 'Pending Claim Batch',
        ]);

        // 2. Create an approved claim (debit > 0.00)
        \App\Models\TrxClaimBalanceLedger::create([
            'customer_code'    => 'C110003074',
            'ref_number'       => 'BATCH-APPROVED',
            'transaction_date' => '2026-07-14',
            'type'             => 'CLAIM',
            'debit'            => 250000.00,
            'credit'           => 0.00,
            'description'      => 'Approved Claim Batch',
        ]);

        // 3. Request approved claims as admin
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/claims/balance-ledger/approved-claims?customer_code=C110003074');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'batch_id',
                        'batch_no',
                        'debit',
                        'customer_code',
                        'customer_name',
                        'depo',
                        'transaction_date',
                    ]
                ]
            ]);

        $data = $response->json('data');
        // It should contain only the approved claim, not the pending one
        $this->assertCount(1, $data);
        $this->assertEquals('BATCH-APPROVED', $data[0]['batch_no']);
        $this->assertEquals(250000.00, $data[0]['debit']);
        $this->assertEquals('C110003074', $data[0]['customer_code']);
    }

    /**
     * Test multiple batch withdrawal creation and validation.
     */
    public function test_withdrawals_multiple_batches(): void
    {
        // 1. Setup two programs/batches with verified discounts
        $program = MstProgram::create([
            'program_code' => 'PRG-01',
            'program_name' => 'Program A',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'status' => 'ACTIVE',
        ]);

        $batch1 = TrxProgramUploadBatch::create(['batch_no' => 'B-001', 'file_name' => 'upload1.xlsx', 'uploaded_by' => 'admin']);
        $batch2 = TrxProgramUploadBatch::create(['batch_no' => 'B-002', 'file_name' => 'upload2.xlsx', 'uploaded_by' => 'admin']);

        // Upload and verified results for batch1 (500,000)
        $upload1 = TrxProgramUpload::create([
            'batch_id' => $batch1->id, 'customer_code' => 'C110003074', 'item_code' => 'E65', 'qty_kg' => 10.00, 'customer_type' => 'GT', 'transaction_date' => '2026-06-15'
        ]);
        TrxProgramResult::create([
            'upload_id' => $upload1->id, 'program_id' => $program->id, 'customer_code' => 'C110003074', 'total_diskon' => 500000.00, 'status' => 'VALID_PROGRAM', 'is_verified' => true
        ]);
        TrxClaimBalanceLedger::create([
            'customer_code' => 'C110003074', 'ref_number' => 'B-001', 'batch_id' => $batch1->id, 'transaction_date' => now()->toDateString(), 'type' => 'CLAIM', 'debit' => 500000.00
        ]);

        // Upload and verified results for batch2 (500,000)
        $upload2 = TrxProgramUpload::create([
            'batch_id' => $batch2->id, 'customer_code' => 'C110003074', 'item_code' => 'E65', 'qty_kg' => 10.00, 'customer_type' => 'GT', 'transaction_date' => '2026-06-15'
        ]);
        TrxProgramResult::create([
            'upload_id' => $upload2->id, 'program_id' => $program->id, 'customer_code' => 'C110003074', 'total_diskon' => 500000.00, 'status' => 'VALID_PROGRAM', 'is_verified' => true
        ]);
        TrxClaimBalanceLedger::create([
            'customer_code' => 'C110003074', 'ref_number' => 'B-002', 'batch_id' => $batch2->id, 'transaction_date' => now()->toDateString(), 'type' => 'CLAIM', 'debit' => 500000.00
        ]);

        // 2. Request a withdrawal of 600,000 (200k from batch1, 400k from batch2)
        $response = $this->actingAs($this->distributorUser, 'sanctum')
            ->postJson('/api/distributor-channel/v1/claims/withdraws', [
                'amount' => 600000.00,
                'lines' => [
                    ['batch_id' => $batch1->id, 'amount' => 200000.00],
                    ['batch_id' => $batch2->id, 'amount' => 400000.00],
                ]
            ]);

        $response->assertStatus(201);
        $withdrawId = $response->json('data.id');

        // 3. Verify ledger entries are created for both batches
        $this->assertDatabaseHas('trx_claim_balance_ledger', [
            'customer_code' => 'C110003074', 'type' => 'WITHDRAW', 'batch_id' => $batch1->id, 'credit' => 200000.00, 'referenceable_id' => $withdrawId
        ]);
        $this->assertDatabaseHas('trx_claim_balance_ledger', [
            'customer_code' => 'C110003074', 'type' => 'WITHDRAW', 'batch_id' => $batch2->id, 'credit' => 400000.00, 'referenceable_id' => $withdrawId
        ]);

        // 4. Verify available balances of both batches in paginated list
        $responseBatches = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/distributor-channel/v1/claims/batches');

        $responseBatches->assertStatus(200);
        $batches = collect($responseBatches->json('data.data'));

        $b1Data = $batches->firstWhere('batch_no', 'B-001');
        $b2Data = $batches->firstWhere('batch_no', 'B-002');

        $this->assertEquals(300000.00, $b1Data['available_balance']);
        $this->assertEquals(100000.00, $b2Data['available_balance']);
    }
}
