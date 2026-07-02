<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'pgsql_ekspedisi';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pastikan skema ekspedisi terbuat di Postgres
        try {
            DB::statement('CREATE SCHEMA IF NOT EXISTS ekspedisi');
        } catch (\Throwable $e) {
            // Abaikan jika tidak memiliki hak akses (skema mungkin sudah dibuat manual)
        }

        Schema::connection($this->connection)->create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('provinces');
    }
};
