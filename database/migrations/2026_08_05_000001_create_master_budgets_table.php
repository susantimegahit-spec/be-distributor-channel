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
        if (!Schema::hasTable('master_budgets')) {
            Schema::create('master_budgets', function (Blueprint $table) {
                $table->id();
                $table->string('budget_code', 50)->unique();
                $table->string('department', 100);
                $table->string('cost_center', 100);
                $table->string('budget_category', 100)->nullable();
                $table->decimal('budget_amount', 20, 2)->default(0.00);
                $table->decimal('used_amount', 20, 2)->default(0.00);
                $table->unsignedSmallInteger('period_month')->nullable(); // 1-12
                $table->unsignedSmallInteger('period_year');              // e.g. 2026
                $table->string('status', 20)->default('ACTIVE');          // ACTIVE, INACTIVE, CLOSED
                $table->text('description')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['department', 'cost_center']);
                $table->index(['period_year', 'period_month']);
                $table->index(['status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_budgets');
    }
};
