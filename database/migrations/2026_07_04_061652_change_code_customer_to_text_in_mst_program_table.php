<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_program', function (Blueprint $table) {
            if (\DB::getDriverName() !== 'sqlite') {
                $table->dropIndex('idx_program_customer');
            }
        });

        Schema::table('mst_program', function (Blueprint $table) {
            $table->text('code_customer')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mst_program', function (Blueprint $table) {
            $table->string('code_customer', 50)->nullable()->change();
            if (\DB::getDriverName() !== 'sqlite') {
                $table->index('code_customer', 'idx_program_customer');
            }
        });
    }
};
