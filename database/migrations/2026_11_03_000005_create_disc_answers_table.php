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
        Schema::create('disc_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('disc_assessments')->cascadeOnDelete();
            $table->unsignedSmallInteger('question_number');
            $table->unsignedTinyInteger('p_choice')->nullable(); // 1, 2, 3, or 4
            $table->unsignedTinyInteger('k_choice')->nullable(); // 1, 2, 3, or 4
            $table->timestamps();

            $table->unique(['assessment_id', 'question_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disc_answers');
    }
};

