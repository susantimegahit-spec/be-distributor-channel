<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class WarehouseOriginTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Warehouse $warehouse;
    protected string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create user
        $this->user = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make($this->password),
            'is_active' => true,
        ]);

        // Create master warehouse in public schema
        $this->warehouse = Warehouse::create([
            'whs_code' => 'WHS01',
            'whs_name' => 'Gudang Utama Surabaya',
            'status' => 1,
        ]);
    }

    public function test_get_warehouse_origins_list(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        WarehouseOrigin::create([
            'whs_name_origin' => 'Origin SBY',
            'whs_code' => 'WHS01',
            'whs_name' => 'Gudang Utama Surabaya',
            'street' => 'Jl. Bubutan No. 10',
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/ekspedisi/origins');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.whs_name_origin', 'Origin SBY')
            ->assertJsonPath('data.data.0.whs_code', 'WHS01')
            ->assertJsonPath('data.data.0.warehouse.whs_name', 'Gudang Utama Surabaya');
    }

    public function test_store_warehouse_origin(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $payload = [
            'whs_name_origin' => 'Gudang Asal Jakarta',
            'whs_code' => 'WHS01', // references existing whs_code
            'street' => 'Jl. Sudirman No. 100',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/ekspedisi/origins', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.whs_name_origin', 'Gudang Asal Jakarta')
            ->assertJsonPath('data.whs_code', 'WHS01')
            // whs_name must be automatically filled based on whs_code
            ->assertJsonPath('data.whs_name', 'Gudang Utama Surabaya');

        $this->assertDatabaseHas('warehouse_origins', [
            'whs_name_origin' => 'Gudang Asal Jakarta',
            'whs_code' => 'WHS01',
            'whs_name' => 'Gudang Utama Surabaya',
        ], 'pgsql_ekspedisi');
    }

    public function test_show_warehouse_origin(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $origin = WarehouseOrigin::create([
            'whs_name_origin' => 'Origin SBY',
            'whs_code' => 'WHS01',
            'whs_name' => 'Gudang Utama Surabaya',
            'street' => 'Jl. Bubutan No. 10',
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/ekspedisi/origins/' . $origin->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.whs_name_origin', 'Origin SBY')
            ->assertJsonPath('data.whs_code', 'WHS01');
    }

    public function test_update_warehouse_origin(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $origin = WarehouseOrigin::create([
            'whs_name_origin' => 'Origin SBY',
            'whs_code' => 'WHS01',
            'whs_name' => 'Gudang Utama Surabaya',
            'street' => 'Jl. Bubutan No. 10',
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
        ]);

        // Create another warehouse to test whs_code update
        Warehouse::create([
            'whs_code' => 'WHS02',
            'whs_name' => 'Gudang Cabang Gresik',
            'status' => 1,
        ]);

        $payload = [
            'whs_name_origin' => 'Origin Updated',
            'whs_code' => 'WHS02',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/distributor-channel/v1/ekspedisi/origins/' . $origin->id, $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.whs_name_origin', 'Origin Updated')
            ->assertJsonPath('data.whs_code', 'WHS02')
            ->assertJsonPath('data.whs_name', 'Gudang Cabang Gresik');

        $this->assertDatabaseHas('warehouse_origins', [
            'id' => $origin->id,
            'whs_name_origin' => 'Origin Updated',
            'whs_code' => 'WHS02',
            'whs_name' => 'Gudang Cabang Gresik',
        ], 'pgsql_ekspedisi');
    }

    public function test_delete_warehouse_origin(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $origin = WarehouseOrigin::create([
            'whs_name_origin' => 'Origin SBY',
            'whs_code' => 'WHS01',
            'whs_name' => 'Gudang Utama Surabaya',
            'street' => 'Jl. Bubutan No. 10',
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/distributor-channel/v1/ekspedisi/origins/' . $origin->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('warehouse_origins', ['id' => $origin->id], 'pgsql_ekspedisi');
    }

    public function test_upload_warehouse_origins(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $uploadServiceMock = $this->mock(\App\Modules\Ekspedisi\Services\WarehouseOriginUploadService::class);
        $uploadServiceMock->shouldReceive('uploadOrigins')
            ->once()
            ->andReturn([
                'processed_count' => 1,
                'created_count' => 1,
                'updated_count' => 0,
                'errors' => [],
            ]);

        $file = UploadedFile::fake()->create('origins.xlsx', 100);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/api/distributor-channel/v1/ekspedisi/origins/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.processed_count', 1)
            ->assertJsonPath('data.created_count', 1);
    }
}
