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
        Schema::create('trx_program_upload', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('trx_program_upload_batch')->onDelete('cascade');
            $table->string('customer_code', 50);
            $table->string('customer_name', 255)->nullable();
            $table->string('item_code', 50);
            $table->string('item_name', 255)->nullable();
            $table->decimal('sell_price_per_kg', 18, 2)->nullable();
            $table->decimal('qty_kg', 18, 2);
            $table->string('customer_type', 2);
            $table->date('transaction_date');
            $table->timestamp('uploaded_at')->useCurrent();

            $table->index('batch_id', 'idx_upload_batch');
            $table->index('item_code', 'idx_upload_item');
            $table->index('transaction_date', 'idx_upload_date');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE trx_program_upload ADD CONSTRAINT chk_upload_customer_type CHECK (customer_type IN ('GT', 'MT'))");
            DB::statement("ALTER TABLE trx_program_upload ADD CONSTRAINT chk_upload_qty_kg CHECK (qty_kg > 0)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_program_upload');
    }
};
