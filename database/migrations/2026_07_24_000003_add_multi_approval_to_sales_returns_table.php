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
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_admin_by')->nullable()->after('approved_at');
            $table->timestamp('approved_admin_at')->nullable()->after('approved_admin_by');
            $table->unsignedBigInteger('approved_finance_by')->nullable()->after('approved_admin_at');
            $table->timestamp('approved_finance_at')->nullable()->after('approved_finance_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropColumn([
                'approved_admin_by',
                'approved_admin_at',
                'approved_finance_by',
                'approved_finance_at'
            ]);
        });
    }
};
