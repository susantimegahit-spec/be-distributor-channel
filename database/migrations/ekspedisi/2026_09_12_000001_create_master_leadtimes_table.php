<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'pgsql_ekspedisi';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conn = $this->connection;
        $isSqlite = Schema::connection($conn)->getConnection()->getDriverName() === 'sqlite';

        if (!Schema::connection($conn)->hasTable('master_leadtimes')) {
            Schema::connection($conn)->create('master_leadtimes', function (Blueprint $table) use ($isSqlite) {
                $table->id();

                // Origin Warehouse (Muatan dari gudang mana)
                $table->unsignedBigInteger('warehouse_origin_id')->nullable()->index()->comment('Reference to warehouse origin ID');
                $table->string('origin_warehouse_code', 50)->nullable()->index()->comment('Origin warehouse code / SAP WhsCode');
                $table->string('origin_warehouse_name', 255)->comment('Origin warehouse / loading location name');
                $table->string('origin_city', 100)->nullable()->comment('Origin city or region');

                // Destination (Tujuan)
                $table->unsignedBigInteger('destination_id')->nullable()->index()->comment('Reference to destination / customer shipto ID');
                $table->unsignedBigInteger('destination_regency_id')->nullable()->index()->comment('Reference to destination regency ID');
                $table->string('destination_code', 50)->nullable()->index()->comment('Destination code');
                $table->string('destination_name', 255)->comment('Destination name / area / recipient location');
                $table->string('destination_city', 100)->nullable()->comment('Destination city or regency name');
                $table->string('destination_province', 100)->nullable()->comment('Destination province name');

                // Lead Time Metrics (Rata-rata leadtime)
                $table->decimal('avg_lead_time_days', 8, 2)->comment('Average lead time in days');
                $table->decimal('min_lead_time_days', 8, 2)->nullable()->comment('Minimum lead time in days');
                $table->decimal('max_lead_time_days', 8, 2)->nullable()->comment('Maximum lead time in days');
                $table->string('lead_time_unit', 20)->default('DAYS')->comment('Unit of lead time (DAYS, HOURS)');

                // Logistics & Operational Details
                $table->string('transport_mode', 50)->nullable()->comment('Mode of transport: LAND, SEA, AIR');
                $table->decimal('distance_km', 10, 2)->nullable()->comment('Estimated distance in kilometers');
                $table->string('status', 20)->default('ACTIVE')->index()->comment('Status: ACTIVE, INACTIVE');
                $table->text('remarks')->nullable()->comment('Additional notes / remarks');

                // Audit Trails
                $table->unsignedBigInteger('created_by')->nullable()->comment('User who created this record');
                $table->unsignedBigInteger('updated_by')->nullable()->comment('User who updated this record');
                $table->timestamps();

                // Foreign Keys
                $originTable = $isSqlite ? 'warehouse_origins' : 'ekspedisi.warehouse_origins';
                $regencyTable = $isSqlite ? 'regencies' : 'ekspedisi.regencies';
                $userTable = $isSqlite ? 'users' : 'public.users';

                $table->foreign('warehouse_origin_id')->references('id')->on($originTable)->onDelete('set null');
                $table->foreign('destination_regency_id')->references('id')->on($regencyTable)->onDelete('set null');
                $table->foreign('created_by')->references('id')->on($userTable)->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on($userTable)->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('master_leadtimes');
    }
};
