<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds a dedicated batch_id column to trx_claim_balance_ledger so that:
     * - ref_number stays as the human-readable display reference (e.g. B-001, ADJ-...)
     * - batch_id stores the integer FK to trx_program_upload_batch for JOIN/link purposes
     */
    public function up(): void
    {
        Schema::table('trx_claim_balance_ledger', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->after('ref_number');
            $table->foreign('batch_id')->references('id')->on('trx_program_upload_batch')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_claim_balance_ledger', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
