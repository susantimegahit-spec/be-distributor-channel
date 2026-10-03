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
                if (!Schema::hasColumn('sales_orders', 'to_whs_code')) {
                    $table->string('to_whs_code', 50)->nullable()->default('VPGMN01')->after('logistic_action_by')->comment('Gudang tujuan mutasi stok ekspedisi');
                }
                if (!Schema::hasColumn('sales_orders', 'nopol')) {
                    $table->string('nopol', 50)->nullable()->after('to_whs_code')->comment('Nomor polisi kendaraan pengangkut');
                }
                if (!Schema::hasColumn('sales_orders', 'nama_supir')) {
                    $table->string('nama_supir', 150)->nullable()->after('nopol')->comment('Nama driver / pengemudi');
                }
                if (!Schema::hasColumn('sales_orders', 'sap_it_doc_entry')) {
                    $table->string('sap_it_doc_entry', 50)->nullable()->after('nama_supir')->comment('DocEntry Inventory Transfer SAP');
                }
                if (!Schema::hasColumn('sales_orders', 'sap_it_doc_num')) {
                    $table->string('sap_it_doc_num', 50)->nullable()->after('sap_it_doc_entry')->comment('DocNum Inventory Transfer SAP');
                }
                if (!Schema::hasColumn('sales_orders', 'sap_it_status')) {
                    $table->string('sap_it_status', 50)->nullable()->after('sap_it_doc_num')->comment('Status integrasi Inventory Transfer SAP');
                }
            });
        }

        if (Schema::hasTable('sales_order_logistic_logs')) {
            Schema::table('sales_order_logistic_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_order_logistic_logs', 'sap_it_doc_entry')) {
                    $table->string('sap_it_doc_entry', 50)->nullable()->after('role_name');
                }
                if (!Schema::hasColumn('sales_order_logistic_logs', 'sap_it_doc_num')) {
                    $table->string('sap_it_doc_num', 50)->nullable()->after('sap_it_doc_entry');
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
                $columns = ['to_whs_code', 'nopol', 'nama_supir', 'sap_it_doc_entry', 'sap_it_doc_num', 'sap_it_status'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('sales_orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('sales_order_logistic_logs')) {
            Schema::table('sales_order_logistic_logs', function (Blueprint $table) {
                $columns = ['sap_it_doc_entry', 'sap_it_doc_num'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('sales_order_logistic_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
