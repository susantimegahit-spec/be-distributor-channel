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
        Schema::create('sales_dashboard_data', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 50);
            $table->string('customer_name', 255);
            $table->string('depo', 100)->nullable();
            $table->string('item_code', 50);
            $table->string('item_name', 255);
            $table->unsignedSmallInteger('month'); // 1-12
            $table->unsignedSmallInteger('year');  // e.g. 2026
            $table->decimal('target_amount', 20, 2)->default(0.00);
            $table->decimal('cmo_amount', 20, 2)->default(0.00);
            $table->decimal('so_amount', 20, 2)->default(0.00);
            $table->decimal('do_amount', 20, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['customer_code', 'item_code', 'month', 'year'], 'uq_sales_dashboard_data');
            $table->index(['customer_code', 'month', 'year']);
            $table->index(['item_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_dashboard_data');
    }
};
