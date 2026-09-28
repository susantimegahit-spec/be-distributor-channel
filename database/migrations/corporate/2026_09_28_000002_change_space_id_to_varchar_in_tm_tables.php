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
            return;
        }

        // 1. Drop foreign key constraints pointing to tm_spaces.id
        $dropFks = [
            "ALTER TABLE corporate.tm_space_members DROP CONSTRAINT IF EXISTS tm_space_members_space_id_foreign",
            "ALTER TABLE corporate.tm_folders DROP CONSTRAINT IF EXISTS tm_folders_space_id_foreign",
            "ALTER TABLE corporate.tm_lists DROP CONSTRAINT IF EXISTS tm_lists_space_id_foreign",
            "ALTER TABLE corporate.tm_tasks DROP CONSTRAINT IF EXISTS tm_tasks_space_id_foreign",
            "ALTER TABLE corporate.tm_master_statuses DROP CONSTRAINT IF EXISTS tm_master_statuses_space_id_foreign",
            "ALTER TABLE corporate.tm_master_tags DROP CONSTRAINT IF EXISTS tm_master_tags_space_id_foreign",
            "ALTER TABLE corporate.tm_master_custom_fields DROP CONSTRAINT IF EXISTS tm_master_custom_fields_space_id_foreign",
        ];

        foreach ($dropFks as $sql) {
            try {
                DB::connection($conn)->statement($sql);
            } catch (\Throwable $e) {}
        }

        // 2. Alter tm_spaces.id to VARCHAR(100) and drop serial default
        try {
            DB::connection($conn)->statement("ALTER TABLE corporate.tm_spaces ALTER COLUMN id DROP DEFAULT");
        } catch (\Throwable $e) {}

        DB::connection($conn)->statement("ALTER TABLE corporate.tm_spaces ALTER COLUMN id TYPE VARCHAR(100) USING id::varchar");

        // 3. Alter space_id column in all referencing child tables
        $alterCols = [
            "ALTER TABLE corporate.tm_space_members ALTER COLUMN space_id TYPE VARCHAR(100) USING space_id::varchar",
            "ALTER TABLE corporate.tm_folders ALTER COLUMN space_id TYPE VARCHAR(100) USING space_id::varchar",
            "ALTER TABLE corporate.tm_lists ALTER COLUMN space_id TYPE VARCHAR(100) USING space_id::varchar",
            "ALTER TABLE corporate.tm_tasks ALTER COLUMN space_id TYPE VARCHAR(100) USING space_id::varchar",
            "ALTER TABLE corporate.tm_master_statuses ALTER COLUMN space_id TYPE VARCHAR(100) USING space_id::varchar",
            "ALTER TABLE corporate.tm_master_tags ALTER COLUMN space_id TYPE VARCHAR(100) USING space_id::varchar",
            "ALTER TABLE corporate.tm_master_custom_fields ALTER COLUMN space_id TYPE VARCHAR(100) USING space_id::varchar",
        ];

        foreach ($alterCols as $sql) {
            DB::connection($conn)->statement($sql);
        }

        // 4. Re-create foreign keys with ON UPDATE CASCADE
        $addFks = [
            "ALTER TABLE corporate.tm_space_members ADD CONSTRAINT tm_space_members_space_id_foreign FOREIGN KEY (space_id) REFERENCES corporate.tm_spaces(id) ON DELETE CASCADE ON UPDATE CASCADE",
            "ALTER TABLE corporate.tm_folders ADD CONSTRAINT tm_folders_space_id_foreign FOREIGN KEY (space_id) REFERENCES corporate.tm_spaces(id) ON DELETE CASCADE ON UPDATE CASCADE",
            "ALTER TABLE corporate.tm_lists ADD CONSTRAINT tm_lists_space_id_foreign FOREIGN KEY (space_id) REFERENCES corporate.tm_spaces(id) ON DELETE CASCADE ON UPDATE CASCADE",
            "ALTER TABLE corporate.tm_tasks ADD CONSTRAINT tm_tasks_space_id_foreign FOREIGN KEY (space_id) REFERENCES corporate.tm_spaces(id) ON DELETE RESTRICT ON UPDATE CASCADE",
            "ALTER TABLE corporate.tm_master_statuses ADD CONSTRAINT tm_master_statuses_space_id_foreign FOREIGN KEY (space_id) REFERENCES corporate.tm_spaces(id) ON DELETE CASCADE ON UPDATE CASCADE",
            "ALTER TABLE corporate.tm_master_tags ADD CONSTRAINT tm_master_tags_space_id_foreign FOREIGN KEY (space_id) REFERENCES corporate.tm_spaces(id) ON DELETE CASCADE ON UPDATE CASCADE",
            "ALTER TABLE corporate.tm_master_custom_fields ADD CONSTRAINT tm_master_custom_fields_space_id_foreign FOREIGN KEY (space_id) REFERENCES corporate.tm_spaces(id) ON DELETE CASCADE ON UPDATE CASCADE",
        ];

        foreach ($addFks as $sql) {
            try {
                DB::connection($conn)->statement($sql);
            } catch (\Throwable $e) {}
        }

        // 5. Update existing spaces to match their dept codes (e.g. IT, HRD, FIN, SCM)
        try {
            DB::connection($conn)->statement("UPDATE corporate.tm_spaces SET id = 'IT', department_id = 'IT' WHERE space_slug = 'it-department' OR id = '1'");
            DB::connection($conn)->statement("UPDATE corporate.tm_spaces SET id = 'HRD', department_id = 'HRD' WHERE space_slug = 'hrd-department' OR id = '2'");
            DB::connection($conn)->statement("UPDATE corporate.tm_spaces SET id = 'FIN', department_id = 'FIN' WHERE space_slug = 'finance-department' OR id = '3'");
            DB::connection($conn)->statement("UPDATE corporate.tm_spaces SET id = 'SCM', department_id = 'SCM' WHERE space_slug = 'supply-chain' OR id = '4'");
        } catch (\Throwable $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op rollback to prevent data loss
    }
};
