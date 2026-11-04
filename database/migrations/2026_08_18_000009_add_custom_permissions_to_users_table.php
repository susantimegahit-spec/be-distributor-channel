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
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'custom_permissions')) {
                    $table->longText('custom_permissions')->nullable()->after('stage')->comment('Custom User-Level Permission Overrides (JSON String / LongText)');
                } else {
                    $table->longText('custom_permissions')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'custom_permissions')) {
                    $table->dropColumn('custom_permissions');
                }
            });
        }
    }
};
