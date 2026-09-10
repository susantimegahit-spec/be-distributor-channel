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
        Schema::create('mst_program_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('mst_program')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['program_id', 'item_id'], 'uq_program_item');
            $table->index('item_id', 'idx_program_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_program_item');
    }
};
