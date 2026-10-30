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
        if (!Schema::hasTable('purchase_requests')) {
            Schema::create('purchase_requests', function (Blueprint $table) {
                $table->id();
                $table->string('pr_number', 50)->unique();
                $table->string('department', 100);
                $table->string('cost_center', 100);
                $table->unsignedBigInteger('requester_id')->nullable();
                $table->string('requester_name', 255)->nullable();
                $table->date('doc_date');
                $table->date('required_date')->nullable();
                $table->decimal('total_amount', 20, 2)->default(0.00);
                $table->string('status', 30)->default('DRAFT'); // DRAFT, SUBMITTED, APPROVED, REJECTED, CANCELLED, COMPLETED
                $table->text('remarks')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['department', 'cost_center']);
                $table->index(['doc_date']);
                $table->index(['status']);
            });
        }

        if (!Schema::hasTable('purchase_request_details')) {
            Schema::create('purchase_request_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_request_id')->constrained('purchase_requests')->onDelete('cascade');
                $table->foreignId('master_budget_id')->nullable()->constrained('master_budgets')->onDelete('set null');
                $table->string('item_code', 50)->nullable();
                $table->string('item_description', 255);
                $table->decimal('quantity', 18, 4)->default(1.0000);
                $table->string('uom', 50)->nullable();
                $table->decimal('unit_price', 20, 2)->default(0.00);
                $table->decimal('line_total', 20, 2)->default(0.00);
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request_details');
        Schema::dropIfExists('purchase_requests');
    }
};
