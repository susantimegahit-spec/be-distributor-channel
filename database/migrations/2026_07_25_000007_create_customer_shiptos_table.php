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
        if (!Schema::hasTable('customer_shiptos')) {
            Schema::create('customer_shiptos', function (Blueprint $table) {
                $table->id();
                $table->string('card_code', 50)->index();
                $table->string('name', 255)->nullable();
                $table->string('address', 255)->nullable(); // ShipTo Code / Address ID in SAP
                $table->string('city', 255)->nullable();
                $table->text('street')->nullable();
                $table->timestamps();

                $table->unique(['card_code', 'address'], 'customer_shiptos_card_code_address_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_shiptos');
    }
};
