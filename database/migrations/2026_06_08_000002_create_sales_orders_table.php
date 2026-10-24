<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 50)->unique();
            $table->unsignedBigInteger('distributor_id');
            $table->string('card_code', 50);
            $table->string('customer_name', 255);
            $table->string('po_number', 100)->nullable();
            $table->date('doc_date');
            $table->date('doc_due_date')->nullable();
            $table->integer('slp_code')->nullable();
            $table->integer('cntct_code')->default(-1);
            $table->string('pay_to_code', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('ship_to_code', 255)->nullable();
            $table->text('address2')->nullable();
            $table->decimal('disc_percent', 18, 2)->default(0.00);
            $table->decimal('doc_total', 18, 2)->default(0.00);
            $table->text('comments')->nullable();
            $table->string('id_discount', 100)->nullable();
            
            $table->string('status', 50)->default('DRAFT');

            $table->integer('sap_doc_entry')->nullable();
            $table->string('sap_doc_num', 50)->nullable();
            $table->text('sap_error')->nullable();
            $table->string('sap_discount_code', 100)->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('integrated_at')->nullable();
            $table->timestamp('delivery_date')->nullable();
            $table->timestamp('arrived_date')->nullable();

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('reject_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->string('item_code', 50);
            $table->decimal('quantity', 18, 4);
            $table->string('unit_msr', 50)->nullable();
            $table->integer('uom_entry')->nullable();
            $table->string('whs_code', 20)->nullable();
            $table->decimal('unit_price', 18, 2);
            $table->decimal('disc_percent', 18, 2)->default(0.00);
            $table->string('vat_group', 10)->nullable();
            $table->decimal('line_total', 18, 2);
            $table->text('free_text')->nullable();
            $table->string('ocr_code', 20)->nullable();
            $table->string('ocr_code2', 20)->nullable();
            $table->string('ocr_code3', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_details');
        Schema::dropIfExists('sales_orders');
    }
};
