<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'pgsql_ekspedisi';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conn = $this->connection;

        if (Schema::connection($conn)->hasTable('picklists')) {
            Schema::connection($conn)->table('picklists', function (Blueprint $table) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('picklists', 'do_doc_entry')) {
                    $table->string('do_doc_entry', 50)->nullable()->after('delivery_order_no')->comment('SAP Delivery Order DocEntry');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'do_doc_num')) {
                    $table->string('do_doc_num', 50)->nullable()->after('do_doc_entry')->comment('SAP Delivery Order DocNum');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'do_status')) {
                    $table->string('do_status', 30)->nullable()->after('do_doc_num')->comment('Status of SAP Delivery Order (SUCCESS, FAILED, PENDING)');
                }
            });
        }

        if (Schema::connection($conn)->hasTable('picklist_items')) {
            Schema::connection($conn)->table('picklist_items', function (Blueprint $table) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('picklist_items', 'delivery_order_no')) {
                    $table->string('delivery_order_no', 50)->nullable()->after('bin_allocations')->comment('Delivery order number (DocNum) from SAP');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->connection;

        if (Schema::connection($conn)->hasTable('picklists')) {
            Schema::connection($conn)->table('picklists', function (Blueprint $table) use ($conn) {
                $cols = ['do_doc_entry', 'do_doc_num', 'do_status'];
                foreach ($cols as $col) {
                    if (Schema::connection($conn)->hasColumn('picklists', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::connection($conn)->hasTable('picklist_items')) {
            Schema::connection($conn)->table('picklist_items', function (Blueprint $table) use ($conn) {
                if (Schema::connection($conn)->hasColumn('picklist_items', 'delivery_order_no')) {
                    $table->dropColumn('delivery_order_no');
                }
            });
        }
    }
};
