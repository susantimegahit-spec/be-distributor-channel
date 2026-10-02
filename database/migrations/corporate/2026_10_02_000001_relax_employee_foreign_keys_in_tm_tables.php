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

        // Drop restrictive foreign key constraints referencing hris_employees
        $dropFks = [
            "ALTER TABLE corporate.tm_tasks DROP CONSTRAINT IF EXISTS tm_tasks_created_by_employee_id_foreign",
            "ALTER TABLE corporate.tm_tasks DROP CONSTRAINT IF EXISTS tm_tasks_approved_by_employee_id_foreign",
            "ALTER TABLE corporate.tm_task_activities DROP CONSTRAINT IF EXISTS tm_task_activities_performed_by_employee_id_foreign",
            "ALTER TABLE corporate.tm_task_comments DROP CONSTRAINT IF EXISTS tm_task_comments_author_employee_id_foreign",
            "ALTER TABLE corporate.tm_task_time_trackings DROP CONSTRAINT IF EXISTS tm_task_time_trackings_employee_id_foreign",
            "ALTER TABLE corporate.tm_task_checklists DROP CONSTRAINT IF EXISTS tm_task_checklists_created_by_employee_id_foreign",
            "ALTER TABLE corporate.tm_task_checklist_items DROP CONSTRAINT IF EXISTS tm_task_checklist_items_assignee_employee_id_foreign",
            "ALTER TABLE corporate.tm_task_checklist_items DROP CONSTRAINT IF EXISTS tm_task_checklist_items_completed_by_employee_id_foreign",
            "ALTER TABLE corporate.tm_task_attachments DROP CONSTRAINT IF EXISTS tm_task_attachments_uploaded_by_employee_id_foreign",
            "ALTER TABLE corporate.tm_task_assignees DROP CONSTRAINT IF EXISTS tm_task_assignees_employee_id_foreign",
            "ALTER TABLE corporate.tm_task_assignees DROP CONSTRAINT IF EXISTS tm_task_assignees_assigned_by_employee_id_foreign",
        ];

        foreach ($dropFks as $sql) {
            try {
                DB::connection($conn)->statement($sql);
            } catch (\Throwable $e) {
                // ignore if already dropped or table does not exist
            }
        }

        // Also make created_by_employee_id nullable just in case
        try {
            DB::connection($conn)->statement("ALTER TABLE corporate.tm_tasks ALTER COLUMN created_by_employee_id DROP NOT NULL");
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-adding strict foreign keys omitted to avoid integrity failures
    }
};
