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
        Schema::table('sales_return_details', function (Blueprint $table) {
            $table->date('do_date')->nullable()->after('do_num')->comment('Delivery Order Date from SAP');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_return_details', function (Blueprint $table) {
            $table->dropColumn('do_date');
        });
    }
};
