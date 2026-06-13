<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mst_program_strata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('mst_program')->onDelete('cascade');
            $table->string('customer_type', 2);
            $table->decimal('min_qty_kg', 18, 2);
            $table->decimal('max_qty_kg', 18, 2)->nullable();
            $table->decimal('harga_program_per_kg', 18, 2);
            $table->decimal('diskon_per_kg', 18, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['program_id', 'customer_type'], 'idx_strata_lookup');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE mst_program_strata ADD CONSTRAINT chk_customer_type CHECK (customer_type IN ('GT', 'MT'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_program_strata');
    }
};
