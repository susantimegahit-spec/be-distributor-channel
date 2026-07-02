<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Item;
use App\Models\MstProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Http\UploadedFile;

class ClaimCalculationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var User
     */
    protected User $user;

    /**
     * @var string
     */
    protected string $token;

    /**
     * @var Item
     */
    protected Item $itemA26;

    /**
     * @var Item
     */
    protected Item $itemB26;

    /**
     * Setup test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create user
        $this->user = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test_token')->plainTextToken;

        // Create items
        $this->itemA26 = Item::create([
            'item_code' => 'A26',
            'item_name' => 'TOP 250 M @ 10 KG / BAL',
            'suom_entry' => 1,
            'sal_unit_msr' => 'Kg',
            'per_kg' => 10,
            'status' => 1,
        ]);

        $this->itemB26 = Item::create([
            'item_code' => 'B26.B',
            'item_name' => 'KOP 250 M @ 10 KG / BAL',
            'suom_entry' => 2,
            'sal_unit_msr' => 'Kg',
            'per_kg' => 10,
            'status' => 1,
        ]);
    }

    /**
     * Helper to get Authorization header.
     */
    private function getAuthHeader(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }

    /**
     * Helper to create real test Excel file in memory.
     */
    private function createTestExcel(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        foreach ($rows as $rowIndex => $rowData) {
            foreach ($rowData as $colIndex => $value) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue("{$colLetter}" . ($rowIndex + 1), $value);
            }
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_test_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);
        
        return new UploadedFile($tempFile, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * Test lookup items API.
     */
    public function test_get_items_lookup(): void
    {
        $response = $this->withHeaders($this->getAuthHeader())
            ->getJson('/api/distributor-channel/v1/claims/items?search=A26');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'item_code' => 'A26',
            'item_name' => 'TOP 250 M @ 10 KG / BAL',
        ]);
    }

    /**
     * Test Program CRUD API.
     */
    public function test_program_crud(): void
    {
        // 1. Create Program
        $payload = [
            'program_code' => 'PRG202606',
            'program_name' => 'Program Garam Juni 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'description' => 'Test description',
            'items' => [$this->itemA26->item_code, $this->itemB26->item_code],
            'strata' => [
                [
                    'customer_type' => 'GT',
                    'min_qty_kg' => 3,
                    'max_qty_kg' => 199,
                    'harga_program_per_kg' => 7700,
                    'diskon_per_kg' => 200,
                ],
                [
                    'customer_type' => 'GT',
                    'min_qty_kg' => 200,
                    'max_qty_kg' => null,
                    'harga_program_per_kg' => 7700,
                    'diskon_per_kg' => 250,
                ]
            ]
        ];

        $response = $this->withHeaders($this->getAuthHeader())
            ->postJson('/api/distributor-channel/v1/claims/programs', $payload);

        $response->assertStatus(201);
        $response->assertJsonFragment(['program_code' => 'PRG202606']);
        $programId = $response->json('data.id');

        // 2. Read Program
        $response = $this->withHeaders($this->getAuthHeader())
            ->getJson('/api/distributor-channel/v1/claims/programs/' . $programId);

        $response->assertStatus(200);
        $response->assertJsonFragment(['program_name' => 'Program Garam Juni 2026']);
        $this->assertCount(2, $response->json('data.items'));
        $this->assertCount(2, $response->json('data.strata'));

        // 3. Update Program
        $payload['program_name'] = 'Updated Program Name';
        $response = $this->withHeaders($this->getAuthHeader())
            ->putJson('/api/distributor-channel/v1/claims/programs/' . $programId, $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment(['program_name' => 'Updated Program Name']);

        // 4. List Programs
        $response = $this->withHeaders($this->getAuthHeader())
            ->getJson('/api/distributor-channel/v1/claims/programs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['data', 'current_page', 'last_page', 'per_page', 'total']]);

        // 5. Delete Program
        $response = $this->withHeaders($this->getAuthHeader())
            ->deleteJson('/api/distributor-channel/v1/claims/programs/' . $programId);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test Excel Import and claim calculations.
     */
    public function test_excel_upload_and_calculations(): void
    {
        // Setup active program with strata
        $program = MstProgram::create([
            'program_code' => 'PRG202606',
            'program_name' => 'Program Garam Juni 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'status' => 'ACTIVE',
        ]);
        $program->items()->sync([$this->itemA26->id]);
        $program->strata()->create([
            'customer_type' => 'GT',
            'min_qty_kg' => 3,
            'max_qty_kg' => 199,
            'harga_program_per_kg' => 7700,
            'diskon_per_kg' => 200,
        ]);
        $program->strata()->create([
            'customer_type' => 'GT',
            'min_qty_kg' => 200,
            'max_qty_kg' => null,
            'harga_program_per_kg' => 7700,
            'diskon_per_kg' => 250,
        ]);

        // Construct mock Excel file contents
        $excelRows = [
            ['TEMPLATE UPLOAD KLAIM DISTRIBUTOR'],
            [''],
            ['Kode Customer', 'Nama Customer', 'Item', 'Nama Item', 'Harga Jual @ Kg', 'Qty @ Kg', 'Type Customer', 'Transaction Date'],
            ['C110000411', 'DUA JAYA, CV', 'A26', 'TOP 250 M @ 10 KG / BAL', '6400', '100', 'GT', '2026-06-12'],
            ['C110000411', 'DUA JAYA, CV', 'X001', 'UNKNOWN ITEM', '6400', '100', 'GT', '2026-06-12'],
            ['C110000411', 'DUA JAYA, CV', 'B26.B', 'KOP 250 M @ 10 KG / BAL', '7700', '100', 'GT', '2026-06-12'],
            ['C110000411', 'DUA JAYA, CV', 'A26', 'TOP 250 M @ 10 KG / BAL', '6400', '1', 'GT', '2026-06-12'],
        ];

        $excelFile = $this->createTestExcel($excelRows);

        // Upload Excel
        $response = $this->withHeaders($this->getAuthHeader())
            ->postJson('/api/distributor-channel/v1/claims/upload', [
                'file' => $excelFile,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'batch_id',
                'batch_no',
                'total_rows',
                'processed_rows',
                'invalid_rows',
                'total_diskon'
            ]
        ]);

        $this->assertEquals(4, $response->json('data.total_rows'));
        $this->assertEquals(1, $response->json('data.processed_rows')); // 1 row VALID_PROGRAM
        $this->assertEquals(3, $response->json('data.invalid_rows')); // 3 rows invalid (ITEM_NOT_FOUND, PROGRAM_NOT_FOUND, STRATA_NOT_FOUND)
        $this->assertEquals(20000, $response->json('data.total_diskon')); // 100 Qty * 200 Diskon = 20000

        $batchId = $response->json('data.batch_id');

        // Check Batch Detail API
        $response = $this->withHeaders($this->getAuthHeader())
            ->getJson('/api/distributor-channel/v1/claims/batches/' . $batchId);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'total_rows' => 4,
            'valid_rows' => 1,
            'invalid_rows' => 3,
            'total_diskon' => 20000,
        ]);

        // Check Results Pagination API
        $response = $this->withHeaders($this->getAuthHeader())
            ->getJson('/api/distributor-channel/v1/claims/results?batch_id=' . $batchId);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['data', 'current_page', 'last_page', 'per_page', 'total']]);
        $this->assertCount(4, $response->json('data.data'));

        $data = $response->json('data.data');
        $this->assertEquals('VALID_PROGRAM', $data[0]['status']);
        $this->assertEquals('ITEM_NOT_FOUND', $data[1]['status']);
        $this->assertEquals('PROGRAM_NOT_FOUND', $data[2]['status']);
        $this->assertEquals('STRATA_NOT_FOUND', $data[3]['status']);

        // Check Dashboard API
        $response = $this->withHeaders($this->getAuthHeader())
            ->getJson('/api/distributor-channel/v1/claims/dashboard');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'total_program' => 1,
            'total_batch' => 1,
            'total_valid_rows' => 1,
            'total_diskon' => 20000,
        ]);

        // Check Results Excel Export API
        $response = $this->withHeaders($this->getAuthHeader())
            ->get('/api/distributor-channel/v1/claims/results/export?batch_id=' . $batchId);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /**
     * Test auto-generation of program code.
     */
    public function test_program_code_auto_generation(): void
    {
        $payload = [
            'program_name' => 'Program Auto Gen Test',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'description' => 'Test auto gen',
            'items' => [$this->itemA26->item_code],
            'strata' => [
                [
                    'customer_type' => 'GT',
                    'min_qty_kg' => 10,
                    'max_qty_kg' => null,
                    'harga_program_per_kg' => 5000,
                    'diskon_per_kg' => 100,
                ]
            ]
        ];

        // 1. Create first program, program_code should be generated as PRG{tahun}{bulan}001
        $response = $this->withHeaders($this->getAuthHeader())
            ->postJson('/api/distributor-channel/v1/claims/programs', $payload);

        $response->assertStatus(201);
        $expectedCode1 = 'PRG' . date('Ym') . '001';
        $response->assertJsonFragment(['program_code' => $expectedCode1]);

        // 2. Create second program, program_code should be generated as PRG{tahun}{bulan}002
        $response2 = $this->withHeaders($this->getAuthHeader())
            ->postJson('/api/distributor-channel/v1/claims/programs', $payload);

        $response2->assertStatus(201);
        $expectedCode2 = 'PRG' . date('Ym') . '002';
        $response2->assertJsonFragment(['program_code' => $expectedCode2]);
    }
}

