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
        // 1. Add receipt_qty column to production_orders if not exists
        if (Schema::connection($this->connection)->hasTable('production_orders')) {
            Schema::connection($this->connection)->table('production_orders', function (Blueprint $table) {
                if (!Schema::connection($this->connection)->hasColumn('production_orders', 'receipt_qty')) {
                    $table->decimal('receipt_qty', 18, 4)->default(0.0000)->after('cmplt_qty')->comment('Receipt / Completed Quantity of Finished Goods');
                }
            });
        }

        // 2. Create production_issues table (Header)
        if (!Schema::connection($this->connection)->hasTable('production_issues')) {
            Schema::connection($this->connection)->create('production_issues', function (Blueprint $table) {
                $table->id();
                $table->integer('doc_entry')->unique()->nullable()->comment('DocEntry from SAP (OIGE.DocEntry)');
                $table->string('doc_num', 50)->unique()->nullable()->comment('DocNum from SAP (OIGE.DocNum)');
                $table->string('issue_no', 50)->unique()->comment('Local Issue Number (e.g. ISS-20260819-A1B2)');
                $table->foreignId('production_order_id')->nullable()->constrained('production.production_orders')->onDelete('set null');
                $table->date('doc_date')->comment('Document / Posting Date');
                $table->date('doc_due_date')->nullable()->comment('Due Date');
                $table->string('u_shift', 50)->nullable()->comment('Shift');
                $table->string('u_unit', 50)->nullable()->comment('Unit');
                $table->string('bom_id', 50)->nullable()->comment('BOM ID');
                $table->text('comments')->nullable()->comment('Remarks / Comments');
                $table->string('status', 20)->default('POSTED')->comment('POSTED, CANCELLED');
                $table->string('sap_status', 20)->default('PENDING')->comment('PENDING, SYNCED, FAILED');
                $table->text('sap_error')->nullable();
                $table->timestamp('integrated_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        // 3. Create production_issue_items table (Detail lines)
        if (!Schema::connection($this->connection)->hasTable('production_issue_items')) {
            Schema::connection($this->connection)->create('production_issue_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_issue_id')->constrained('production.production_issues')->onDelete('cascade');
                $table->foreignId('production_order_id')->nullable()->constrained('production.production_orders')->onDelete('set null');
                $table->foreignId('production_order_item_id')->nullable()->constrained('production.production_order_items')->onDelete('set null');
                $table->integer('line_num')->default(0)->comment('Line Number');
                $table->integer('base_type')->default(202)->comment('Base Type (202 = Production Order)');
                $table->string('base_entry', 50)->comment('Base DocEntry / Order ID');
                $table->string('base_line', 50)->comment('Base LineNum of component');
                $table->string('item_code', 50)->nullable()->comment('Component Item Code');
                $table->decimal('quantity', 18, 4)->comment('Quantity Issued');
                $table->string('warehouse', 20)->nullable()->comment('Warehouse Code');
                $table->string('uom_entry', 50)->nullable();
                $table->string('ocr_code', 20)->nullable();
                $table->string('ocr_code2', 20)->nullable();
                $table->string('ocr_code3', 20)->nullable();
                $table->text('comments')->nullable();
                $table->timestamps();
            });
        }

        // 4. Create production_receipts table (Header)
        if (!Schema::connection($this->connection)->hasTable('production_receipts')) {
            Schema::connection($this->connection)->create('production_receipts', function (Blueprint $table) {
                $table->id();
                $table->integer('doc_entry')->unique()->nullable()->comment('DocEntry from SAP (OIGN.DocEntry)');
                $table->string('doc_num', 50)->unique()->nullable()->comment('DocNum from SAP (OIGN.DocNum)');
                $table->string('receipt_no', 50)->unique()->comment('Local Receipt Number (e.g. RCP-20260819-A1B2)');
                $table->foreignId('production_order_id')->nullable()->constrained('production.production_orders')->onDelete('set null');
                $table->date('doc_date')->comment('Document / Posting Date');
                $table->date('doc_due_date')->nullable()->comment('Due Date');
                $table->string('u_shift', 50)->nullable()->comment('Shift');
                $table->string('u_unit', 50)->nullable()->comment('Unit');
                $table->string('bom_id', 50)->nullable()->comment('BOM ID');
                $table->text('comments')->nullable()->comment('Remarks / Comments');
                $table->string('status', 20)->default('POSTED')->comment('POSTED, CANCELLED');
                $table->string('sap_status', 20)->default('PENDING')->comment('PENDING, SYNCED, FAILED');
                $table->text('sap_error')->nullable();
                $table->timestamp('integrated_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        // 5. Create production_receipt_items table (Detail lines)
        if (!Schema::connection($this->connection)->hasTable('production_receipt_items')) {
            Schema::connection($this->connection)->create('production_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_receipt_id')->constrained('production.production_receipts')->onDelete('cascade');
                $table->foreignId('production_order_id')->nullable()->constrained('production.production_orders')->onDelete('set null');
                $table->integer('line_num')->default(0)->comment('Line Number');
                $table->integer('base_type')->default(202)->comment('Base Type (202 = Production Order)');
                $table->string('base_entry', 50)->comment('Base DocEntry / Order ID');
                $table->string('base_line', 50)->nullable()->comment('Base LineNum');
                $table->string('item_code', 50)->nullable()->comment('Finished Good Item Code');
                $table->decimal('quantity', 18, 4)->comment('Quantity Received');
                $table->string('warehouse', 20)->nullable()->comment('Warehouse Code');
                $table->string('uom_entry', 50)->nullable();
                $table->string('ocr_code', 20)->nullable();
                $table->string('ocr_code2', 20)->nullable();
                $table->string('ocr_code3', 20)->nullable();
                $table->text('comments')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('production_receipt_items');
        Schema::connection($this->connection)->dropIfExists('production_receipts');
        Schema::connection($this->connection)->dropIfExists('production_issue_items');
        Schema::connection($this->connection)->dropIfExists('production_issues');

        if (Schema::connection($this->connection)->hasTable('production_orders')) {
            Schema::connection($this->connection)->table('production_orders', function (Blueprint $table) {
                if (Schema::connection($this->connection)->hasColumn('production_orders', 'receipt_qty')) {
                    $table->dropColumn('receipt_qty');
                }
            });
        }
    }
};
