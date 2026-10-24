<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Item;
use App\Models\MstProgram;
use App\Models\MstProgramStrata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ClaimExtendedWebUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Distributor $distributor;
    protected Item $item;
    protected MstProgram $program;
    protected MstProgramStrata $strata;

    protected function setUp(): void
    {
        parent::setUp();

        $this->distributor = Distributor::create([
            'code_customer' => 'C110003074',
            'name'          => 'LESAFFRE SARI',
            'depo'          => 'DEPO SURABAYA',
            'status'        => 1,
        ]);

        $this->user = User::create([
            'name'          => 'Admin Claim',
            'username'      => 'adminclaim',
            'email'         => 'adminclaim@example.com',
            'password'      => Hash::make('secret123'),
            'code_customer' => 'C110003074',
            'is_active'     => true,
        ]);

        $this->item = Item::create([
            'item_code'    => 'E65',
            'item_name'    => 'TOP 250 M @ 10 KG / BAL',
            'suom_entry'   => 1,
            'sal_unit_msr' => 'Kg',
            'per_kg'       => 10,
            'status'       => 1,
        ]);

        $this->program = MstProgram::create([
            'program_code' => 'PRG-TEST-2026',
            'program_name' => 'Program Promo Web Test',
            'start_date'   => '2026-01-01',
            'end_date'     => '2026-12-31',
            'status'       => 'ACTIVE',
        ]);
        $this->program->items()->attach($this->item->id);

        $this->strata = MstProgramStrata::create([
            'program_id'           => $this->program->id,
            'customer_type'        => 'GT',
            'min_qty_kg'           => 10,
            'max_qty_kg'           => 1000,
            'harga_program_per_kg' => 7000,
            'diskon_per_kg'        => 500,
        ]);
    }

    protected function getAuthHeaders(): array
    {
        $token = $this->user->createToken('test_token')->plainTextToken;
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ];
    }

    public function test_template_excel_contains_all_extended_b2b_headers(): void
    {
        $response = $this->withHeaders($this->getAuthHeaders())
            ->get('/api/distributor-channel/v1/claims/template-excel');

        $response->assertStatus(200);
        $content = $response->getContent();

        $expectedHeaders = [
            'Kode Distributor',
            'Nama Distributor',
            'Depo',
            'Kode Customer',
            'Nama Customer',
            'Alamat',
            'Kota / Kabupaten',
            'Kecamatan',
            'Salesman Distributor',
            'Item',
            'Nama Item',
            'Jenis Barang',
            'Qty @Kg',
            'Harga Jual @Kg',
            'Type Customer',
            'Jenis Transaksi',
            'Channel',
            'Invoice',
            'DPP',
            'Diskon 1',
            'Diskon 2',
            'Diskon 3',
            'Diskon 4',
            'Diskon 5',
            'PPN',
            'NET',
            'Transaction Date',
        ];

        foreach ($expectedHeaders as $header) {
            $this->assertStringContainsString("<Data ss:Type=\"String\">{$header}</Data>", $content);
        }
    }

    public function test_web_upload_and_results_with_all_extended_fields(): void
    {
        // 1. Build Excel file with the new extended format
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('template upload klaim');

        $headers = [
            'Kode Distributor',
            'Nama Distributor',
            'Depo',
            'Kode Customer',
            'Nama Customer',
            'Alamat',
            'Kota / Kabupaten',
            'Kecamatan',
            'Salesman Distributor',
            'Item',
            'Nama Item',
            'Jenis Barang',
            'Qty @Kg',
            'Harga Jual @Kg',
            'Type Customer',
            'Jenis Transaksi',
            'Channel',
            'Invoice',
            'DPP',
            'Diskon 1',
            'Diskon 2',
            'Diskon 3',
            'Diskon 4',
            'Diskon 5',
            'PPN',
            'NET',
            'Transaction Date',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $row1 = [
            'C110003074',
            'LESAFFRE SARI',
            'DEPO SURABAYA',
            'OUTLET-001',
            'TOKO BERKAH',
            'JL. RUNGKUT 45',
            'SURABAYA',
            'RUNGKUT',
            'SALES-AHMAD',
            'E65',
            'TOP 250 M @ 10 KG / BAL',
            'REGULER',
            100,
            7500,
            'GT',
            'KREDIT',
            'RETAIL',
            'INV-WEB-001',
            750000,
            25000,
            5000,
            0,
            0,
            0,
            79200,
            799200,
            '2026-10-15',
        ];
        $sheet->fromArray([$row1], null, 'A2');

        $tempPath = tempnam(sys_get_temp_dir(), 'claim_extended_test_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $uploadedFile = new UploadedFile($tempPath, 'claim_extended.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        // 2. Upload Excel via Web API endpoint
        $uploadResponse = $this->withHeaders($this->getAuthHeaders())
            ->postJson('/api/distributor-channel/v1/claims/upload', [
                'file' => $uploadedFile,
            ]);

        $uploadResponse->assertStatus(201);
        $batchId = $uploadResponse->json('data.batch_id');
        $this->assertNotNull($batchId);
        $this->assertEquals('DEPO SURABAYA', $uploadResponse->json('data.depo'));

        // 3. Check Batch list API includes depo
        $batchListResponse = $this->withHeaders($this->getAuthHeaders())
            ->getJson('/api/distributor-channel/v1/claims/batches');
        $batchListResponse->assertStatus(200);
        $batches = $batchListResponse->json('data.data');
        $this->assertNotEmpty($batches);
        $this->assertEquals('DEPO SURABAYA', $batches[0]['depo']);

        // 4. Check Results API includes all extended fields appended to each result
        $resultsResponse = $this->withHeaders($this->getAuthHeaders())
            ->getJson('/api/distributor-channel/v1/claims/results?batch_id=' . $batchId);

        $resultsResponse->assertStatus(200);
        $results = $resultsResponse->json('data');
        $this->assertCount(1, $results);

        $res = $results[0];
        $this->assertEquals('VALID_PROGRAM', $res['status']);
        $this->assertEquals('DEPO SURABAYA', $res['depo']);
        $this->assertEquals('OUTLET-001', $res['distributor_customer_code']);
        $this->assertEquals('TOKO BERKAH', $res['distributor_customer_name']);
        $this->assertEquals('JL. RUNGKUT 45', $res['address']);
        $this->assertEquals('SURABAYA', $res['city']);
        $this->assertEquals('RUNGKUT', $res['district']);
        $this->assertEquals('SALES-AHMAD', $res['salesman_name']);
        $this->assertEquals('REGULER', $res['item_category']);
        $this->assertEquals('KREDIT', $res['transaction_type']);
        $this->assertEquals('RETAIL', $res['channel']);
        $this->assertEquals('INV-WEB-001', $res['invoice_no']);
        $this->assertEquals(750000.0, (float)$res['dpp']);
        $this->assertEquals(25000.0, (float)$res['diskon_1']);
        $this->assertEquals(5000.0, (float)$res['diskon_2']);
        $this->assertEquals(0.0, (float)$res['diskon_3']);
        $this->assertEquals(79200.0, (float)$res['ppn']);
        $this->assertEquals(799200.0, (float)$res['net']);

        // 5. Check Export Excel API succeeds
        $exportResponse = $this->withHeaders($this->getAuthHeaders())
            ->get('/api/distributor-channel/v1/claims/results/export?batch_id=' . $batchId);

        $exportResponse->assertStatus(200);
        $exportResponse->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        @unlink($tempPath);
    }
}
