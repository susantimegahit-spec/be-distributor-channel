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
        Schema::create('disc_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('disc_candidates')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('status', 30)->default('in_progress')->index(); // in_progress, completed, scored, review_required
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disc_assessments');
    }
};

