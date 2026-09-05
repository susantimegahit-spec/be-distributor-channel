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
            $table->decimal('do_quantity', 18, 4)->nullable()->default(0.0000)->comment('Original quantity from Delivery Order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_return_details', function (Blueprint $table) {
            $table->dropColumn('do_quantity');
        });
    }
};
