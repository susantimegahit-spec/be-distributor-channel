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
        if (!Schema::connection($this->connection)->hasTable('expedition_rates')) {
            Schema::connection($this->connection)->create('expedition_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expedition_id')->constrained('ekspedisi.expeditions')->onDelete('cascade');
                $table->foreignId('origin_regency_id')->constrained('ekspedisi.regencies')->onDelete('cascade');
                $table->foreignId('destination_district_id')->constrained('ekspedisi.districts')->onDelete('cascade');
                $table->decimal('rate_per_kg', 12, 2)->default(0);
                $table->decimal('fixed_rate', 12, 2)->default(0);
                $table->integer('estimated_days')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // Set unique constraint agar tidak ada duplikasi tarif pada rute yang sama untuk satu ekspedisi
                $table->unique(
                    ['expedition_id', 'origin_regency_id', 'destination_district_id'],
                    'uq_expedition_route_rate'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('expedition_rates');
    }
};
