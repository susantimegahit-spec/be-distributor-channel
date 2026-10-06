<?php

namespace Tests\Unit;

use App\Models\MasterLeadtime;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterLeadtimeTest extends TestCase
{
    protected string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbPath = database_path('test_leadtime.sqlite');
        if (!file_exists($this->dbPath)) {
            touch($this->dbPath);
        }

        config([
            'database.connections.pgsql_ekspedisi' => [
                'driver' => 'sqlite',
                'database' => $this->dbPath,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        $migration = require database_path('migrations/ekspedisi/2026_09_12_000001_create_master_leadtimes_table.php');
        $migration->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('pgsql_ekspedisi');
        if (file_exists($this->dbPath)) {
            @unlink($this->dbPath);
        }

        parent::tearDown();
    }

    public function test_master_leadtime_table_has_expected_columns(): void
    {
        $schema = DB::connection('pgsql_ekspedisi')->getSchemaBuilder();

        $this->assertTrue($schema->hasTable('master_leadtimes'));

        $columns = [
            'id',
            'warehouse_origin_id',
            'origin_warehouse_code',
            'origin_warehouse_name',
            'origin_city',
            'destination_id',
            'destination_regency_id',
            'destination_code',
            'destination_name',
            'destination_city',
            'destination_province',
            'avg_lead_time_days',
            'min_lead_time_days',
            'max_lead_time_days',
            'lead_time_unit',
            'transport_mode',
            'distance_km',
            'status',
            'remarks',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue($schema->hasColumn('master_leadtimes', $column), "Column [{$column}] should exist in master_leadtimes.");
        }
    }

    public function test_can_instantiate_and_persist_master_leadtime(): void
    {
        $leadtime = new MasterLeadtime();
        $leadtime->fill([
            'origin_warehouse_code' => 'WHS-SUB',
            'origin_warehouse_name' => 'Gudang Utama Surabaya',
            'origin_city' => 'Surabaya',
            'destination_name' => 'Gudang Cabang Bandung',
            'destination_city' => 'Bandung',
            'destination_province' => 'Jawa Barat',
            'avg_lead_time_days' => 2.50,
            'min_lead_time_days' => 2.00,
            'max_lead_time_days' => 3.00,
            'lead_time_unit' => 'DAYS',
            'transport_mode' => 'LAND',
            'distance_km' => 680.00,
            'status' => 'ACTIVE',
            'remarks' => 'Direct delivery route',
        ]);
        $leadtime->save();

        $this->assertNotNull($leadtime->id);
        $this->assertEquals('2.50', $leadtime->avg_lead_time_days);
        $this->assertEquals('Gudang Utama Surabaya', $leadtime->origin_warehouse_name);
        $this->assertEquals('Gudang Cabang Bandung', $leadtime->destination_name);
    }

    public function test_relationships_are_properly_defined(): void
    {
        $leadtime = new MasterLeadtime();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $leadtime->warehouseOrigin());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $leadtime->destinationRegency());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $leadtime->creator());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $leadtime->updater());
    }
}
