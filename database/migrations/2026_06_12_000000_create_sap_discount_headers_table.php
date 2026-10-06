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
        Schema::create('sap_discount_headers', function (Blueprint $table) {
            $table->id();
            $table->string('discount_code')->unique()->index();
            $table->string('card_code');
            $table->string('card_name');
            $table->decimal('total_so', 15, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sap_discount_headers');
    }
};
