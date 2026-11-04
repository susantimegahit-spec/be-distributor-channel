<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        if (Schema::connection($this->connection)->hasTable('production_orders')) {
            Schema::connection($this->connection)->table('production_orders', function (Blueprint $table) {
                if (!Schema::connection($this->connection)->hasColumn('production_orders', 'act_item_cost')) {
                    $table->decimal('act_item_cost', 18, 4)->default(0.0000)->comment('Actual Item Component Cost');
                }
                if (!Schema::connection($this->connection)->hasColumn('production_orders', 'act_res_cost')) {
                    $table->decimal('act_res_cost', 18, 4)->default(0.0000)->comment('Actual Resource Component Cost');
                }
                if (!Schema::connection($this->connection)->hasColumn('production_orders', 'act_add_cost')) {
                    $table->decimal('act_add_cost', 18, 4)->default(0.0000)->comment('Actual Additional Cost');
                }
                if (!Schema::connection($this->connection)->hasColumn('production_orders', 'act_prod_cost')) {
                    $table->decimal('act_prod_cost', 18, 4)->default(0.0000)->comment('Actual Product Cost');
                }
                if (!Schema::connection($this->connection)->hasColumn('production_orders', 'act_by_prod_cost')) {
                    $table->decimal('act_by_prod_cost', 18, 4)->default(0.0000)->comment('Actual By-Product Cost');
                }
                if (!Schema::connection($this->connection)->hasColumn('production_orders', 'total_variance')) {
                    $table->decimal('total_variance', 18, 4)->default(0.0000)->comment('Total Variance');
                }
                if (!Schema::connection($this->connection)->hasColumn('production_orders', 'jrnl_memo')) {
                    $table->string('jrnl_memo', 255)->nullable()->comment('Journal Remarks / OWOR.JrnlMemo');
                }
                if (!Schema::connection($this->connection)->hasColumn('production_orders', 'ref_doc')) {
                    $table->string('ref_doc', 100)->nullable()->comment('Referenced Document');
                }
                if (!Schema::connection($this->connection)->hasColumn('production_orders', 'act_close_date')) {
                    $table->date('act_close_date')->nullable()->comment('Actual Closing Date');
                }
                if (!Schema::connection($this->connection)->hasColumn('production_orders', 'overdue')) {
                    $table->integer('overdue')->nullable()->comment('Overdue days');
                }
            });
        }

        if (Schema::connection($this->connection)->hasTable('production_order_items')) {
            Schema::connection($this->connection)->table('production_order_items', function (Blueprint $table) {
                if (!Schema::connection($this->connection)->hasColumn('production_order_items', 'available_qty')) {
                    $table->decimal('available_qty', 18, 4)->default(0.0000)->comment('Current physical stock available');
                }
                if (!Schema::connection($this->connection)->hasColumn('production_order_items', 'base_entry')) {
                    $table->integer('base_entry')->nullable()->comment('Base document DocEntry');
                }
                if (!Schema::connection($this->connection)->hasColumn('production_order_items', 'base_type')) {
                    $table->integer('base_type')->nullable()->comment('Base document ObjType');
                }
                if (!Schema::connection($this->connection)->hasColumn('production_order_items', 'base_line')) {
                    $table->integer('base_line')->nullable()->comment('Base document LineNum');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('production_order_items', function (Blueprint $table) {
            $table->dropColumn(['available_qty', 'base_entry', 'base_type', 'base_line']);
        });

        Schema::connection($this->connection)->table('production_orders', function (Blueprint $table) {
            $table->dropColumn([
                'act_item_cost',
                'act_res_cost',
                'act_add_cost',
                'act_prod_cost',
                'act_by_prod_cost',
                'total_variance',
                'jrnl_memo',
                'ref_doc',
                'act_close_date',
                'overdue'
            ]);
        });
    }
};
