<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Distributor;
use App\Models\TrxProgramUploadBatch;
use App\Models\TrxProgramUpload;
use App\Models\TrxProgramResult;
use App\Models\TrxProgramWithdraw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClaimMultipleCustomerFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $distributorUser1;
    protected User $distributorUser2;

    protected function setUp(): void
    {
        parent::setUp();

        // Admin User
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // Distributor 1
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

        // Distributor 2
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
    }

    public function test_batches_multiple_customer_filter_for_admin(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        // Create batches
        $batch1 = TrxProgramUploadBatch::create(['batch_no' => 'B-001', 'file_name' => 'file1.xlsx']);
        $batch2 = TrxProgramUploadBatch::create(['batch_no' => 'B-002', 'file_name' => 'file2.xlsx']);

        // Upload rows for Batch 1
        $upload1 = TrxProgramUpload::create([
            'batch_id' => $batch1->id,
            'customer_code' => 'CUST01',
            'item_code' => 'I001',
            'qty_kg' => 10,
            'customer_type' => 'GT',
            'transaction_date' => '2026-06-01',
        ]);
        TrxProgramResult::create([
            'upload_id' => $upload1->id,
            'customer_code' => 'CUST01',
            'total_diskon' => 1000,
            'status' => 'VALID_PROGRAM',
        ]);

        // Upload rows for Batch 2
        $upload2 = TrxProgramUpload::create([
            'batch_id' => $batch2->id,
            'customer_code' => 'CUST02',
            'item_code' => 'I001',
            'qty_kg' => 20,
            'customer_type' => 'GT',
            'transaction_date' => '2026-06-01',
        ]);
        TrxProgramResult::create([
            'upload_id' => $upload2->id,
            'customer_code' => 'CUST02',
            'total_diskon' => 2000,
            'status' => 'VALID_PROGRAM',
        ]);

        // Admin requests with customer_codes as array
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/distributor-channel/v1/claims/batches?customer_codes[]=CUST01');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('B-001', $response->json('data.data.0.batch_no'));
        $this->assertEquals(1000, $response->json('data.data.0.total_diskon'));
        $this->assertEquals('CUST01', $response->json('data.data.0.code_customer'));
        $this->assertEquals('Distributor 1', $response->json('data.data.0.customer_name'));
        $this->assertEquals('Distributor 1', $response->json('data.data.0.name_customer'));
        $this->assertEquals('DEPO SURABAYA', $response->json('data.data.0.depo'));

        // Admin requests with customer_codes as comma-separated string
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/distributor-channel/v1/claims/batches?customer_codes=CUST02');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('B-002', $response->json('data.data.0.batch_no'));
        $this->assertEquals(2000, $response->json('data.data.0.total_diskon'));

        // Admin requests multiple
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/distributor-channel/v1/claims/batches?customer_codes=CUST01,CUST02');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_batches_filter_forced_for_distributor(): void
    {
        $token = $this->distributorUser1->createToken('dist_token')->plainTextToken;

        // Create batches
        $batch1 = TrxProgramUploadBatch::create(['batch_no' => 'B-001', 'file_name' => 'file1.xlsx']);
        $batch2 = TrxProgramUploadBatch::create(['batch_no' => 'B-002', 'file_name' => 'file2.xlsx']);

        $upload1 = TrxProgramUpload::create([
            'batch_id' => $batch1->id,
            'customer_code' => 'CUST01',
            'item_code' => 'I001',
            'qty_kg' => 10,
            'customer_type' => 'GT',
            'transaction_date' => '2026-06-01',
        ]);
        TrxProgramResult::create([
            'upload_id' => $upload1->id,
            'customer_code' => 'CUST01',
            'total_diskon' => 1000,
            'status' => 'VALID_PROGRAM',
        ]);

        $upload2 = TrxProgramUpload::create([
            'batch_id' => $batch2->id,
            'customer_code' => 'CUST02',
            'item_code' => 'I001',
            'qty_kg' => 20,
            'customer_type' => 'GT',
            'transaction_date' => '2026-06-01',
        ]);
        TrxProgramResult::create([
            'upload_id' => $upload2->id,
            'customer_code' => 'CUST02',
            'total_diskon' => 2000,
            'status' => 'VALID_PROGRAM',
        ]);

        // Distributor 1 requests without parameters or trying to request CUST02
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/distributor-channel/v1/claims/batches?customer_codes=CUST02');

        // Should be forced to see only CUST01
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('B-001', $response->json('data.data.0.batch_no'));
    }

    public function test_withdraws_multiple_customer_filter(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        TrxProgramWithdraw::create([
            'withdraw_no' => 'WD-001',
            'customer_code' => 'CUST01',
            'amount' => 50000,
            'status' => 'PENDING',
        ]);

        TrxProgramWithdraw::create([
            'withdraw_no' => 'WD-002',
            'customer_code' => 'CUST02',
            'amount' => 100000,
            'status' => 'APPROVED',
        ]);

        // Admin requests with customer_codes
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/distributor-channel/v1/claims/withdraws?customer_codes=CUST01,CUST02');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));

        // Admin requests CUST01 only
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/distributor-channel/v1/claims/withdraws?customer_codes[]=CUST01');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('WD-001', $response->json('data.data.0.withdraw_no'));
        $this->assertEquals('CUST01', $response->json('data.data.0.code_customer'));
        $this->assertEquals('Distributor 1', $response->json('data.data.0.customer_name'));
        $this->assertEquals('Distributor 1', $response->json('data.data.0.name_customer'));
        $this->assertEquals('DEPO SURABAYA', $response->json('data.data.0.depo'));
    }

    public function test_reward_summary_multiple_customer_filter(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        // Setup results for CUST01
        $batch1 = TrxProgramUploadBatch::create(['batch_no' => 'B-001']);
        $upload1 = TrxProgramUpload::create([
            'batch_id' => $batch1->id,
            'customer_code' => 'CUST01',
            'item_code' => 'I001',
            'qty_kg' => 10,
            'customer_type' => 'GT',
            'transaction_date' => '2026-06-01',
        ]);
        TrxProgramResult::create([
            'upload_id' => $upload1->id,
            'customer_code' => 'CUST01',
            'total_diskon' => 1000000,
            'status' => 'VALID_PROGRAM',
            'is_verified' => true,
        ]);
        TrxProgramWithdraw::create([
            'withdraw_no' => 'WD-001',
            'customer_code' => 'CUST01',
            'amount' => 300000,
            'status' => 'COMPLETED',
        ]);

        // Setup results for CUST02
        $upload2 = TrxProgramUpload::create([
            'batch_id' => $batch1->id,
            'customer_code' => 'CUST02',
            'item_code' => 'I001',
            'qty_kg' => 10,
            'customer_type' => 'GT',
            'transaction_date' => '2026-06-01',
        ]);
        TrxProgramResult::create([
            'upload_id' => $upload2->id,
            'customer_code' => 'CUST02',
            'total_diskon' => 2000000,
            'status' => 'VALID_PROGRAM',
            'is_verified' => true,
        ]);
        TrxProgramWithdraw::create([
            'withdraw_no' => 'WD-002',
            'customer_code' => 'CUST02',
            'amount' => 500000,
            'status' => 'APPROVED',
        ]);

        // Request summary for CUST01 only
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/distributor-channel/v1/claims/reward-summary?customer_codes=CUST01');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'total_claimed' => 1000000.00,
                'total_verified' => 1000000.00,
                'total_withdrawn' => 300000.00,
                'available_balance' => 700000.00,
            ]
        ]);

        // Request summary for multiple
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/distributor-channel/v1/claims/reward-summary?customer_codes=CUST01,CUST02');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'total_claimed' => 3000000.00,
                'total_verified' => 3000000.00,
                'total_withdrawn' => 800000.00,
                'available_balance' => 2200000.00,
            ]
        ]);
    }
}
