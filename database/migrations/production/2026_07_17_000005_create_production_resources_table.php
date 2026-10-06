<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'pgsql_production';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('production_resources')) {
            Schema::connection($this->connection)->create('production_resources', function (Blueprint $table) {
                $table->id();
                $table->string('res_code', 50)->unique()->comment('Resource Code (ResCode)');
                $table->string('res_name', 255)->comment('Resource Name (ResName)');
                $table->string('unit_of_msr', 50)->nullable()->comment('Unit of Measure (UnitOfMsr)');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('production_resources');
    }
};
