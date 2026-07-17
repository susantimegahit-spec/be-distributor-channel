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
        Schema::connection($this->connection)->create('production_bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_bom_id')->constrained('production.production_boms')->onDelete('cascade');
            $table->string('father', 50)->comment('Parent Product Code (ITT1.Father)');
            $table->integer('child_num')->comment('Line Number (ITT1.ChildNum)');
            $table->string('type', 20)->default('Item')->comment('Component Type: Item, Resource (ITT1.Type)');
            $table->string('code', 50)->comment('Component Item or Resource Code (ITT1.Code)');
            $table->decimal('quantity', 18, 4)->comment('Component quantity (ITT1.Quantity)');
            $table->string('warehouse', 20)->nullable()->comment('Component warehouse (ITT1.Warehouse)');
            $table->string('issue_mthd', 20)->default('B')->comment('Issue Method: B = Backflush, M = Manual (ITT1.IssueMthd)');
            $table->string('ocr_code', 20)->nullable()->comment('Distribution Rule 1 / Cabang (ITT1.OcrCode)');
            $table->string('ocr_code2', 20)->nullable()->comment('Distribution Rule 2 / Bisnis Unit (ITT1.OcrCode2)');
            $table->string('ocr_code3', 20)->nullable()->comment('Distribution Rule 3 / Department (ITT1.OcrCode3)');
            $table->text('comments')->nullable()->comment('Line Remarks (ITT1.Comment)');
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
        Schema::connection($this->connection)->dropIfExists('production_bom_items');
    }
};
