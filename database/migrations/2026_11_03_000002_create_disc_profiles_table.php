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
        Schema::create('disc_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('profile_number')->unique();
            $table->string('title', 100);
            $table->string('dimension_code', 50)->nullable();
            $table->jsonb('characteristics')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disc_profiles');
    }
};

