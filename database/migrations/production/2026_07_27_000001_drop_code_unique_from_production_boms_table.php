<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'pgsql_production';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('production_boms', function (Blueprint $table) {
            // Drop unique constraint on code column to allow alternate versions
            $table->dropUnique('production_boms_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('production_boms', function (Blueprint $table) {
            $table->unique('code', 'production_boms_code_unique');
        });
    }
};
