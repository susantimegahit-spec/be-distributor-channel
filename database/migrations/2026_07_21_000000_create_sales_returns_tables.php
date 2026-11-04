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
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 50)->unique();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->unsignedBigInteger('distributor_id');
            $table->string('card_code', 50);
            $table->string('customer_name', 255);
            $table->text('reason')->nullable();
            $table->decimal('doc_total', 18, 2)->default(0.00);
            $table->string('status', 50)->default('SUBMITTED');

            // SAP columns
            $table->integer('sap_doc_entry')->nullable();
            $table->string('sap_doc_num', 50)->nullable();
            $table->text('sap_error')->nullable();

            // Tracking and Approval
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('reject_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['distributor_id', 'status']);
            $table->index(['card_code']);
        });

        Schema::create('sales_return_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->onDelete('cascade');
            $table->foreignId('sales_order_detail_id')->constrained('sales_order_details')->onDelete('cascade');
            $table->string('item_code', 50);
            $table->decimal('quantity', 18, 4);
            $table->string('unit_msr', 50)->nullable();
            $table->integer('uom_entry')->nullable();
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_return_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_return_attachments');
        Schema::dropIfExists('sales_return_details');
        Schema::dropIfExists('sales_returns');
    }
};
