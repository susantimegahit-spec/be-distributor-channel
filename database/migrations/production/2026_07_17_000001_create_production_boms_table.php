<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
        Schema::connection($this->connection)->create('production_boms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Parent Item Code / Product No (OITT.Code)');
            $table->decimal('qty', 18, 4)->default(1.0000)->comment('Base Quantity (OITT.Qty)');
            $table->string('to_whs', 20)->nullable()->comment('Receipt Warehouse (OITT.ToWhs)');
            $table->string('type', 20)->default('P')->comment('BOM Type: P = Production, S = Template, etc (OITT.Type)');
            $table->integer('alternate')->default(1)->comment('Alternate BOM number / version');
            $table->string('ocr_code', 20)->nullable()->comment('Distribution Rule 1 / Cabang (OITT.OcrCode)');
            $table->string('ocr_code2', 20)->nullable()->comment('Distribution Rule 2 / Bisnis Unit (OITT.OcrCode2)');
            $table->string('ocr_code3', 20)->nullable()->comment('Distribution Rule 3 / Department (OITT.OcrCode3)');
            $table->string('u_shift', 50)->nullable()->comment('User Defined Field: Shift');
            $table->string('u_unit', 50)->nullable()->comment('User Defined Field: Unit');
            $table->text('comments')->nullable()->comment('Remarks (OITT.Comment)');
            $table->boolean('is_active')->default(true);
            
            $table->integer('sap_doc_entry')->nullable();
            $table->string('sap_doc_num', 50)->nullable();
            
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Foreign Key constraints referencing tables in the public schema
            $table->foreign('code')->references('item_code')->on('public.items')->onDelete('cascade');
            $table->foreign('to_whs')->references('whs_code')->on('public.warehouses')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('public.users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('public.users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('production_boms');
    }
};
