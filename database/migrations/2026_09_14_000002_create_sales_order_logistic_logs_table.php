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
        if (!Schema::hasTable('sales_order_logistic_logs')) {
            Schema::create('sales_order_logistic_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
                $table->string('action', 50)->index()->comment('LOGISTIC_APPROVED, LOGISTIC_RESCHEDULE, ADMIN_SALES_APPROVED_RESCHEDULE, ADMIN_SALES_REJECTED_RESCHEDULE');
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50);
                $table->date('previous_due_date')->nullable();
                $table->date('previous_eta_date')->nullable();
                $table->date('proposed_due_date')->nullable();
                $table->date('proposed_eta_date')->nullable();
                $table->date('approved_due_date')->nullable();
                $table->date('approved_eta_date')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name', 255)->nullable();
                $table->string('role_name', 100)->nullable();
                $table->timestamps();

                $table->index(['sales_order_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_logistic_logs');
    }
};
