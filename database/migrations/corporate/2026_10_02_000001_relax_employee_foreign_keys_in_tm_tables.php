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

        // 1. Dynamic PostgreSQL block: inspect pg_constraint and drop ANY foreign keys
        // on tm_% tables referencing hris_employees / hris_employees_2 or employee columns
        try {
            DB::connection($conn)->statement("
                DO \$\$
                DECLARE
                    r RECORD;
                BEGIN
                    FOR r IN (
                        SELECT c.conname, t.relname, n.nspname
                        FROM pg_constraint c
                        JOIN pg_class t ON c.conrelid = t.oid
                        JOIN pg_namespace n ON t.relnamespace = n.oid
                        WHERE c.contype = 'f'
                          AND t.relname LIKE 'tm_%'
                          AND (c.conname LIKE '%employee%' OR c.confrelid::regclass::text LIKE '%hris_employee%')
                    ) LOOP
                        BEGIN
                            EXECUTE format('ALTER TABLE %I.%I DROP CONSTRAINT IF EXISTS %I CASCADE', r.nspname, r.relname, r.conname);
                        EXCEPTION WHEN OTHERS THEN
                            -- continue with next constraint
                        END;
                    END LOOP;
                END \$\$;
            ");
        } catch (\Throwable $e) {
            // fallback to explicit drops below
        }

        // 2. Explicit drops across various schema qualifications (unqualified, corporate., public.)
        $schemas = ['', 'corporate.', 'public.'];
        $constraints = [
            'tm_tasks' => [
                'tm_tasks_created_by_employee_id_foreign',
                'tm_tasks_approved_by_employee_id_foreign',
            ],
            'tm_task_activities' => [
                'tm_task_activities_performed_by_employee_id_foreign',
            ],
            'tm_task_comments' => [
                'tm_task_comments_author_employee_id_foreign',
            ],
            'tm_task_time_trackings' => [
                'tm_task_time_trackings_employee_id_foreign',
            ],
            'tm_task_checklists' => [
                'tm_task_checklists_created_by_employee_id_foreign',
            ],
            'tm_task_checklist_items' => [
                'tm_task_checklist_items_assignee_employee_id_foreign',
                'tm_task_checklist_items_completed_by_employee_id_foreign',
            ],
            'tm_task_attachments' => [
                'tm_task_attachments_uploaded_by_employee_id_foreign',
            ],
            'tm_task_assignees' => [
                'tm_task_assignees_employee_id_foreign',
                'tm_task_assignees_assigned_by_employee_id_foreign',
            ],
        ];

        foreach ($schemas as $schema) {
            foreach ($constraints as $table => $fks) {
                foreach ($fks as $fk) {
                    try {
                        DB::connection($conn)->statement("ALTER TABLE {$schema}{$table} DROP CONSTRAINT IF EXISTS {$fk} CASCADE");
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }

            try {
                DB::connection($conn)->statement("ALTER TABLE {$schema}tm_tasks ALTER COLUMN created_by_employee_id DROP NOT NULL");
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // 3. If hris_employees_2 exists, sync any missing records from hris_employees
        try {
            DB::connection($conn)->statement("
                DO \$\$
                BEGIN
                    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'hris_employees_2') AND
                       EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'hris_employees') THEN
                        INSERT INTO hris_employees_2 (id, nik, user_id, full_name, nickname, email_office, phone_number, department_id, division_id, position_id, employment_status, is_active, created_at, updated_at)
                        SELECT id, nik, user_id, full_name, nickname, email_office, phone_number, department_id, division_id, position_id, employment_status, is_active, created_at, updated_at
                        FROM hris_employees
                        ON CONFLICT (id) DO NOTHING;
                    END IF;
                EXCEPTION WHEN OTHERS THEN
                    -- ignore
                END \$\$;
            ");
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op rollback to prevent data loss
    }
};
