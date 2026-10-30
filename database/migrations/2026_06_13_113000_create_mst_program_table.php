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
        Schema::create('mst_program', function (Blueprint $table) {
            $table->id();
            $table->string('program_code', 30)->unique();
            $table->string('program_name', 200);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->string('created_by', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['start_date', 'end_date'], 'idx_program_period');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE mst_program ADD CONSTRAINT chk_program_status CHECK (status IN ('ACTIVE', 'INACTIVE'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_program');
    }
};
