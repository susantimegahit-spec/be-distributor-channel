<?php

namespace Tests\Feature;

use App\Models\ProductionBom;
use App\Models\ProductionBomItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductionBomImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Production Admin',
            'username' => 'prodadmin',
            'email' => 'prodadmin@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    /**
     * Test importing multiple BOMs from JSON flat rows array matching the user's Excel column headers.
     */
    public function test_import_boms_from_json_rows_array_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // 15 rows with 3 distinct BOM IDs (7 for BOM 1, 7 for BOM 2, 1 for BOM 3)
        $rows = [
            // BOM ID 1 (A07) - Row 1
            [
                'NO' => 1,
                'BOM ID' => '1',
                'Prod ItemCode' => 'A07',
                'Prod ItemName' => 'GARAMI 200 M @ 4 KG/BAL',
                'Alternative BOM' => 1,
                'BOM Header Qty' => 1,
                'Prod UoM' => 'Bal',
                'Prod Warehouse' => 'PRD01-05',
                'Prod Warehouse Name' => 'GUDANG PRODUKSI SURABAYA - UNIT 5',
                'BPL ID' => '0',
                'Business Place' => 'Surabaya',
                'BOM Remarks' => 'BOM Garami 4KG',
                'Header Cabang' => 'SBY',
                'Header Business Unit' => 'UNIT5',
                'Header Department' => 'PROD',
                'Line No' => 1,
                'Component Type Code' => '4',
                'Component Type' => 'Item',
                'Component ItemCode' => 'RAW-SALT-01',
                'Component ItemName' => 'Garam Curah Halus',
                'Component Qty BOM' => 4.0000,
                'Component UoM' => 'KG',
                'Qty BOM per 1 FG' => 4.0000,
                'Component Warehouse' => 'RAW01-05',
                'Component Warehouse Name' => 'Gudang Bahan Baku',
                'Issue Method' => 'B',
                'Component Cabang' => 'SBY',
            ],
            // BOM ID 1 (A07) - Row 2
            [
                'NO' => 2,
                'BOM ID' => '1',
                'Prod ItemCode' => 'A07',
                'Prod ItemName' => 'GARAMI 200 M @ 4 KG/BAL',
                'Alternative BOM' => 1,
                'BOM Header Qty' => 1,
                'Prod UoM' => 'Bal',
                'Prod Warehouse' => 'PRD01-05',
                'Line No' => 2,
                'Component Type' => 'Item',
                'Component ItemCode' => 'PKG-PLASTIC-200M',
                'Component ItemName' => 'Plastik Kemasan 200M',
                'Component Qty BOM' => 20.0000,
                'Component UoM' => 'PCS',
                'Component Warehouse' => 'PKG01-05',
                'Issue Method' => 'M',
            ],
            // BOM ID 1 (A07) - Row 3 (Resource)
            [
                'NO' => 3,
                'BOM ID' => '1',
                'Prod ItemCode' => 'A07',
                'Alternative BOM' => 1,
                'Line No' => 3,
                'Component Type Code' => '290',
                'Component Type' => 'Resource',
                'Component ItemCode' => 'RES-MACHINE-SEAL',
                'Component ItemName' => 'Mesin Sealing Garami',
                'Component Qty BOM' => 0.0500,
                'Component UoM' => 'HR',
                'Issue Method' => 'B',
            ],
            // BOM ID 2 (A08) - Row 1
            [
                'NO' => 4,
                'BOM ID' => '2',
                'Prod ItemCode' => 'A08',
                'Prod ItemName' => 'GARAMI 200 M @ 8 KG/BAL',
                'Alternative BOM' => 1,
                'BOM Header Qty' => 1,
                'Prod UoM' => 'Bal',
                'Prod Warehouse' => 'PRD01-05',
                'Line No' => 1,
                'Component Type' => 'Item',
                'Component ItemCode' => 'RAW-SALT-01',
                'Component ItemName' => 'Garam Curah Halus',
                'Component Qty BOM' => 8.0000,
                'Component UoM' => 'KG',
                'Issue Method' => 'B',
            ],
            // BOM ID 2 (A08) - Row 2
            [
                'NO' => 5,
                'BOM ID' => '2',
                'Prod ItemCode' => 'A08',
                'Prod ItemName' => 'GARAMI 200 M @ 8 KG/BAL',
                'Alternative BOM' => 1,
                'BOM Header Qty' => 1,
                'Prod UoM' => 'Bal',
                'Prod Warehouse' => 'PRD01-05',
                'Line No' => 2,
                'Component Type' => 'Item',
                'Component ItemCode' => 'PKG-PLASTIC-200M',
                'Component ItemName' => 'Plastik Kemasan 200M',
                'Component Qty BOM' => 40.0000,
                'Component UoM' => 'PCS',
                'Issue Method' => 'M',
            ],
            // BOM ID 3 (A26) - Row 1
            [
                'NO' => 6,
                'BOM ID' => '3',
                'Prod ItemCode' => 'A26',
                'Prod ItemName' => 'TOP 250 M @ 10 KG / BAL',
                'Alternative BOM' => 1,
                'BOM Header Qty' => 1,
                'Prod UoM' => 'Bal',
                'Prod Warehouse' => 'PRD01-05',
                'Line No' => 1,
                'Component Type' => 'Item',
                'Component ItemCode' => 'RAW-SALT-TOP',
                'Component ItemName' => 'Garam Top 250M',
                'Component Qty BOM' => 10.0000,
                'Component UoM' => 'KG',
                'Issue Method' => 'B',
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/boms/import', [
            'rows' => $rows,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_boms' => 3,
                    'total_boms_created' => 3,
                    'total_boms_updated' => 0,
                    'total_items_created' => 6,
                ],
            ]);

        // Verify Database Records
        $this->assertDatabaseHas('production_boms', [
            'code' => 'A07',
            'alternate' => 1,
            'to_whs' => 'PRD01-05',
            'sap_doc_num' => '1',
        ], 'pgsql_production');

        $this->assertDatabaseHas('production_boms', [
            'code' => 'A08',
            'alternate' => 1,
            'to_whs' => 'PRD01-05',
            'sap_doc_num' => '2',
        ], 'pgsql_production');

        $this->assertDatabaseHas('production_boms', [
            'code' => 'A26',
            'alternate' => 1,
            'sap_doc_num' => '3',
        ], 'pgsql_production');

        $bom1 = ProductionBom::where('code', 'A07')->first();
        $this->assertNotNull($bom1);
        $this->assertEquals(3, $bom1->details()->count());

        $bom2 = ProductionBom::where('code', 'A08')->first();
        $this->assertNotNull($bom2);
        $this->assertEquals(2, $bom2->details()->count());

        $bom3 = ProductionBom::where('code', 'A26')->first();
        $this->assertNotNull($bom3);
        $this->assertEquals(1, $bom3->details()->count());

        // Verify Component fields
        $this->assertDatabaseHas('production_bom_items', [
            'production_bom_id' => $bom1->id,
            'code' => 'RAW-SALT-01',
            'quantity' => 4.0000,
            'type' => 'Item',
            'issue_mthd' => 'B',
        ], 'pgsql_production');

        $this->assertDatabaseHas('production_bom_items', [
            'production_bom_id' => $bom1->id,
            'code' => 'RES-MACHINE-SEAL',
            'quantity' => 0.0500,
            'type' => 'Resource',
            'issue_mthd' => 'B',
        ], 'pgsql_production');
    }

    /**
     * Test re-importing the same BOM updates the header and refreshes detail items.
     */
    public function test_reimport_boms_updates_existing_records(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Initial import
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/boms/import', [
            'rows' => [
                [
                    'BOM ID' => '1',
                    'Prod ItemCode' => 'A07',
                    'Alternative BOM' => 1,
                    'BOM Header Qty' => 1,
                    'Prod Warehouse' => 'PRD01-05',
                    'Line No' => 1,
                    'Component ItemCode' => 'OLD-COMP-01',
                    'Component Qty BOM' => 1.0,
                ],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('production_bom_items', [
            'code' => 'OLD-COMP-01',
        ], 'pgsql_production');

        // Re-import updated BOM (same code A07 + alternate 1, new components)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/boms/import', [
            'rows' => [
                [
                    'BOM ID' => '1',
                    'Prod ItemCode' => 'A07',
                    'Alternative BOM' => 1,
                    'BOM Header Qty' => 2.5,
                    'Prod Warehouse' => 'PRD01-05',
                    'Line No' => 1,
                    'Component ItemCode' => 'NEW-COMP-01',
                    'Component Qty BOM' => 5.0,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_boms' => 1,
                    'total_boms_created' => 0,
                    'total_boms_updated' => 1,
                    'total_items_created' => 1,
                ],
            ]);

        $this->assertDatabaseHas('production_boms', [
            'code' => 'A07',
            'qty' => 2.5,
        ], 'pgsql_production');

        $this->assertDatabaseHas('production_bom_items', [
            'code' => 'NEW-COMP-01',
        ], 'pgsql_production');

        $this->assertDatabaseMissing('production_bom_items', [
            'code' => 'OLD-COMP-01',
        ], 'pgsql_production');
    }

    /**
     * Test uploading actual Excel spreadsheet (.xlsx file).
     */
    public function test_import_boms_via_excel_file_upload(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create in-memory Excel file
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $sheet->fromArray([
            [
                'NO', 'BOM ID', 'Prod ItemCode', 'Prod ItemName', 'Alternative BOM',
                'BOM Header Qty', 'Prod UoM', 'Prod Warehouse', 'BOM Remarks',
                'Line No', 'Component Type', 'Component ItemCode', 'Component Qty BOM', 'Issue Method'
            ],
            [
                1, '10', 'B01', 'GARAM BERYODIUM 500G', 1,
                1, 'BAL', 'PRD01-01', 'Remarks Excel',
                1, 'Item', 'RAW-SALT-500', 0.5, 'B'
            ],
            [
                2, '10', 'B01', 'GARAM BERYODIUM 500G', 1,
                1, 'BAL', 'PRD01-01', 'Remarks Excel',
                2, 'Item', 'PKG-PLASTIC-500', 1.0, 'M'
            ],
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'bom_test_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $uploadedFile = new UploadedFile(
            $tempPath,
            'master_bom.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/api/distributor-channel/v1/production/boms/upload-excel', [
            'file' => $uploadedFile,
        ], [
            'Accept' => 'application/json',
        ]);

        @unlink($tempPath);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_boms' => 1,
                    'total_items_created' => 2,
                ],
            ]);

        $this->assertDatabaseHas('production_boms', [
            'code' => 'B01',
            'alternate' => 1,
            'sap_doc_num' => '10',
        ], 'pgsql_production');

        $this->assertDatabaseHas('production_bom_items', [
            'code' => 'RAW-SALT-500',
            'quantity' => 0.5,
        ], 'pgsql_production');
    }

    /**
     * Test invalid payload returns validation error.
     */
    public function test_import_boms_invalid_payload_error(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/boms/import', [
            'invalid_key' => 'test',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test fields with '?', '0 ?', '-', 'null', 'N/A' are converted to null.
     */
    public function test_import_boms_converts_question_mark_and_placeholders_to_null(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/boms/import', [
            'rows' => [
                [
                    'BOM ID' => '99',
                    'Prod ItemCode' => 'C99',
                    'Prod Warehouse' => '?',
                    'BOM Remarks' => '0 ?',
                    'Header Cabang' => '-',
                    'Header Business Unit' => 'NULL',
                    'Header Department' => 'N/A',
                    'Line No' => 1,
                    'Component ItemCode' => 'RAW-99',
                    'Component Warehouse' => '?',
                    'Component Cabang' => '?',
                    'Component Remarks' => '?',
                ],
            ],
        ]);

        $response->assertStatus(200);

        $bom = ProductionBom::where('code', 'C99')->first();
        $this->assertNotNull($bom);
        $this->assertNull($bom->to_whs);
        $this->assertNull($bom->comments);
        $this->assertNull($bom->ocr_code);
        $this->assertNull($bom->ocr_code2);
        $this->assertNull($bom->ocr_code3);

        $detail = $bom->details()->first();
        $this->assertNotNull($detail);
        $this->assertNull($detail->warehouse);
        $this->assertNull($detail->ocr_code);
        $this->assertNull($detail->comments);
    }
}

