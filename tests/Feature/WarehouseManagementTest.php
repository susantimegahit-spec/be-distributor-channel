<?php

namespace Tests\Feature;

use App\Models\MasterUnit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected MasterUnit $unit1;
    protected MasterUnit $unit2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->unit1 = MasterUnit::create([
            'unit_code' => 'UNIT1',
            'unit_name' => 'Unit Produksi 1',
            'status' => 'ACTIVE',
        ]);

        $this->unit2 = MasterUnit::create([
            'unit_code' => 'UNIT2',
            'unit_name' => 'Unit Produksi 2',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_can_list_warehouses_with_unit_eager_loaded(): void
    {
        Sanctum::actingAs($this->user);

        Warehouse::create([
            'whs_code' => 'WHS01',
            'whs_name' => 'Gudang Utama 1',
            'master_unit_id' => 'UNIT1',
            'status' => 'ACTIVE',
        ]);

        Warehouse::create([
            'whs_code' => 'WHS02',
            'whs_name' => 'Gudang Utama 2',
            'master_unit_id' => 'UNIT2',
            'status' => 'ACTIVE',
        ]);

        $response = $this->getJson('/api/distributor-channel/v1/warehouses');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Daftar master gudang berhasil diambil.',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.whs_code', 'WHS01')
            ->assertJsonPath('data.0.unit.unit_code', 'UNIT1')
            ->assertJsonPath('data.0.unit_code', 'UNIT1')
            ->assertJsonPath('data.0.unit_name', 'Unit Produksi 1')
            ->assertJsonPath('data.1.whs_code', 'WHS02')
            ->assertJsonPath('data.1.unit.unit_code', 'UNIT2');
    }

    public function test_can_filter_warehouses_by_master_unit_id(): void
    {
        Sanctum::actingAs($this->user);

        Warehouse::create([
            'whs_code' => 'WHS01',
            'whs_name' => 'Gudang Unit 1',
            'master_unit_id' => 'UNIT1',
            'status' => 'ACTIVE',
        ]);

        Warehouse::create([
            'whs_code' => 'WHS02',
            'whs_name' => 'Gudang Unit 2',
            'master_unit_id' => 'UNIT2',
            'status' => 'ACTIVE',
        ]);

        $response = $this->getJson('/api/distributor-channel/v1/warehouses?master_unit_id=UNIT1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.whs_code', 'WHS01')
            ->assertJsonPath('data.0.master_unit_id', 'UNIT1');
    }

    public function test_can_create_warehouse_with_master_unit(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'whs_code' => 'WHS03',
            'whs_name' => 'Gudang Baru Unit 1',
            'master_unit_id' => 'UNIT1',
            'status' => 'ACTIVE',
        ];

        $response = $this->postJson('/api/distributor-channel/v1/warehouses', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Data master gudang berhasil ditambahkan.',
            ])
            ->assertJsonPath('data.whs_code', 'WHS03')
            ->assertJsonPath('data.master_unit_id', 'UNIT1')
            ->assertJsonPath('data.unit.unit_code', 'UNIT1')
            ->assertJsonPath('data.unit_name', 'Unit Produksi 1');

        $this->assertDatabaseHas('warehouses', [
            'whs_code' => 'WHS03',
            'master_unit_id' => 'UNIT1',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_can_show_warehouse_detail_with_unit(): void
    {
        Sanctum::actingAs($this->user);

        $whs = Warehouse::create([
            'whs_code' => 'WHS01',
            'whs_name' => 'Gudang Surabaya',
            'master_unit_id' => 'UNIT1',
            'status' => 'ACTIVE',
        ]);

        $response = $this->getJson("/api/distributor-channel/v1/warehouses/{$whs->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Detail master gudang berhasil diambil.',
            ])
            ->assertJsonPath('data.whs_code', 'WHS01')
            ->assertJsonPath('data.unit.unit_name', 'Unit Produksi 1');
    }

    public function test_can_update_warehouse_and_assign_unit(): void
    {
        Sanctum::actingAs($this->user);

        $whs = Warehouse::create([
            'whs_code' => 'WHS01',
            'whs_name' => 'Gudang Awal Tanpa Unit',
            'master_unit_id' => null,
            'status' => 'ACTIVE',
        ]);

        // User edits warehouse and assigns Unit 2
        $payload = [
            'whs_name' => 'Gudang Assigned Unit 2',
            'master_unit_id' => 'UNIT2',
            'status' => 'ACTIVE',
        ];

        $response = $this->putJson("/api/distributor-channel/v1/warehouses/{$whs->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data master gudang berhasil diperbarui.',
            ])
            ->assertJsonPath('data.master_unit_id', 'UNIT2')
            ->assertJsonPath('data.unit.unit_code', 'UNIT2');

        $this->assertDatabaseHas('warehouses', [
            'id' => $whs->id,
            'master_unit_id' => 'UNIT2',
        ]);
    }

    public function test_can_delete_warehouse(): void
    {
        Sanctum::actingAs($this->user);

        $whs = Warehouse::create([
            'whs_code' => 'WHS_DEL',
            'whs_name' => 'Gudang Hapus',
            'status' => 'ACTIVE',
        ]);

        $response = $this->deleteJson("/api/distributor-channel/v1/warehouses/{$whs->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data master gudang berhasil dihapus.',
            ]);

        $this->assertDatabaseMissing('warehouses', [
            'id' => $whs->id,
        ]);
    }

    public function test_sync_from_sap_preserves_existing_unit_assignment(): void
    {
        Sanctum::actingAs($this->user);

        // Pre-existing warehouse with unit assigned
        $whs = Warehouse::create([
            'whs_code' => 'WHS_SAP_01',
            'whs_name' => 'Old Name from SAP',
            'master_unit_id' => 'UNIT1',
            'status' => 'ACTIVE',
        ]);

        Http::fake([
            '*/api/SearchWH' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Success',
                'Result' => [
                    [
                        'WhsCode' => 'WHS_SAP_01',
                        'WhsName' => 'Updated Name from SAP',
                    ],
                    [
                        'WhsCode' => 'WHS_SAP_02',
                        'WhsName' => 'Brand New Warehouse',
                    ]
                ]
            ], 200)
        ]);

        $response = $this->postJson('/api/distributor-channel/v1/warehouses/sync');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data master gudang berhasil disinkronisasi dari SAP.',
            ]);

        // Verify that WHS_SAP_01 name was updated from SAP, but master_unit_id is STILL UNIT1
        $this->assertDatabaseHas('warehouses', [
            'whs_code' => 'WHS_SAP_01',
            'whs_name' => 'Updated Name from SAP',
            'master_unit_id' => 'UNIT1',
        ]);

        // Verify new warehouse was inserted
        $this->assertDatabaseHas('warehouses', [
            'whs_code' => 'WHS_SAP_02',
            'whs_name' => 'Brand New Warehouse',
            'master_unit_id' => null,
        ]);
    }
}
