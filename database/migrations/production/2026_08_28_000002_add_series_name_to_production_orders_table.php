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
                if (!Schema::connection($this->connection)->hasColumn('production_orders', 'series_name')) {
                    $table->string('series_name', 100)->nullable()->after('series')->comment('Series Name (e.g. SBY26-08)');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('production_orders')) {
            Schema::connection($this->connection)->table('production_orders', function (Blueprint $table) {
                if (Schema::connection($this->connection)->hasColumn('production_orders', 'series_name')) {
                    $table->dropColumn('series_name');
                }
            });
        }
    }
};
