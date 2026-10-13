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
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'accessible_systems')) {
                $table->dropColumn('accessible_systems');
            }
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->string('accessible_systems')
                ->nullable()
                ->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'accessible_systems')) {
                $table->dropColumn('accessible_systems');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('accessible_systems')
                ->nullable()
                ->after('code_customer');
        });
    }
};
