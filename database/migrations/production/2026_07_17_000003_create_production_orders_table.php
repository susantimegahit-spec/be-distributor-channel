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
        Schema::connection($this->connection)->create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('doc_entry')->unique()->nullable()->comment('Internal DocEntry from SAP (OWOR.DocEntry)');
            $table->string('doc_num', 50)->unique()->nullable()->comment('Document Number from SAP (OWOR.DocNum)');
            $table->string('series', 20)->nullable()->comment('Series Name (OWOR.Series)');
            $table->string('prod_order_no', 50)->unique()->comment('Local Production Order Number');
            $table->string('status', 20)->default('PLANNED')->comment('Status: PLANNED, RELEASED, CLOSED, CANCELLED (OWOR.Status)');
            $table->string('type', 20)->default('Standard')->comment('Type: Standard, Special, Disassembly (OWOR.Type)');
            $table->string('item_code', 50)->comment('Parent Item / Product No (OWOR.ItemCode)');
            $table->decimal('planned_qty', 18, 4)->comment('Planned Quantity to produce (OWOR.PlannedQty)');
            $table->decimal('cmplt_qty', 18, 4)->default(0.0000)->comment('Completed Quantity (OWOR.CmpltQty)');
            $table->decimal('rjct_qty', 18, 4)->default(0.0000)->comment('Rejected Quantity (OWOR.RjctQty)');
            $table->string('warehouse', 20)->comment('Receipt Warehouse (OWOR.Warehouse)');
            $table->integer('priority')->default(100)->comment('Priority (OWOR.Priority)');
            $table->string('project', 50)->nullable()->comment('Project Code (OWOR.Project)');
            $table->date('post_date')->comment('Order Date / Posting Date (OWOR.PostDate)');
            $table->date('start_date')->nullable()->comment('Start Date (OWOR.StartDate)');
            $table->date('due_date')->nullable()->comment('Due Date (OWOR.DueDate)');
            $table->string('origin_type', 20)->nullable()->comment('Link To Object (e.g. Sales Order) (OWOR.OriginType)');
            $table->string('origin_num', 50)->nullable()->comment('Linked Order Number (OWOR.OriginNum)');
            $table->string('card_code', 50)->nullable()->comment('Customer Card Code (OWOR.CardCode)');
            $table->string('ocr_code', 20)->nullable()->comment('Distribution Rule 1 / Cabang (OWOR.OcrCode)');
            $table->string('ocr_code2', 20)->nullable()->comment('Distribution Rule 2 / Bisnis Unit (OWOR.OcrCode2)');
            $table->string('ocr_code3', 20)->nullable()->comment('Distribution Rule 3 / Department (OWOR.OcrCode3)');
            $table->string('u_shift', 50)->nullable()->comment('User Defined Field: Shift');
            $table->string('u_unit', 50)->nullable()->comment('User Defined Field: Unit');
            $table->text('comments')->nullable()->comment('Remarks (OWOR.Comments)');
            
            $table->text('issue_for_production')->nullable()->comment('Comma-separated list of linked Issue DocNums');
            $table->text('receipt_from_production')->nullable()->comment('Comma-separated list of linked Receipt DocNums');
            
            $table->foreignId('production_bom_id')->nullable()->constrained('production.production_boms')->onDelete('set null');
            
            $table->string('sap_status', 20)->default('PENDING');
            $table->text('sap_error')->nullable();
            $table->timestamp('integrated_at')->nullable();
            
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Foreign keys referencing public schema
            $table->foreign('item_code')->references('item_code')->on('public.items')->onDelete('cascade');
            $table->foreign('warehouse')->references('whs_code')->on('public.warehouses')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('public.users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('public.users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('production_orders');
    }
};
