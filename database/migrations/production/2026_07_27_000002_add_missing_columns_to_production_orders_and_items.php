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
        Schema::connection($this->connection)->table('production_orders', function (Blueprint $table) {
            $table->decimal('act_item_cost', 18, 4)->default(0.0000)->comment('Actual Item Component Cost');
            $table->decimal('act_res_cost', 18, 4)->default(0.0000)->comment('Actual Resource Component Cost');
            $table->decimal('act_add_cost', 18, 4)->default(0.0000)->comment('Actual Additional Cost');
            $table->decimal('act_prod_cost', 18, 4)->default(0.0000)->comment('Actual Product Cost');
            $table->decimal('act_by_prod_cost', 18, 4)->default(0.0000)->comment('Actual By-Product Cost');
            $table->decimal('total_variance', 18, 4)->default(0.0000)->comment('Total Variance');
            $table->string('jrnl_memo', 255)->nullable()->comment('Journal Remarks / OWOR.JrnlMemo');
            $table->string('ref_doc', 100)->nullable()->comment('Referenced Document');
            $table->date('act_close_date')->nullable()->comment('Actual Closing Date');
            $table->integer('overdue')->nullable()->comment('Overdue days');
        });

        Schema::connection($this->connection)->table('production_order_items', function (Blueprint $table) {
            $table->decimal('available_qty', 18, 4)->default(0.0000)->comment('Current physical stock available');
            $table->integer('base_entry')->nullable()->comment('Base document DocEntry');
            $table->integer('base_type')->nullable()->comment('Base document ObjType');
            $table->integer('base_line')->nullable()->comment('Base document LineNum');
        });
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
