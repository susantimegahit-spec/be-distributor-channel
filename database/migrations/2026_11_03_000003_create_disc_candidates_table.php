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
        Schema::create('disc_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 255);
            $table->string('email', 255)->index();
            $table->string('phone', 50)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('position_applied', 150)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disc_candidates');
    }
};

