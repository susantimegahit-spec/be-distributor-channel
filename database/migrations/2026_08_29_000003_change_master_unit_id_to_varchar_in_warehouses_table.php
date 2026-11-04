<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('warehouses')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                // Drop foreign key if exists in PostgreSQL
                DB::statement('ALTER TABLE warehouses DROP CONSTRAINT IF EXISTS warehouses_master_unit_id_foreign;');
                
                // If column exists, alter type using USING clause
                if (Schema::hasColumn('warehouses', 'master_unit_id')) {
                    DB::statement('ALTER TABLE warehouses ALTER COLUMN master_unit_id TYPE VARCHAR(50) USING master_unit_id::varchar;');
                } else {
                    DB::statement('ALTER TABLE warehouses ADD COLUMN master_unit_id VARCHAR(50) NULL;');
                }
            } else {
                Schema::table('warehouses', function (Blueprint $table) {
                    if (Schema::hasColumn('warehouses', 'master_unit_id')) {
                        $table->string('master_unit_id', 50)->nullable()->change();
                    } else {
                        $table->string('master_unit_id', 50)->nullable()->after('whs_name');
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
        // Reversal logic if needed
    }
};
