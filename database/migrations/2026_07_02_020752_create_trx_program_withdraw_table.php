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
        Schema::create('trx_program_withdraw', function (Blueprint $table) {
            $table->id();
            $table->string('withdraw_no', 50)->unique();
            $table->string('customer_code', 50);
            $table->decimal('amount', 18, 2);
            $table->string('status', 30)->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->string('created_by', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_code', 'idx_withdraw_customer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_program_withdraw');
    }
};
