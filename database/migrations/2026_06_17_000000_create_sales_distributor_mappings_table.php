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
        Schema::create('sales_distributor_mappings', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('code_customer');
            $blueprint->integer('slp_code');
            $blueprint->integer('status')->default(1); // 1 = active, 0 = inactive
            $blueprint->timestamps();

            // Foreign keys or indexes
            $blueprint->index('code_customer');
            $blueprint->index('slp_code');
            $blueprint->unique(['code_customer', 'slp_code'], 'idx_cust_slp_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_distributor_mappings');
    }
};
