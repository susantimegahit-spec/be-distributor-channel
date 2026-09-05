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
        if (Schema::hasTable('purchase_requests')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_requests', 'series')) {
                    $table->string('series', 50)->nullable()->after('pr_number');
                }
                if (!Schema::hasColumn('purchase_requests', 'req_type')) {
                    $table->string('req_type', 20)->nullable()->default('12')->after('series');
                }
                if (!Schema::hasColumn('purchase_requests', 'requester')) {
                    $table->string('requester', 100)->nullable()->after('req_type');
                }
                if (!Schema::hasColumn('purchase_requests', 'doc_due_date')) {
                    $table->date('doc_due_date')->nullable()->after('doc_date');
                }
                if (!Schema::hasColumn('purchase_requests', 'comments')) {
                    $table->text('comments')->nullable()->after('remarks');
                }
                if (!Schema::hasColumn('purchase_requests', 'user_id')) {
                    $table->string('user_id', 50)->nullable()->after('comments');
                }
                if (!Schema::hasColumn('purchase_requests', 'addon_id')) {
                    $table->string('addon_id', 50)->nullable()->default('2')->after('user_id');
                }
                if (!Schema::hasColumn('purchase_requests', 'sap_doc_entry')) {
                    $table->integer('sap_doc_entry')->nullable()->after('addon_id');
                }
                if (!Schema::hasColumn('purchase_requests', 'sap_doc_num')) {
                    $table->string('sap_doc_num', 50)->nullable()->after('sap_doc_entry');
                }
            });
        }

        if (Schema::hasTable('purchase_request_details')) {
            Schema::table('purchase_request_details', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_request_details', 'bom_id')) {
                    $table->unsignedBigInteger('bom_id')->nullable()->after('master_budget_id');
                }
                if (!Schema::hasColumn('purchase_request_details', 'pqt_req_date')) {
                    $table->date('pqt_req_date')->nullable()->after('item_code');
                }
                if (!Schema::hasColumn('purchase_request_details', 'uom_entry')) {
                    $table->string('uom_entry', 50)->nullable()->default('-1')->after('quantity');
                }
                if (!Schema::hasColumn('purchase_request_details', 'uom_code')) {
                    $table->string('uom_code', 50)->nullable()->default('-1')->after('uom_entry');
                }
                if (!Schema::hasColumn('purchase_request_details', 'whs_code')) {
                    $table->string('whs_code', 50)->nullable()->default('01')->after('uom_code');
                }
                if (!Schema::hasColumn('purchase_request_details', 'unit_msr')) {
                    $table->string('unit_msr', 50)->nullable()->default('Pcs')->after('whs_code');
                }
                if (!Schema::hasColumn('purchase_request_details', 'free_txt')) {
                    $table->text('free_txt')->nullable()->after('unit_msr');
                }
                if (!Schema::hasColumn('purchase_request_details', 'ocr_code')) {
                    $table->string('ocr_code', 50)->nullable()->after('free_txt');
                }
                if (!Schema::hasColumn('purchase_request_details', 'ocr_code2')) {
                    $table->string('ocr_code2', 50)->nullable()->after('ocr_code');
                }
                if (!Schema::hasColumn('purchase_request_details', 'ocr_code3')) {
                    $table->string('ocr_code3', 50)->nullable()->after('ocr_code2');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_requests')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $columns = ['series', 'req_type', 'requester', 'doc_due_date', 'comments', 'user_id', 'addon_id', 'sap_doc_entry', 'sap_doc_num'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('purchase_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('purchase_request_details')) {
            Schema::table('purchase_request_details', function (Blueprint $table) {
                $columns = ['bom_id', 'pqt_req_date', 'uom_entry', 'uom_code', 'whs_code', 'unit_msr', 'free_txt', 'ocr_code', 'ocr_code2', 'ocr_code3'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('purchase_request_details', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
