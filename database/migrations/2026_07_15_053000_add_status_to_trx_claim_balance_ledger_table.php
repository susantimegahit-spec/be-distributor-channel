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
        Schema::table('trx_claim_balance_ledger', function (Blueprint $table) {
            $table->string('status', 30)->default('APPROVED')->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_claim_balance_ledger', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
