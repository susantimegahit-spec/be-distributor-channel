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
        Schema::create('trx_program_upload_batch', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no', 50)->unique();
            $table->string('file_name', 255)->nullable();
            $table->string('uploaded_by', 50)->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_program_upload_batch');
    }
};
