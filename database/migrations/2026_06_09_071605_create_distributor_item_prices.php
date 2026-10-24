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
        Schema::create('distributor_item_prices', function (Blueprint $table) {
            $table->id();
            $table->string('code_customer')->index();
            $table->string('item_code')->index();
            $table->decimal('price', 18, 2)->default(0.00);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->unsignedSmallInteger('status')->default(1);
            $table->timestamps();

            // Foreign keys
            $table->foreign('code_customer')->references('code_customer')->on('distributors')->onDelete('cascade');
            $table->foreign('item_code')->references('item_code')->on('items')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributor_item_prices');
    }
};
