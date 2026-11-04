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
            $table->string('do_num', 50)->nullable()->comment('Delivery Order Number from SAP');
            $table->integer('baseline')->nullable()->comment('BaseLine index inside DO from SAP');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_return_details', function (Blueprint $table) {
            $table->dropColumn(['do_num', 'baseline']);
        });
    }
};
