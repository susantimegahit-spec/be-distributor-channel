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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('sap_status', 50)->nullable()->after('sap_error');
            $table->string('sap_last_doc_type', 20)->nullable()->after('sap_status');
            $table->string('sap_last_doc_num', 50)->nullable()->after('sap_last_doc_type');
            $table->timestamp('sap_last_synced_at')->nullable()->after('sap_last_doc_num');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn([
                'sap_status',
                'sap_last_doc_type',
                'sap_last_doc_num',
                'sap_last_synced_at'
            ]);
        });
    }
};
