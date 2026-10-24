<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
        $connectionName = $this->connection;
        $driver = config("database.connections.{$connectionName}.driver", config('database.default'));

        // 1. Drop foreign keys on production_boms
        if (Schema::connection($connectionName)->hasTable('production_boms')) {
            if ($driver === 'pgsql') {
                try {
                    DB::connection($connectionName)->statement('ALTER TABLE production.production_boms DROP CONSTRAINT IF EXISTS production_boms_code_foreign');
                } catch (\Throwable $e) {
                    // Ignore if constraint does not exist
                }

                try {
                    DB::connection($connectionName)->statement('ALTER TABLE production.production_boms DROP CONSTRAINT IF EXISTS production_boms_to_whs_foreign');
                } catch (\Throwable $e) {
                    // Ignore if constraint does not exist
                }
            } else {
                Schema::connection($connectionName)->table('production_boms', function (Blueprint $table) {
                    try {
                        $table->dropForeign(['code']);
                    } catch (\Throwable $e) {
                    }
                    try {
                        $table->dropForeign(['to_whs']);
                    } catch (\Throwable $e) {
                    }
                });
            }
        }

        // 2. Drop foreign key on production_bom_items (warehouse)
        if (Schema::connection($connectionName)->hasTable('production_bom_items')) {
            if ($driver === 'pgsql') {
                try {
                    DB::connection($connectionName)->statement('ALTER TABLE production.production_bom_items DROP CONSTRAINT IF EXISTS production_bom_items_warehouse_foreign');
                } catch (\Throwable $e) {
                    // Ignore if constraint does not exist
                }
            } else {
                Schema::connection($connectionName)->table('production_bom_items', function (Blueprint $table) {
                    try {
                        $table->dropForeign(['warehouse']);
                    } catch (\Throwable $e) {
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-adding cross-schema foreign keys is intentionally omitted to avoid integrity failures with non-sales items
    }
};
