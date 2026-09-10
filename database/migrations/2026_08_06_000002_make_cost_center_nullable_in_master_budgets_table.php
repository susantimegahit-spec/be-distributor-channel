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
        if (Schema::hasTable('master_budgets')) {
            Schema::table('master_budgets', function (Blueprint $table) {
                $table->string('cost_center', 100)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('master_budgets')) {
            Schema::table('master_budgets', function (Blueprint $table) {
                $table->string('cost_center', 100)->nullable(false)->change();
            });
        }
    }
};
