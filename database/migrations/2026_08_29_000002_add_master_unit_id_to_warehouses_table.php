<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                if (!Schema::hasColumn('warehouses', 'master_unit_id')) {
                    $table->string('master_unit_id', 50)->nullable()->after('whs_name')->index()->comment('References master_units unit_code or id (varchar)');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                if (Schema::hasColumn('warehouses', 'master_unit_id')) {
                    $table->dropColumn('master_unit_id');
                }
            });
        }
    }
};
