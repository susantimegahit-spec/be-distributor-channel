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
        if (!Schema::hasTable('document_fields')) {
            Schema::create('document_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_schema_id')->constrained('document_schemas')->onDelete('cascade');
                $table->string('section', 50)->default('header'); // header, line, summary, logistics, accounting
                $table->string('field_code', 100);
                $table->string('label', 150);
                $table->string('field_type', 50)->default('text'); // text, number, currency, date, datetime, badge, lookup, boolean
                $table->string('source_type', 50)->default('direct'); // direct, lookup, calculated, static
                $table->string('source', 255)->nullable();
                $table->json('lookup_config')->nullable();
                $table->json('calculation_config')->nullable();
                $table->json('formatter_config')->nullable();
                $table->json('ui_props')->nullable(); // col_span, align, width, tab, is_highlight, etc.
                $table->boolean('is_required')->default(false);
                $table->boolean('is_readonly')->default(true);
                $table->boolean('is_visible')->default(true);
                $table->integer('sequence')->default(0);
                $table->timestamps();

                $table->index(['document_schema_id', 'section', 'sequence']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_fields');
    }
};
