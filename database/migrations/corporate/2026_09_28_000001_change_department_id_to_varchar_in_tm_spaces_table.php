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
            Schema::connection($conn)->table('tm_spaces', function (Blueprint $table) {
                $table->string('department_id', 100)->nullable()->change();
            });
            return;
        }

        // Drop foreign key if exists in PostgreSQL
        try {
            DB::connection($conn)->statement("ALTER TABLE corporate.tm_spaces DROP CONSTRAINT IF EXISTS tm_spaces_department_id_foreign");
        } catch (\Throwable $e) {
            // ignore
        }

        // Alter column type to varchar(100)
        DB::connection($conn)->statement("ALTER TABLE corporate.tm_spaces ALTER COLUMN department_id TYPE VARCHAR(100) USING department_id::varchar");
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
            DB::connection($conn)->statement("ALTER TABLE corporate.tm_spaces ALTER COLUMN department_id TYPE BIGINT USING NULL");
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
