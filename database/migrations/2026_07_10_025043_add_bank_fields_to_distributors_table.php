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
        Schema::table('distributors', function (Blueprint $table) {
            $table->string('bank_code')->nullable()->after('depo');
            $table->string('bank_name')->nullable()->after('bank_code');
            $table->string('client_bank_name')->nullable()->after('bank_name');
            $table->string('account_bank_number')->nullable()->after('client_bank_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->dropColumn(['bank_code', 'bank_name', 'client_bank_name', 'account_bank_number']);
        });
    }
};
