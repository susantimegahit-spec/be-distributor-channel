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
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_orders', 'delivery_order_no')) {
                    $table->string('delivery_order_no', 50)->nullable()->after('sap_it_status')->comment('Delivery Order number (DocNum) from SAP');
                }
                if (!Schema::hasColumn('sales_orders', 'sap_do_doc_entry')) {
                    $table->string('sap_do_doc_entry', 50)->nullable()->after('delivery_order_no')->comment('SAP Delivery Order DocEntry');
                }
                if (!Schema::hasColumn('sales_orders', 'sap_do_doc_num')) {
                    $table->string('sap_do_doc_num', 50)->nullable()->after('sap_do_doc_entry')->comment('SAP Delivery Order DocNum');
                }
                if (!Schema::hasColumn('sales_orders', 'sap_do_status')) {
                    $table->string('sap_do_status', 30)->nullable()->after('sap_do_doc_num')->comment('Status of SAP Delivery Order (SUCCESS, FAILED, PENDING)');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $columns = ['delivery_order_no', 'sap_do_doc_entry', 'sap_do_doc_num', 'sap_do_status'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('sales_orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
