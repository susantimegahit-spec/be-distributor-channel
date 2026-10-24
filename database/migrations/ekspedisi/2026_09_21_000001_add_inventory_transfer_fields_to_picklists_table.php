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
                if (!Schema::connection($conn)->hasColumn('picklists', 'it_doc_entry')) {
                    $table->string('it_doc_entry', 50)->nullable()->after('comments')->comment('SAP Inventory Transfer DocEntry');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'it_doc_num')) {
                    $table->string('it_doc_num', 50)->nullable()->after('it_doc_entry')->comment('SAP Inventory Transfer DocNum');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'it_status')) {
                    $table->string('it_status', 30)->nullable()->after('it_doc_num')->comment('Status of SAP Inventory Transfer (SUCCESS, FAILED, PENDING)');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'to_whs_code')) {
                    $table->string('to_whs_code', 50)->default('VPGMN01')->after('it_status')->comment('Target destination warehouse (default VPGMN01)');
                }
            });
        }

        if (Schema::connection($conn)->hasTable('picklist_items')) {
            Schema::connection($conn)->table('picklist_items', function (Blueprint $table) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('picklist_items', 'bin_allocations')) {
                    $table->json('bin_allocations')->nullable()->after('total_weight')->comment('Allocated Bin Locations from source warehouse');
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
                $cols = ['it_doc_entry', 'it_doc_num', 'it_status', 'to_whs_code'];
                foreach ($cols as $col) {
                    if (Schema::connection($conn)->hasColumn('picklists', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::connection($conn)->hasTable('picklist_items')) {
            Schema::connection($conn)->table('picklist_items', function (Blueprint $table) use ($conn) {
                if (Schema::connection($conn)->hasColumn('picklist_items', 'bin_allocations')) {
                    $table->dropColumn('bin_allocations');
                }
            });
        }
    }
};
