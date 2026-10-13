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
        Schema::create('trx_program_result', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('trx_program_upload')->onDelete('cascade');
            $table->foreignId('program_id')->nullable()->constrained('mst_program')->onDelete('cascade');
            $table->string('customer_code', 50)->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->string('item_code', 50)->nullable();
            $table->string('item_name', 255)->nullable();
            $table->decimal('qty_kg', 18, 2)->nullable();
            $table->decimal('sell_price_per_kg', 18, 2)->nullable();
            $table->decimal('harga_program_per_kg', 18, 2)->nullable();
            $table->decimal('diskon_per_kg', 18, 2)->nullable();
            $table->decimal('total_diskon', 18, 2)->nullable();
            $table->date('transaction_date')->nullable();
            $table->string('status', 30)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('program_id', 'idx_result_program');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE trx_program_result ADD CONSTRAINT chk_result_status CHECK (status IN ('VALID_PROGRAM', 'ITEM_NOT_FOUND', 'PROGRAM_NOT_FOUND', 'STRATA_NOT_FOUND'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_program_result');
    }
};
