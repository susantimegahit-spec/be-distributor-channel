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
        Schema::create('trx_claim_balance_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 50);
            $table->string('ref_number', 100)->nullable();
            $table->date('transaction_date');
            $table->string('type', 30); // CLAIM, TRANSACTION, WITHDRAW, RETURN, CORRECTION
            $table->decimal('debit', 18, 2)->default(0.00);
            $table->decimal('credit', 18, 2)->default(0.00);
            $table->decimal('running_balance', 18, 2)->default(0.00);
            $table->string('claim_type', 30)->nullable(); // BULANAN, 3_BULANAN, 6_BULANAN
            $table->date('claim_start')->nullable();
            $table->date('claim_end')->nullable();
            $table->string('description', 255)->nullable();
            $table->nullableMorphs('referenceable'); // referenceable_id & referenceable_type
            $table->string('created_by', 50)->nullable();
            $table->timestamps();

            $table->index('customer_code', 'idx_ledger_customer');
            $table->index('transaction_date', 'idx_ledger_date');
            $table->index('type', 'idx_ledger_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_claim_balance_ledger');
    }
};
