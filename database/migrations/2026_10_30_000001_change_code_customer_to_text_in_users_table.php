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
            $table->text('code_customer')->nullable()->change();
            $table->text('expedition_code')->nullable()->change();
            $table->text('units')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('code_customer', 255)->nullable()->change();
            $table->string('expedition_code', 100)->nullable()->change();
            $table->string('units', 255)->nullable()->change();
        });
    }
};

