<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_program', function (Blueprint $table) {
            $table->string('code_customer', 50)->nullable()->after('program_code');
            $table->index('code_customer', 'idx_program_customer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mst_program', function (Blueprint $table) {
            $table->dropIndex('idx_program_customer');
            $table->dropColumn('code_customer');
        });
    }
};
