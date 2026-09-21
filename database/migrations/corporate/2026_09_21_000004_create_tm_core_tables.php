<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        // 1. tm_tasks
        if (!Schema::connection($conn)->hasTable('tm_tasks')) {
            Schema::connection($conn)->create('tm_tasks', function (Blueprint $table) {
                $table->id();
                $table->string('task_code', 100)->unique();
                $table->string('legacy_clickup_id', 100)->nullable()->unique();

                $table->foreignId('space_id')->constrained('tm_spaces')->onDelete('restrict');
                $table->foreignId('folder_id')->nullable()->constrained('tm_folders')->onDelete('set null');
                $table->foreignId('list_id')->constrained('tm_lists')->onDelete('restrict');
                $table->foreignId('parent_task_id')->nullable()->constrained('tm_tasks')->onDelete('cascade');

                $table->string('title', 255);
                $table->longText('description')->nullable();

                $table->foreignId('status_id')->constrained('tm_master_statuses')->onDelete('restrict');
                $table->foreignId('priority_id')->constrained('tm_master_priorities')->onDelete('restrict');
                $table->foreignId('task_type_id')->constrained('tm_master_task_types')->onDelete('restrict');

                $table->dateTime('start_date')->nullable();
                $table->dateTime('due_date')->nullable()->index();
                $table->dateTime('completed_at')->nullable();

                $table->decimal('estimated_hours', 8, 2)->default(0.00);
                $table->decimal('actual_hours', 8, 2)->default(0.00);
                $table->unsignedTinyInteger('progress_percentage')->default(0);

                $table->foreignId('created_by_employee_id')->constrained('hris_employees')->onDelete('restrict');
                $table->foreignId('approved_by_employee_id')->nullable()->constrained('hris_employees')->onDelete('set null');

                $table->boolean('is_milestone')->default(false);
                $table->boolean('is_archived')->default(false);
                $table->integer('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('status_id');
                $table->index('priority_id');
                $table->index('list_id');
                $table->index('parent_task_id');
            });
        }

        // 2. tm_task_assignees
        if (!Schema::connection($conn)->hasTable('tm_task_assignees')) {
            Schema::connection($conn)->create('tm_task_assignees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tm_tasks')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('hris_employees')->onDelete('cascade');
                $table->foreignId('assigned_by_employee_id')->constrained('hris_employees')->onDelete('restrict');
                $table->timestamp('assigned_at')->useCurrent();

                $table->unique(['task_id', 'employee_id']);
                $table->index('employee_id');
            });
        }

        // 3. tm_task_watchers
        if (!Schema::connection($conn)->hasTable('tm_task_watchers')) {
            Schema::connection($conn)->create('tm_task_watchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tm_tasks')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('hris_employees')->onDelete('cascade');
                $table->timestamps();

                $table->unique(['task_id', 'employee_id']);
            });
        }

        // 4. tm_task_tags
        if (!Schema::connection($conn)->hasTable('tm_task_tags')) {
            Schema::connection($conn)->create('tm_task_tags', function (Blueprint $table) {
                $table->foreignId('task_id')->constrained('tm_tasks')->onDelete('cascade');
                $table->foreignId('tag_id')->constrained('tm_master_tags')->onDelete('cascade');
                $table->primary(['task_id', 'tag_id']);
            });
        }

        // 5. tm_task_custom_field_values
        if (!Schema::connection($conn)->hasTable('tm_task_custom_field_values')) {
            Schema::connection($conn)->create('tm_task_custom_field_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tm_tasks')->onDelete('cascade');
                $table->foreignId('custom_field_id')->constrained('tm_master_custom_fields')->onDelete('cascade');
                $table->text('value_text')->nullable();
                $table->decimal('value_number', 15, 4)->nullable();
                $table->date('value_date')->nullable();
                $table->json('value_json')->nullable();
                $table->timestamps();

                $table->unique(['task_id', 'custom_field_id']);
            });
        }

        // 6. tm_task_checklists
        if (!Schema::connection($conn)->hasTable('tm_task_checklists')) {
            Schema::connection($conn)->create('tm_task_checklists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tm_tasks')->onDelete('cascade');
                $table->string('checklist_title', 150)->default('Checklist Pekerjaan');
                $table->timestamps();
            });
        }

        // 7. tm_task_checklist_items
        if (!Schema::connection($conn)->hasTable('tm_task_checklist_items')) {
            Schema::connection($conn)->create('tm_task_checklist_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('checklist_id')->constrained('tm_task_checklists')->onDelete('cascade');
                $table->string('item_text', 255);
                $table->boolean('is_completed')->default(false);
                $table->foreignId('assignee_employee_id')->nullable()->constrained('hris_employees')->onDelete('set null');
                $table->date('due_date')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->foreignId('completed_by_employee_id')->nullable()->constrained('hris_employees')->onDelete('set null');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 8. tm_task_attachments
        if (!Schema::connection($conn)->hasTable('tm_task_attachments')) {
            Schema::connection($conn)->create('tm_task_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tm_tasks')->onDelete('cascade');
                $table->string('file_name', 255);
                $table->string('file_path', 255);
                $table->unsignedBigInteger('file_size_bytes');
                $table->string('mime_type', 100);
                $table->foreignId('uploaded_by_employee_id')->constrained('hris_employees')->onDelete('restrict');
                $table->timestamps();
            });
        }

        // 9. tm_task_comments
        if (!Schema::connection($conn)->hasTable('tm_task_comments')) {
            Schema::connection($conn)->create('tm_task_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tm_tasks')->onDelete('cascade');
                $table->foreignId('parent_comment_id')->nullable()->constrained('tm_task_comments')->onDelete('cascade');
                $table->foreignId('author_employee_id')->constrained('hris_employees')->onDelete('restrict');
                $table->longText('comment_text');
                $table->boolean('is_internal_only')->default(false);
                $table->timestamps();

                $table->index('task_id');
            });
        }

        // 10. tm_task_time_trackings
        if (!Schema::connection($conn)->hasTable('tm_task_time_trackings')) {
            Schema::connection($conn)->create('tm_task_time_trackings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tm_tasks')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('hris_employees')->onDelete('cascade');
                $table->dateTime('start_time');
                $table->dateTime('end_time')->nullable();
                $table->unsignedInteger('duration_minutes')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'start_time']);
            });
        }

        // 11. tm_task_dependencies
        if (!Schema::connection($conn)->hasTable('tm_task_dependencies')) {
            Schema::connection($conn)->create('tm_task_dependencies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tm_tasks')->onDelete('cascade');
                $table->foreignId('depends_on_task_id')->constrained('tm_tasks')->onDelete('cascade');
                $table->string('dependency_type', 30)->default('WAITING_ON'); // WAITING_ON, BLOCKING
                $table->timestamps();

                $table->unique(['task_id', 'depends_on_task_id']);
            });
        }

        // 12. tm_task_activity_logs
        if (!Schema::connection($conn)->hasTable('tm_task_activity_logs')) {
            Schema::connection($conn)->create('tm_task_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tm_tasks')->onDelete('cascade');
                $table->foreignId('performed_by_employee_id')->constrained('hris_employees')->onDelete('restrict');
                $table->string('action_type', 50); // STATUS_CHANGE, ASSIGNEE_ADDED, DUE_DATE_EXTENDED, COMMENT_ADDED, FILE_UPLOADED
                $table->string('field_name', 100)->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->timestamps();

                $table->index('task_id');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->getConnection();
        Schema::connection($conn)->dropIfExists('tm_task_activity_logs');
        Schema::connection($conn)->dropIfExists('tm_task_dependencies');
        Schema::connection($conn)->dropIfExists('tm_task_time_trackings');
        Schema::connection($conn)->dropIfExists('tm_task_comments');
        Schema::connection($conn)->dropIfExists('tm_task_attachments');
        Schema::connection($conn)->dropIfExists('tm_task_checklist_items');
        Schema::connection($conn)->dropIfExists('tm_task_checklists');
        Schema::connection($conn)->dropIfExists('tm_task_custom_field_values');
        Schema::connection($conn)->dropIfExists('tm_task_tags');
        Schema::connection($conn)->dropIfExists('tm_task_watchers');
        Schema::connection($conn)->dropIfExists('tm_task_assignees');
        Schema::connection($conn)->dropIfExists('tm_tasks');
    }
};
