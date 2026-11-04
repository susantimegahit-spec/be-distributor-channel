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
        Schema::table('disc_candidates', function (Blueprint $table) {
            if (!Schema::hasColumn('disc_candidates', 'password')) {
                $table->string('password')->nullable()->after('email');
            }
            if (!Schema::hasColumn('disc_candidates', 'remember_token')) {
                $table->rememberToken()->after('password');
            }
            if (!Schema::hasColumn('disc_candidates', 'avatar')) {
                $table->string('avatar', 255)->nullable()->after('gender');
            }
            if (!Schema::hasColumn('disc_candidates', 'biodata')) {
                $table->jsonb('biodata')->nullable()->after('position_applied');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disc_candidates', function (Blueprint $table) {
            $table->dropColumn(['password', 'remember_token', 'avatar', 'biodata']);
        });
    }
};

