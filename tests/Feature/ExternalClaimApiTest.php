<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\DistributorApiKey;
use App\Models\Item;
use App\Models\MstProgram;
use App\Models\MstProgramStrata;
use App\Models\TrxProgramUploadBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalClaimApiTest extends TestCase
{
    use RefreshDatabase;

    protected Distributor $distributor;
    protected Distributor $otherDistributor;
    protected string $rawApiKey;
    protected DistributorApiKey $apiKeyRecord;
    protected Item $item;
    protected MstProgram $program;
    protected MstProgramStrata $strata;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create distributors
        $this->distributor = Distributor::create([
            'code_customer' => 'C110003074',
            'name'          => 'PT SUSANTI DISTRIBUSI MAKMUR',
            'status'        => 1,
        ]);

        $this->otherDistributor = Distributor::create([
            'code_customer' => 'C999999999',
            'name'          => 'PT OTHER DISTRIBUTOR',
            'status'        => 1,
        ]);

        // 2. Create API Key for distributor
        $this->rawApiKey = 'susanti_claim_sec_' . Str::random(40);
        $hashedKey = DistributorApiKey::hashKey($this->rawApiKey);

        $this->apiKeyRecord = DistributorApiKey::create([
            'distributor_id' => $this->distributor->id,
            'name'           => 'B2B Claim Distributor Integration',
            'key_prefix'     => substr($this->rawApiKey, 0, 15),
            'api_key_hash'   => $hashedKey,
            'is_active'      => true,
        ]);
        $this->apiKeyRecord->distributors()->attach($this->distributor->id);

        // 3. Create Item
        $this->item = Item::create([
            'item_code'    => 'E65',
            'item_name'    => 'TOP 250 M @ 10 KG / BAL',
            'sal_unit_msr' => 'KG',
            'status'       => 1,
        ]);

        // 4. Create Active Program & Strata
        $this->program = MstProgram::create([
            'program_code'  => 'PRG-2026-OCT',
            'program_name'  => 'Program Promo Garam Top Oktober 2026',
            'start_date'    => '2026-10-01',
            'end_date'      => '2026-10-31',
            'code_customer' => $this->distributor->code_customer,
            'status'        => 'ACTIVE',
        ]);
        $this->program->items()->attach($this->item->id);

        $this->strata = MstProgramStrata::create([
            'program_id'           => $this->program->id,
            'customer_type'        => 'GT',
            'min_qty_kg'           => 10,
            'max_qty_kg'           => 500,
            'harga_program_per_kg' => 7500,
            'diskon_per_kg'        => 250,
        ]);
    }

    /**
     * Test failure without API key.
     */
    public function test_fails_without_api_key(): void
    {
        $response = $this->postJson('/api/distributor-channel/v1/external/claims', []);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test failure with invalid API key.
     */
    public function test_fails_with_invalid_api_key(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token_123',
        ])->postJson('/api/distributor-channel/v1/external/claims', []);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test failure when trying to submit claim with unauthorized card_code.
     */
    public function test_fails_with_unauthorized_card_code(): void
    {
        $payload = [
            'card_code'          => $this->otherDistributor->code_customer,
            'distributor_ref_no' => 'CLM-TEST-001',
            'lines'              => [
                [
                    'item_code'        => 'E65',
                    'qty_kg'           => 100,
                    'customer_type'    => 'GT',
                    'transaction_date' => '2026-10-15',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rawApiKey,
        ])->postJson('/api/distributor-channel/v1/external/claims', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test failure validation when item_code does not exist.
     */
    public function test_fails_validation_when_item_not_found(): void
    {
        $payload = [
            'card_code'          => $this->distributor->code_customer,
            'distributor_ref_no' => 'CLM-TEST-002',
            'lines'              => [
                [
                    'item_code'        => 'NON_EXISTENT_SKU',
                    'qty_kg'           => 100,
                    'customer_type'    => 'GT',
                    'transaction_date' => '2026-10-15',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rawApiKey,
        ])->postJson('/api/distributor-channel/v1/external/claims', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test successful claim creation with JSON lines and automatic calculation.
     */
    public function test_store_claim_success_with_json_lines(): void
    {
        $payload = [
            'card_code'          => $this->distributor->code_customer,
            'distributor_ref_no' => 'CLM-TEST-SUCCESS-001',
            'lines'              => [
                [
                    'item_code'         => 'E65',
                    'qty_kg'            => 100,
                    'customer_type'     => 'GT',
                    'transaction_date'  => '2026-10-15',
                    'sell_price_per_kg' => 7500,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rawApiKey,
        ])->postJson('/api/distributor-channel/v1/external/claims', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'card_code'          => $this->distributor->code_customer,
                    'distributor_ref_no' => 'CLM-TEST-SUCCESS-001',
                    'total_rows'         => 1,
                    'processed_rows'     => 1,
                    'invalid_rows'       => 0,
                    'total_diskon'       => 25000, // 100 kg * 250 diskon
                ],
            ]);

        $this->assertDatabaseHas('trx_program_upload_batch', [
            'distributor_ref_no' => 'CLM-TEST-SUCCESS-001',
            'uploaded_by'        => $this->distributor->code_customer,
        ]);

        $this->assertDatabaseHas('trx_program_upload', [
            'customer_code' => $this->distributor->code_customer,
            'item_code'     => 'E65',
            'qty_kg'        => 100,
            'customer_type' => 'GT',
        ]);

        $this->assertDatabaseHas('trx_program_result', [
            'customer_code' => $this->distributor->code_customer,
            'item_code'     => 'E65',
            'status'        => 'VALID_PROGRAM',
            'total_diskon'  => 25000,
        ]);
    }

    /**
     * Test idempotency with distributor_ref_no.
     */
    public function test_idempotency_with_distributor_ref_no(): void
    {
        $payload = [
            'card_code'          => $this->distributor->code_customer,
            'distributor_ref_no' => 'CLM-IDEMPOTENT-001',
            'lines'              => [
                [
                    'item_code'        => 'E65',
                    'qty_kg'           => 100,
                    'customer_type'    => 'GT',
                    'transaction_date' => '2026-10-15',
                ],
            ],
        ];

        // 1st request -> 201 Created
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rawApiKey,
        ])->postJson('/api/distributor-channel/v1/external/claims', $payload);
        $response1->assertStatus(201);

        // 2nd request -> 200 OK idempotent
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rawApiKey,
        ])->postJson('/api/distributor-channel/v1/external/claims', $payload);

        $response2->assertStatus(200)
            ->assertJson([
                'success'      => true,
                'is_duplicate' => true,
            ]);

        // Ensure database only has 1 batch record
        $this->assertEquals(1, TrxProgramUploadBatch::where('distributor_ref_no', 'CLM-IDEMPOTENT-001')->count());
    }

    /**
     * Test get batches listing filtered by authorized distributor.
     */
    public function test_get_batches_filtered_by_authorized_distributor(): void
    {
        // Create a batch
        $batch = TrxProgramUploadBatch::create([
            'batch_no'           => 'UPLOAD-20261016-999',
            'file_name'          => 'API_CLAIM_TEST',
            'uploaded_by'        => $this->distributor->code_customer,
            'distributor_ref_no' => 'REF-BATCH-001',
        ]);
        \App\Models\TrxProgramUpload::create([
            'batch_id'         => $batch->id,
            'customer_code'    => $this->distributor->code_customer,
            'customer_name'    => $this->distributor->name,
            'item_code'        => 'E65',
            'qty_kg'           => 50,
            'customer_type'    => 'GT',
            'transaction_date' => '2026-10-15',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rawApiKey,
        ])->getJson('/api/distributor-channel/v1/external/claims/batches');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'batch_no'           => 'UPLOAD-20261016-999',
                'distributor_ref_no' => 'REF-BATCH-001',
            ]);
    }

    /**
     * Test show batch detail by batch_no and by distributor_ref_no.
     */
    public function test_show_batch_detail(): void
    {
        $batch = TrxProgramUploadBatch::create([
            'batch_no'           => 'UPLOAD-20261016-888',
            'file_name'          => 'API_CLAIM_DETAIL_TEST',
            'uploaded_by'        => $this->distributor->code_customer,
            'distributor_ref_no' => 'REF-DETAIL-001',
        ]);
        \App\Models\TrxProgramUpload::create([
            'batch_id'         => $batch->id,
            'customer_code'    => $this->distributor->code_customer,
            'customer_name'    => $this->distributor->name,
            'item_code'        => 'E65',
            'qty_kg'           => 50,
            'customer_type'    => 'GT',
            'transaction_date' => '2026-10-15',
        ]);

        // Query by batch_no
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rawApiKey,
        ])->getJson('/api/distributor-channel/v1/external/claims/batches/UPLOAD-20261016-888');

        $response1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'batch_no'           => 'UPLOAD-20261016-888',
                    'distributor_ref_no' => 'REF-DETAIL-001',
                ],
            ]);

        // Query by distributor_ref_no
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rawApiKey,
        ])->getJson('/api/distributor-channel/v1/external/claims/batches/REF-DETAIL-001');

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'batch_no' => 'UPLOAD-20261016-888',
                ],
            ]);
    }

    /**
     * Test get active programs for distributor.
     */
    public function test_get_programs(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rawApiKey,
        ])->getJson('/api/distributor-channel/v1/external/claims/programs');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'program_code' => 'PRG-2026-OCT',
            ]);
    }

    /**
     * Test successful claim creation with Excel file upload.
     */
    public function test_store_claim_success_with_excel_file(): void
    {
        $rows = [
            ['TEMPLATE UPLOAD KLAIM DISTRIBUTOR'],
            [''],
            ['Kode Distributor', 'Nama Distributor', 'Item', 'Nama Item', 'Harga Jual @ Kg', 'Qty @ Kg', 'Type Customer', 'Transaction Date'],
            [$this->distributor->code_customer, $this->distributor->name, 'E65', 'TOP 250 M @ 10 KG / BAL', '7500', '100', 'GT', '2026-10-15'],
        ];

        $excelFile = $this->createTestExcel($rows);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rawApiKey,
        ])->post('/api/distributor-channel/v1/external/claims', [
            'card_code'          => $this->distributor->code_customer,
            'distributor_ref_no' => 'CLM-TEST-EXCEL-001',
            'file'               => $excelFile,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'card_code'          => $this->distributor->code_customer,
                    'distributor_ref_no' => 'CLM-TEST-EXCEL-001',
                    'total_rows'         => 1,
                    'processed_rows'     => 1,
                    'total_diskon'       => 25000,
                ],
            ]);
    }

    private function createTestExcel(array $rows): \Illuminate\Http\UploadedFile
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        foreach ($rows as $rowIndex => $rowData) {
            foreach ($rowData as $colIndex => $value) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue("{$colLetter}" . ($rowIndex + 1), $value);
            }
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_claim_test_') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);
        
        return new \Illuminate\Http\UploadedFile($tempFile, 'claim_upload.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}

