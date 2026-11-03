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
        Schema::create('disc_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('question_number')->unique();
            $table->text('statement_1');
            $table->text('statement_2');
            $table->text('statement_3');
            $table->text('statement_4');
            $table->string('p_key_1', 5);
            $table->string('p_key_2', 5);
            $table->string('p_key_3', 5);
            $table->string('p_key_4', 5);
            $table->string('k_key_1', 5);
            $table->string('k_key_2', 5);
            $table->string('k_key_3', 5);
            $table->string('k_key_4', 5);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disc_questions');
    }
};

