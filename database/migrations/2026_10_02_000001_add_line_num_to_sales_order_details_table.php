<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sales_order_details')) {
            Schema::table('sales_order_details', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_order_details', 'line_num')) {
                    $table->integer('line_num')->nullable()->after('baseline')->comment('SAP LineNum based on ItemCode');
                }
            });

            // Backfill existing line_num from baseline if baseline is set
            try {
                if (Schema::hasColumn('sales_order_details', 'baseline')) {
                    DB::table('sales_order_details')
                        ->whereNull('line_num')
                        ->whereNotNull('baseline')
                        ->update([
                            'line_num' => DB::raw('baseline')
                        ]);
                }
            } catch (\Throwable $e) {
                // Ignore backfill errors
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales_order_details') && Schema::hasColumn('sales_order_details', 'line_num')) {
            Schema::table('sales_order_details', function (Blueprint $table) {
                $table->dropColumn('line_num');
            });
        }
    }
};
