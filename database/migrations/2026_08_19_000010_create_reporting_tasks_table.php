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
        if (!Schema::hasTable('reporting_tasks')) {
            Schema::create('reporting_tasks', function (Blueprint $table) {
                $table->id();
                $table->string('task_id', 100)->unique()->comment('ClickUp Task ID');
                $table->string('task_name', 255)->nullable()->comment('Task Name / Title');

                // ClickUp Hierarchy Locations
                $table->string('space_id', 100)->nullable()->comment('ClickUp Space ID');
                $table->string('space_name', 255)->nullable()->comment('ClickUp Space Name');
                $table->string('folder_name', 255)->nullable()->comment('ClickUp Folder Name');
                $table->string('list_name', 255)->nullable()->comment('ClickUp List Name');

                $table->string('assignee', 255)->nullable()->comment('Assignee Name / Email');
                $table->string('timeline', 100)->nullable()->comment('Timeline descriptor (e.g. Sprint 1, Q3)');
                $table->dateTime('start_date')->nullable()->comment('Start Date & Time');
                $table->dateTime('due_date')->nullable()->comment('Due Date & Time');
                $table->string('priority', 50)->nullable()->comment('Priority: Urgent, High, Normal, Low');
                $table->string('task_type', 100)->nullable()->comment('Task Type / Category');
                $table->string('created_by', 255)->nullable()->comment('Task Creator');
                $table->text('comment')->nullable()->comment('Latest Comment / Description / Remarks');
                $table->string('status', 100)->nullable()->comment('Task Status (e.g. to do, in progress, complete)');
                $table->dateTime('synced_at')->nullable()->comment('Timestamp when synced from n8n');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_tasks');
    }
};
