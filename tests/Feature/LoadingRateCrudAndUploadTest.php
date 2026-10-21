<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\LoadingRate;
use App\Models\LoadingRateType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LoadingRateCrudAndUploadTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Item $item1;
    protected Item $item2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrCreate(
            ['username' => 'tester_loading_rate'],
            [
                'name'      => 'Tester Loading Rate',
                'email'     => 'tester_loading_rate@example.com',
                'password'  => bcrypt('password123'),
                'role'      => 'logistic',
                'is_active' => true,
            ]
        );

        $this->item1 = Item::firstOrCreate(
            ['item_code' => 'SKU-LOAD-01'],
            [
                'item_name'    => 'Garam Kasar 50kg',
                'sal_unit_msr' => 'SAK',
                'per_kg'       => 50.0,
            ]
        );

        $this->item2 = Item::firstOrCreate(
            ['item_code' => 'SKU-LOAD-02'],
            [
                'item_name'    => 'Garam Halus 25kg',
                'sal_unit_msr' => 'SAK',
                'per_kg'       => 25.0,
            ]
        );
    }

    public function test_can_list_and_create_loading_rate_types(): void
    {
        // 1. List default types (REGULAR, OVERTIME seeded by migration)
        $listRes = $this->actingAs($this->user)->getJson('/api/distributor-channel/v1/ekspedisi/loading-rate-types');
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 2. Create custom rate type
        $uniqueCode = strtoupper('SPECIAL_' . uniqid());
        $createRes = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/ekspedisi/loading-rate-types', [
            'code'        => $uniqueCode,
            'name'        => 'Tarif Khusus Hari Libur',
            'description' => 'Tarif untuk hari libur nasional',
            'is_active'   => true,
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', $uniqueCode);

        $typeId = $createRes->json('data.id');

        // 3. Show
        $showRes = $this->actingAs($this->user)->getJson("/api/distributor-channel/v1/ekspedisi/loading-rate-types/{$typeId}");
        $showRes->assertStatus(200)
            ->assertJsonPath('data.code', $uniqueCode);

        // 4. Update
        $updateRes = $this->actingAs($this->user)->putJson("/api/distributor-channel/v1/ekspedisi/loading-rate-types/{$typeId}", [
            'name' => 'Tarif Khusus Holiday Updated',
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.name', 'Tarif Khusus Holiday Updated');

        // 5. Delete
        $delRes = $this->actingAs($this->user)->deleteJson("/api/distributor-channel/v1/ekspedisi/loading-rate-types/{$typeId}");
        $delRes->assertStatus(200);
    }

    public function test_can_perform_crud_on_loading_rates(): void
    {
        $regType = LoadingRateType::firstOrCreate(['code' => 'REGULAR'], ['name' => 'Regular', 'is_active' => true]);

        // 1. Create Loading Rate
        $createRes = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/ekspedisi/loading-rates', [
            'item_code' => $this->item1->item_code,
            'rate_type' => 'REGULAR',
            'rate'      => 1500,
            'uom'       => 'SAK',
            'remarks'   => 'Ongkos kuli reguler garam 50kg',
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.item_code', $this->item1->item_code)
            ->assertJsonPath('data.rate_type', 'REGULAR')
            ->assertJsonPath('data.uom', 'SAK');

        $rateId = $createRes->json('data.id');

        // 2. Reject Duplicate
        $dupRes = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/ekspedisi/loading-rates', [
            'item_code' => $this->item1->item_code,
            'rate_type' => 'REGULAR',
            'rate'      => 2000,
        ]);
        $dupRes->assertStatus(422)->assertJsonPath('success', false);

        // 3. Show
        $showRes = $this->actingAs($this->user)->getJson("/api/distributor-channel/v1/ekspedisi/loading-rates/{$rateId}");
        $showRes->assertStatus(200)
            ->assertJsonPath('data.id', $rateId);

        // 4. Update
        $updateRes = $this->actingAs($this->user)->putJson("/api/distributor-channel/v1/ekspedisi/loading-rates/{$rateId}", [
            'rate'    => 1750,
            'remarks' => 'Penyesuaian tarif reguler',
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.rate', '1750.00');

        // 5. Index with filter
        $indexRes = $this->actingAs($this->user)->getJson("/api/distributor-channel/v1/ekspedisi/loading-rates?item_code={$this->item1->item_code}");
        $indexRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 6. Delete
        $delRes = $this->actingAs($this->user)->deleteJson("/api/distributor-channel/v1/ekspedisi/loading-rates/{$rateId}");
        $delRes->assertStatus(200);
    }

    public function test_can_upload_excel_loading_rates(): void
    {
        // Build Excel file in memory
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Kode Item', 'Nama Item', 'Type Rate', 'Biaya', 'UOM', 'Keterangan'],
            ['SKU-LOAD-01', 'Garam Kasar 50kg', 'REGULER', 1200, 'SAK', 'Tarif reguler via Excel'],
            ['SKU-LOAD-01', 'Garam Kasar 50kg', 'LEMBUR', 2200, 'SAK', 'Tarif lembur via Excel'],
            ['SKU-LOAD-02', 'Garam Halus 25kg', 'REGULAR', 800, 'SAK', 'Tarif reguler 25kg'],
            ['SKU-LOAD-02', 'Garam Halus 25kg', 'OVERTIME', 1500, 'SAK', 'Tarif lembur 25kg'],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_load_rate_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'rates.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->user)->postJson(
            '/api/distributor-channel/v1/ekspedisi/loading-rates/upload',
            ['file' => $uploadedFile]
        );

        @unlink($tmpFile);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_processed', 4);

        // Verify in database
        $r1 = LoadingRate::where('item_code', 'SKU-LOAD-01')->where('rate_type', 'REGULAR')->first();
        $this->assertNotNull($r1);
        $this->assertEquals(1200.0, (float) $r1->rate);

        $r2 = LoadingRate::where('item_code', 'SKU-LOAD-01')->where('rate_type', 'OVERTIME')->first();
        $this->assertNotNull($r2);
        $this->assertEquals(2200.0, (float) $r2->rate);
    }

    public function test_can_download_excel_template(): void
    {
        $response = $this->actingAs($this->user)->get('/api/distributor-channel/v1/ekspedisi/loading-rates/template');

        $response->assertStatus(200);
        $this->assertTrue(str_contains(
            $response->headers->get('content-type', ''),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ));
    }
}
