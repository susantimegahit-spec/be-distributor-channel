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
        if (Schema::connection($this->connection)->hasTable('production_orders')) {
            Schema::connection($this->connection)->table('production_orders', function (Blueprint $table) {
                try {
                    $table->dropForeign(['production_bom_id']);
                } catch (\Throwable $e) {
                    // Ignore if foreign key constraint already removed
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('production_orders') && Schema::connection($this->connection)->hasTable('production_boms')) {
            Schema::connection($this->connection)->table('production_orders', function (Blueprint $table) {
                $table->foreign('production_bom_id')->references('id')->on('production.production_boms')->onDelete('set null');
            });
        }
    }
};
