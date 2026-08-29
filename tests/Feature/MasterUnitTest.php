<?php

namespace Tests\Feature;

use App\Models\MasterUnit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterUnitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_can_get_master_units_list(): void
    {
        Sanctum::actingAs($this->user);

        MasterUnit::create([
            'unit_code' => 'UNIT1',
            'unit_name' => 'Unit Pabrik 1',
            'description' => 'Pabrik Garam Unit 1',
            'status' => 'ACTIVE',
        ]);

        MasterUnit::create([
            'unit_code' => 'UNIT2',
            'unit_name' => 'Unit Pabrik 2',
            'description' => 'Pabrik Garam Unit 2',
            'status' => 'ACTIVE',
        ]);

        $response = $this->getJson('/api/distributor-channel/v1/master-units');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Daftar master unit berhasil diambil.',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_can_search_master_units(): void
    {
        Sanctum::actingAs($this->user);

        MasterUnit::create([
            'unit_code' => 'HO',
            'unit_name' => 'Head Office Surabaya',
            'status' => 'ACTIVE',
        ]);

        MasterUnit::create([
            'unit_code' => 'PABRIK_GRS',
            'unit_name' => 'Pabrik Manyar Gresik',
            'status' => 'ACTIVE',
        ]);

        $response = $this->getJson('/api/distributor-channel/v1/master-units?search=Office');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.unit_code', 'HO');
    }

    public function test_can_create_master_unit(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'unit_code' => 'UNIT3',
            'unit_name' => 'Unit Produksi 3',
            'description' => 'Gudang & Produksi Unit 3',
            'status' => 'ACTIVE',
        ];

        $response = $this->postJson('/api/distributor-channel/v1/master-units', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Master unit berhasil dibuat.',
            ])
            ->assertJsonPath('data.unit_code', 'UNIT3')
            ->assertJsonPath('data.unit_name', 'Unit Produksi 3')
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertDatabaseHas('master_units', [
            'unit_code' => 'UNIT3',
            'unit_name' => 'Unit Produksi 3',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_cannot_create_duplicate_unit_code(): void
    {
        Sanctum::actingAs($this->user);

        MasterUnit::create([
            'unit_code' => 'UNIT1',
            'unit_name' => 'Unit 1 Exists',
            'status' => 'ACTIVE',
        ]);

        $payload = [
            'unit_code' => 'UNIT1',
            'unit_name' => 'Unit 1 Duplicate',
            'status' => 'ACTIVE',
        ];

        $response = $this->postJson('/api/distributor-channel/v1/master-units', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_code']);
    }

    public function test_can_show_master_unit_detail(): void
    {
        Sanctum::actingAs($this->user);

        $unit = MasterUnit::create([
            'unit_code' => 'UNIT1',
            'unit_name' => 'Unit Produksi 1',
            'status' => 'ACTIVE',
        ]);

        Warehouse::create([
            'whs_code' => 'WHS-P1',
            'whs_name' => 'Gudang Pabrik 1',
            'master_unit_id' => 'UNIT1',
            'status' => 'ACTIVE',
        ]);

        $response = $this->getJson("/api/distributor-channel/v1/master-units/{$unit->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Detail master unit berhasil diambil.',
            ])
            ->assertJsonPath('data.unit_code', 'UNIT1')
            ->assertJsonCount(1, 'data.warehouses')
            ->assertJsonPath('data.warehouses.0.whs_code', 'WHS-P1');
    }

    public function test_can_update_master_unit(): void
    {
        Sanctum::actingAs($this->user);

        $unit = MasterUnit::create([
            'unit_code' => 'UNIT1',
            'unit_name' => 'Unit Produksi 1',
            'status' => 'ACTIVE',
        ]);

        $payload = [
            'unit_name' => 'Unit Produksi 1 - Revised',
            'description' => 'Updated description',
            'status' => 'ACTIVE',
        ];

        $response = $this->putJson("/api/distributor-channel/v1/master-units/{$unit->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Master unit berhasil diperbarui.',
            ])
            ->assertJsonPath('data.unit_name', 'Unit Produksi 1 - Revised');

        $this->assertDatabaseHas('master_units', [
            'id' => $unit->id,
            'unit_name' => 'Unit Produksi 1 - Revised',
        ]);
    }

    public function test_can_delete_master_unit(): void
    {
        Sanctum::actingAs($this->user);

        $unit = MasterUnit::create([
            'unit_code' => 'UNIT_DEL',
            'unit_name' => 'Unit Will Delete',
            'status' => 'ACTIVE',
        ]);

        $response = $this->deleteJson("/api/distributor-channel/v1/master-units/{$unit->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Master unit berhasil dihapus.',
            ]);

        $this->assertDatabaseMissing('master_units', [
            'id' => $unit->id,
        ]);
    }
}
