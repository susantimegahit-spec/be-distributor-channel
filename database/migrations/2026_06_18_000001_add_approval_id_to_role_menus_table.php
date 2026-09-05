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
        Schema::table('role_menus', function (Blueprint $table) {
            $table->foreignId('approval_id')->nullable()->constrained('master_approvals')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_menus', function (Blueprint $table) {
            $table->dropForeign(['approval_id']);
            $table->dropColumn('approval_id');
        });
    }
};
