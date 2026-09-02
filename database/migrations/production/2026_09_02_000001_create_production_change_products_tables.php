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
        // 1. Create production_change_products table (Header)
        if (!Schema::connection($this->connection)->hasTable('production_change_products')) {
            Schema::connection($this->connection)->create('production_change_products', function (Blueprint $table) {
                $table->id();
                $table->string('cp_no', 50)->unique()->comment('Local Change Product Number (e.g. CP-20260902-0001)');
                $table->dateTime('doc_date')->comment('Document / Posting Date');
                $table->dateTime('doc_due_date')->nullable()->comment('Due Date');
                $table->text('comments')->nullable()->comment('Remarks / Comments');
                $table->string('shift', 50)->nullable()->comment('Shift');
                $table->string('unit', 50)->nullable()->comment('Unit / Machine Line');
                $table->string('addon_id', 100)->nullable()->comment('Addon / Transaction Reference ID');
                $table->string('user_id', 50)->nullable()->comment('SAP User ID / Operator ID');
                $table->integer('gi_entry')->nullable()->comment('Goods Issue DocEntry from SAP (Issue Entry)');
                $table->integer('gr_entry')->nullable()->comment('Goods Receipt DocEntry from SAP (Receipt Entry)');
                $table->string('status', 20)->default('DRAFT')->comment('DRAFT, COMPLETE, CANCELLED');
                $table->string('sap_status', 20)->default('PENDING')->comment('PENDING, SYNCED, FAILED');
                $table->text('sap_message')->nullable()->comment('Response message from SAP');
                $table->text('sap_error')->nullable()->comment('Error message if SAP sync failed');
                $table->timestamp('integrated_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        // 2. Create production_change_product_items table (Detail lines)
        if (!Schema::connection($this->connection)->hasTable('production_change_product_items')) {
            Schema::connection($this->connection)->create('production_change_product_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_change_product_id')
                    ->constrained('production.production_change_products')
                    ->onDelete('cascade');
                $table->integer('line_num')->default(0)->comment('Line Number / Index');
                $table->string('old_item_code', 50)->comment('Old Item Code to Issue');
                $table->string('new_item_code', 50)->comment('New Item Code to Receipt');
                $table->decimal('quantity', 18, 4)->comment('Quantity to Change');
                $table->string('from_whs_code', 50)->comment('Source Warehouse (GI)');
                $table->string('to_whs_code', 50)->comment('Destination Warehouse (GR)');
                $table->string('ocr_code', 50)->nullable()->comment('Cost Center / Distribution Rule 1');
                $table->string('ocr_code2', 50)->nullable()->comment('Cost Center / Distribution Rule 2');
                $table->string('ocr_code3', 50)->nullable()->comment('Cost Center / Distribution Rule 3');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('production_change_product_items');
        Schema::connection($this->connection)->dropIfExists('production_change_products');
    }
};
