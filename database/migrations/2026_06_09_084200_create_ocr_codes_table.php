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
        Schema::create('ocr_codes', function (Blueprint $table) {
            $table->id();
            $table->string('ocr_code');
            $table->string('ocr_name');
            $table->string('distribution_target')->index(); // CABANG, UNIT, DEPARTEMENT
            $table->unsignedSmallInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['ocr_code', 'distribution_target']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ocr_codes');
    }
};
