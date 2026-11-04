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
        // 1. Add docentry to sales_orders table
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_orders', 'docentry')) {
                    $table->integer('docentry')->nullable()->after('sap_doc_entry')->comment('SAP Sales Order DocEntry (= BaseEntry for Delivery Order)');
                }
            });

            // Backfill existing docentry from sap_doc_entry if available
            try {
                DB::table('sales_orders')
                    ->whereNull('docentry')
                    ->whereNotNull('sap_doc_entry')
                    ->update([
                        'docentry' => DB::raw('sap_doc_entry')
                    ]);
            } catch (\Throwable $e) {
                // Ignore backfill error
            }
        }

        // 2. Add baseline to sales_order_details table
        if (Schema::hasTable('sales_order_details')) {
            Schema::table('sales_order_details', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_order_details', 'baseline')) {
                    $table->integer('baseline')->nullable()->after('line_total')->comment('Zero-based line index in SAP SO for BaseLine in Delivery Order');
                }
            });

            // Backfill existing baseline for already approved sales orders
            try {
                $orders = DB::table('sales_orders')
                    ->whereNotNull('sap_doc_entry')
                    ->pluck('id');

                foreach ($orders as $orderId) {
                    $details = DB::table('sales_order_details')
                        ->where('sales_order_id', $orderId)
                        ->orderBy('id', 'asc')
                        ->get(['id']);

                    foreach ($details as $idx => $row) {
                        DB::table('sales_order_details')
                            ->where('id', $row->id)
                            ->whereNull('baseline')
                            ->update(['baseline' => $idx]);
                    }
                }
            } catch (\Throwable $e) {
                // Ignore backfill error
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales_orders') && Schema::hasColumn('sales_orders', 'docentry')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropColumn('docentry');
            });
        }

        if (Schema::hasTable('sales_order_details') && Schema::hasColumn('sales_order_details', 'baseline')) {
            Schema::table('sales_order_details', function (Blueprint $table) {
                $table->dropColumn('baseline');
            });
        }
    }
};
