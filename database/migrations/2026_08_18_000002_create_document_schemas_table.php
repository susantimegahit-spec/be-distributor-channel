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
        if (!Schema::hasTable('document_schemas')) {
            Schema::create('document_schemas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_type_id')->constrained('document_types')->onDelete('cascade');
                $table->integer('version')->default(1);
                $table->string('name', 100);
                $table->json('layout_config')->nullable(); // tabs, sections hierarchy, grid settings
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['document_type_id', 'version']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_schemas');
    }
};
