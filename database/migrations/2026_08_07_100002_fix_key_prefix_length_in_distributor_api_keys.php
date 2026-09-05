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
        if (Schema::hasTable('distributor_api_keys')) {
            Schema::table('distributor_api_keys', function (Blueprint $table) {
                $table->string('key_prefix', 30)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('distributor_api_keys')) {
            Schema::table('distributor_api_keys', function (Blueprint $table) {
                $table->string('key_prefix', 10)->change();
            });
        }
    }
};
