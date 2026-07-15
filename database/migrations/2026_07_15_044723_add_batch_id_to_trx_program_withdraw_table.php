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
        Schema::table('trx_program_withdraw', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->after('customer_code');
            $table->foreign('batch_id')->references('id')->on('trx_program_upload_batch')->nullOnDelete();
            $table->json('lines')->nullable()->after('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_program_withdraw', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn(['batch_id', 'lines']);
        });
    }
};
