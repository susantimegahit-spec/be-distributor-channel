<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $conn = $this->connection;
        $driver = Schema::connection($conn)->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // Drop foreign key constraint if exists
            DB::connection($conn)->statement('ALTER TABLE ekspedisi.picklists DROP CONSTRAINT IF EXISTS picklists_expedition_id_foreign');
            // Change column type to VARCHAR(100)
            DB::connection($conn)->statement('ALTER TABLE ekspedisi.picklists ALTER COLUMN expedition_id TYPE VARCHAR(100)');
        } elseif ($driver === 'sqlite') {
            if (Schema::connection($conn)->hasTable('picklists') && Schema::connection($conn)->hasColumn('picklists', 'expedition_id')) {
                try {
                    Schema::connection($conn)->table('picklists', function (Blueprint $table) {
                        $table->string('expedition_id', 100)->nullable()->change();
                    });
                } catch (\Throwable $e) {
                    // Ignored in SQLite if change() is not supported without doctrine/dbal
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->connection;
        $driver = Schema::connection($conn)->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::connection($conn)->statement('ALTER TABLE ekspedisi.picklists ALTER COLUMN expedition_id TYPE BIGINT USING (NULLIF(expedition_id, \'\')::bigint)');
        }
    }
};
