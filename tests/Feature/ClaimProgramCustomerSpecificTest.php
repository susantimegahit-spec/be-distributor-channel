<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Item;
use App\Models\Distributor;
use App\Models\MstProgram;
use App\Models\TrxProgramUploadBatch;
use App\Models\TrxProgramUpload;
use App\Modules\Claim\Services\ClaimCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClaimProgramCustomerSpecificTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $distributorUser1;
    protected User $distributorUser2;
    protected User $distributorUser3;
    protected Item $item;
    protected string $adminToken;
    protected string $dist1Token;
    protected string $dist2Token;
    protected string $dist3Token;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Admin
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminToken = $this->adminUser->createToken('admin_token')->plainTextToken;

        // 2. Create Distributors
        Distributor::create([
            'code_customer' => 'CUST01',
            'name' => 'Distributor 1',
            'depo' => 'DEPO SURABAYA',
            'status' => 1,
        ]);
        $this->distributorUser1 = User::create([
            'name' => 'Distributor 1 User',
            'username' => 'dist1',
            'email' => 'dist1@example.com',
            'password' => Hash::make('password123'),
            'code_customer' => 'CUST01',
            'is_active' => true,
        ]);
        $this->dist1Token = $this->distributorUser1->createToken('dist1_token')->plainTextToken;

        Distributor::create([
            'code_customer' => 'CUST02',
            'name' => 'Distributor 2',
            'depo' => 'DEPO JAKARTA',
            'status' => 1,
        ]);
        $this->distributorUser2 = User::create([
            'name' => 'Distributor 2 User',
            'username' => 'dist2',
            'email' => 'dist2@example.com',
            'password' => Hash::make('password123'),
            'code_customer' => 'CUST02',
            'is_active' => true,
        ]);
        $this->dist2Token = $this->distributorUser2->createToken('dist2_token')->plainTextToken;

        Distributor::create([
            'code_customer' => 'CUST03',
            'name' => 'Distributor 3',
            'depo' => 'DEPO MEDAN',
            'status' => 1,
        ]);
        $this->distributorUser3 = User::create([
            'name' => 'Distributor 3 User',
            'username' => 'dist3',
            'email' => 'dist3@example.com',
            'password' => Hash::make('password123'),
            'code_customer' => 'CUST03',
            'is_active' => true,
        ]);
        $this->dist3Token = $this->distributorUser3->createToken('dist3_token')->plainTextToken;

        // 3. Create Item
        $this->item = Item::create([
            'item_code' => 'A26',
            'item_name' => 'TOP 250 M @ 10 KG / BAL',
            'suom_entry' => 1,
            'sal_unit_msr' => 'Kg',
            'per_kg' => 10,
            'status' => 1,
        ]);
    }

    /**
     * Test creating a program with multiple customer codes via API.
     */
    public function test_create_program_with_multiple_customer_codes(): void
    {
        $payload = [
            'program_code' => 'PRG_SPECIFIC',
            'program_name' => 'Specific Program for CUST01 and CUST02',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'description' => 'For CUST01 and CUST02',
            'code_customer' => 'CUST01,CUST02',
            'items' => [$this->item->item_code],
            'strata' => [
                [
                    'customer_type' => 'GT',
                    'min_qty_kg' => 3,
                    'max_qty_kg' => 199,
                    'harga_program_per_kg' => 7700,
                    'diskon_per_kg' => 300,
                ]
            ]
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson('/api/distributor-channel/v1/claims/programs', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.code_customer', 'CUST01,CUST02');

        $this->assertDatabaseHas('mst_program', [
            'program_code' => 'PRG_SPECIFIC',
            'code_customer' => 'CUST01,CUST02',
        ]);
    }

    /**
     * Test validation fails when customer code is invalid.
     */
    public function test_create_program_with_invalid_customer_code(): void
    {
        $payload = [
            'program_code' => 'PRG_SPECIFIC',
            'program_name' => 'Specific Program',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'code_customer' => 'CUST01,INVALID_CUST',
            'items' => [$this->item->item_code],
            'strata' => [
                [
                    'customer_type' => 'GT',
                    'min_qty_kg' => 3,
                    'harga_program_per_kg' => 7700,
                    'diskon_per_kg' => 300,
                ]
            ]
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson('/api/distributor-channel/v1/claims/programs', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status_code' => 422,
                'message' => 'Satu atau lebih kode customer tidak valid.',
            ]);
    }

    /**
     * Helper to create test programs.
     */
    private function createTestPrograms(): void
    {
        // 1. Create a general program (null code_customer)
        $generalProgram = MstProgram::create([
            'program_code' => 'PRG_GENERAL',
            'program_name' => 'General Program',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'description' => 'Available for all',
            'status' => 'ACTIVE',
            'code_customer' => null,
        ]);
        $generalProgram->items()->sync([$this->item->id]);

        // 2. Create a customer specific program for CUST01 and CUST02
        $specificProgram = MstProgram::create([
            'program_code' => 'PRG_SPECIFIC',
            'program_name' => 'Specific Program for CUST01 and CUST02',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'description' => 'Only for CUST01 and CUST02',
            'status' => 'ACTIVE',
            'code_customer' => 'CUST01,CUST02',
        ]);
        $specificProgram->items()->sync([$this->item->id]);
    }

    /**
     * Scenario A: Distributor 1 User should see both Specific (CUST01,CUST02) and General (null) programs
     */
    public function test_list_programs_for_distributor_1(): void
    {
        $this->createTestPrograms();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->dist1Token])
            ->getJson('/api/distributor-channel/v1/claims/programs');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data.data');
    }

    /**
     * Scenario B: Distributor 2 User should also see both Specific and General programs (since CUST02 is in the list)
     */
    public function test_list_programs_for_distributor_2(): void
    {
        $this->createTestPrograms();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->dist2Token])
            ->getJson('/api/distributor-channel/v1/claims/programs');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data.data');
    }

    /**
     * Scenario C: Distributor 3 User should only see General program (since CUST03 is not in the list)
     */
    public function test_list_programs_for_distributor_3(): void
    {
        $this->createTestPrograms();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->dist3Token])
            ->getJson('/api/distributor-channel/v1/claims/programs');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.program_code', 'PRG_GENERAL');
    }

    /**
     * Scenario D: Admin filtering exactly by code_customer=CUST01
     */
    public function test_list_programs_for_admin_filtered(): void
    {
        $this->createTestPrograms();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->getJson('/api/distributor-channel/v1/claims/programs?code_customer=CUST01');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.program_code', 'PRG_SPECIFIC');
    }

    /**
     * Test claim calculation matches specific program and prioritizes it.
     */
    public function test_claim_calculation_matches_and_prioritizes(): void
    {
        // 1. Create a general program with 200 diskon_per_kg
        $generalProgram = MstProgram::create([
            'program_code' => 'PRG_GENERAL',
            'program_name' => 'General Program',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'status' => 'ACTIVE',
            'code_customer' => null,
        ]);
        $generalProgram->items()->sync([$this->item->id]);
        $generalProgram->strata()->create([
            'customer_type' => 'GT',
            'min_qty_kg' => 0,
            'max_qty_kg' => null,
            'harga_program_per_kg' => 7700,
            'diskon_per_kg' => 200,
        ]);

        // 2. Create a customer specific program for CUST01 and CUST02 with 350 diskon_per_kg
        $specificProgram = MstProgram::create([
            'program_code' => 'PRG_SPECIFIC',
            'program_name' => 'Specific Program for CUST01 and CUST02',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'status' => 'ACTIVE',
            'code_customer' => 'CUST01,CUST02',
        ]);
        $specificProgram->items()->sync([$this->item->id]);
        $specificProgram->strata()->create([
            'customer_type' => 'GT',
            'min_qty_kg' => 0,
            'max_qty_kg' => null,
            'harga_program_per_kg' => 7700,
            'diskon_per_kg' => 350,
        ]);

        // 3. Create a batch
        $batch = TrxProgramUploadBatch::create([
            'batch_no' => 'BATCH-TEST-SPECIFIC-001',
            'file_name' => 'test.xlsx',
            'uploaded_by' => 'admin',
        ]);

        // 4. Create upload transactions
        // Transaction A: Customer CUST01 (should resolve specific program: diskon 350)
        $txDist1 = TrxProgramUpload::create([
            'batch_id' => $batch->id,
            'row_number' => 1,
            'transaction_date' => '2026-06-15',
            'invoice_no' => 'INV01',
            'customer_code' => 'CUST01',
            'customer_name' => 'Distributor 1',
            'customer_type' => 'GT',
            'item_code' => 'A26',
            'qty_kg' => 10,
            'price_per_kg' => 8000,
            'total_price' => 80000,
        ]);

        // Transaction B: Customer CUST02 (should also resolve specific program: diskon 350)
        $txDist2 = TrxProgramUpload::create([
            'batch_id' => $batch->id,
            'row_number' => 2,
            'transaction_date' => '2026-06-15',
            'invoice_no' => 'INV02',
            'customer_code' => 'CUST02',
            'customer_name' => 'Distributor 2',
            'customer_type' => 'GT',
            'item_code' => 'A26',
            'qty_kg' => 10,
            'price_per_kg' => 8000,
            'total_price' => 80000,
        ]);

        // Transaction C: Customer CUST03 (should resolve general program fallback: diskon 200)
        $txDist3 = TrxProgramUpload::create([
            'batch_id' => $batch->id,
            'row_number' => 3,
            'transaction_date' => '2026-06-15',
            'invoice_no' => 'INV03',
            'customer_code' => 'CUST03',
            'customer_name' => 'Distributor 3',
            'customer_type' => 'GT',
            'item_code' => 'A26',
            'qty_kg' => 10,
            'price_per_kg' => 8000,
            'total_price' => 80000,
        ]);

        // 5. Run Calculation
        $service = app(ClaimCalculationService::class);
        $summary = $service->calculateBatch($batch->id);

        $this->assertEquals(3, $summary['total_rows']);
        $this->assertEquals(3, $summary['processed_rows']);
        $this->assertEquals(0, $summary['invalid_rows']);
        $this->assertEquals(9000, $summary['total_diskon']); // 10*350 + 10*350 + 10*200 = 9000

        // Fetch insertion results from DB
        $dbResults = \App\Models\TrxProgramResult::where('upload_id', '>', 0)->get();
        $this->assertCount(3, $dbResults);

        $res1 = $dbResults->firstWhere('customer_code', 'CUST01');
        $res2 = $dbResults->firstWhere('customer_code', 'CUST02');
        $res3 = $dbResults->firstWhere('customer_code', 'CUST03');

        $this->assertNotNull($res1);
        $this->assertNotNull($res2);
        $this->assertNotNull($res3);

        // CUST01 matches specific program (350)
        $this->assertEquals($specificProgram->id, $res1->program_id);
        $this->assertEquals(350, $res1->diskon_per_kg);
        $this->assertEquals(3500, $res1->total_diskon);

        // CUST02 matches specific program (350)
        $this->assertEquals($specificProgram->id, $res2->program_id);
        $this->assertEquals(350, $res2->diskon_per_kg);
        $this->assertEquals(3500, $res2->total_diskon);

        // CUST03 matches general program (200)
        $this->assertEquals($generalProgram->id, $res3->program_id);
        $this->assertEquals(200, $res3->diskon_per_kg);
        $this->assertEquals(2000, $res3->total_diskon);
    }
}
