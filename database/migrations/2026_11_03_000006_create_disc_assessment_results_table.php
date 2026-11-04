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
        Schema::create('disc_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->unique()->constrained('disc_assessments')->cascadeOnDelete();
            $table->jsonb('p_counts');    // {"D": x, "I": x, "S": x, "C": x, "star": x, "total": 24}
            $table->jsonb('k_counts');    // {"D": x, "I": x, "S": x, "C": x, "star": x, "total": 24}
            $table->jsonb('net_counts');  // {"D": x, "I": x, "S": x, "C": x}
            $table->jsonb('chart_scores'); // {"line1": {"D": x, ...}, "line2": {...}, "line3": {...}}
            $table->foreignId('profile_id')->nullable()->constrained('disc_profiles')->nullOnDelete();
            $table->unsignedSmallInteger('profile_number')->nullable();
            $table->string('profile_title', 255)->nullable();
            $table->string('dimension_code', 50)->nullable();
            $table->unsignedSmallInteger('matched_rule_index')->nullable();
            $table->string('status', 30)->default('valid'); // valid, review_required
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disc_assessment_results');
    }
};

