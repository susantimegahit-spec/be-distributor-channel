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
        // 1. Drop old table if exists from previous iteration
        if (Schema::connection($this->connection)->hasTable('production_change_product_items')) {
            Schema::connection($this->connection)->dropIfExists('production_change_product_items');
        }

        // 2. Create production_change_product_old_lines table (Old Item lines to Issue)
        if (!Schema::connection($this->connection)->hasTable('production_change_product_old_lines')) {
            Schema::connection($this->connection)->create('production_change_product_old_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_change_product_id')
                    ->constrained('production.production_change_products')
                    ->onDelete('cascade');
                $table->integer('line_num')->default(0)->comment('Line Number / Index');
                $table->string('item_code', 50)->comment('Old Item Code to Issue');
                $table->decimal('quantity', 18, 4)->comment('Quantity to Issue');
                $table->string('from_whs_code', 50)->comment('Source Warehouse (GI)');
                $table->string('ocr_code', 50)->nullable()->comment('Cost Center / Distribution Rule 1');
                $table->string('ocr_code2', 50)->nullable()->comment('Cost Center / Distribution Rule 2');
                $table->string('ocr_code3', 50)->nullable()->comment('Cost Center / Distribution Rule 3');
                $table->timestamps();
            });
        }

        // 3. Create production_change_product_new_lines table (New Item lines to Receipt)
        if (!Schema::connection($this->connection)->hasTable('production_change_product_new_lines')) {
            Schema::connection($this->connection)->create('production_change_product_new_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_change_product_id')
                    ->constrained('production.production_change_products')
                    ->onDelete('cascade');
                $table->integer('line_num')->default(0)->comment('Line Number / Index');
                $table->string('item_code', 50)->comment('New Item Code to Receipt');
                $table->decimal('quantity', 18, 4)->comment('Quantity to Receipt');
                $table->string('to_whs_code', 50)->comment('Destination Warehouse (GR)');
                $table->string('ocr_code', 50)->nullable()->comment('Cost Center / Distribution Rule 1');
                $table->string('ocr_code2', 50)->nullable()->comment('Cost Center / Distribution Rule 2');
                $table->string('ocr_code3', 50)->nullable()->comment('Cost Center / Distribution Rule 3');
                $table->decimal('value_allocation_percent', 18, 4)->default(0)->comment('Value Allocation Percent (e.g. 0)');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('production_change_product_new_lines');
        Schema::connection($this->connection)->dropIfExists('production_change_product_old_lines');
    }
};
