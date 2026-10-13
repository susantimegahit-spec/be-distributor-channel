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
    protected $connection = 'pgsql_corporate';

    public function getConnection()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : $this->connection;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conn = $this->getConnection();

        if (config('database.default') === 'sqlite') {
            try {
                Schema::connection($conn)->table('tm_tasks', function (Blueprint $table) {
                    $table->text('title')->change();
                });
            } catch (\Throwable $e) {
                // SQLite table alter fallback
            }
            return;
        }

        // Alter title column to TEXT across possible schemas
        $statements = [
            "ALTER TABLE corporate.tm_tasks ALTER COLUMN title TYPE TEXT",
            "ALTER TABLE public.tm_tasks ALTER COLUMN title TYPE TEXT",
            "ALTER TABLE tm_tasks ALTER COLUMN title TYPE TEXT",
        ];

        foreach ($statements as $sql) {
            try {
                DB::connection($conn)->statement($sql);
            } catch (\Throwable $e) {
                // ignore if schema/table does not exist
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->getConnection();

        if (config('database.default') === 'sqlite') {
            return;
        }

        try {
            DB::connection($conn)->statement("ALTER TABLE corporate.tm_tasks ALTER COLUMN title TYPE VARCHAR(255)");
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
