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
        Schema::connection($this->connection)->create('production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production.production_orders')->onDelete('cascade');
            $table->integer('line_num')->comment('Line number (WOR1.LineNum)');
            $table->string('item_code', 50)->comment('Component Item/Resource Code (WOR1.ItemCode)');
            $table->string('type', 20)->default('Item')->comment('Component Type: Item, Resource (WOR1.Type)');
            $table->decimal('base_qty', 18, 4)->comment('Standard quantity per 1 parent unit (WOR1.BaseQty)');
            $table->decimal('planned_qty', 18, 4)->comment('Planned component quantity (WOR1.PlannedQty)');
            $table->decimal('issued_qty', 18, 4)->default(0.0000)->comment('Actual quantity consumed (WOR1.IssuedQty)');
            $table->string('warehouse', 20)->nullable()->comment('Component Warehouse (WOR1.Warehouse)');
            $table->string('issue_mthd', 20)->default('B')->comment('Issue Method: B = Backflush, M = Manual (WOR1.IssueMthd)');
            $table->string('ocr_code', 20)->nullable()->comment('Distribution Rule 1 / Cabang (WOR1.OcrCode)');
            $table->string('ocr_code2', 20)->nullable()->comment('Distribution Rule 2 / Bisnis Unit (WOR1.OcrCode2)');
            $table->string('ocr_code3', 20)->nullable()->comment('Distribution Rule 3 / Department (WOR1.OcrCode3)');
            $table->text('comments')->nullable()->comment('Line Comments');
            $table->timestamps();

            // Foreign keys referencing public schema
            $table->foreign('warehouse')->references('whs_code')->on('public.warehouses')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('production_order_items');
    }
};
