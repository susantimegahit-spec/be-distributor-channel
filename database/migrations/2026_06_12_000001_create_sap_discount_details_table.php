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
        Schema::create('sap_discount_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sap_discount_header_id')->constrained('sap_discount_headers')->onDelete('cascade');
            $table->string('type_discount');
            $table->decimal('percentage', 5, 2)->default(0);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sap_discount_details');
    }
};
