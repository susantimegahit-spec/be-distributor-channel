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
        if (Schema::hasTable('reporting_tasks')) {
            Schema::table('reporting_tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('reporting_tasks', 'space_id')) {
                    $table->string('space_id', 100)->nullable()->after('task_name')->comment('ClickUp Space ID');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('reporting_tasks')) {
            Schema::table('reporting_tasks', function (Blueprint $table) {
                if (Schema::hasColumn('reporting_tasks', 'space_id')) {
                    $table->dropColumn('space_id');
                }
            });
        }
    }
};
