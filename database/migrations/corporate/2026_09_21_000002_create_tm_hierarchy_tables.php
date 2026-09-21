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

        // 1. tm_workspaces
        if (!Schema::connection($conn)->hasTable('tm_workspaces')) {
            Schema::connection($conn)->create('tm_workspaces', function (Blueprint $table) {
                $table->id();
                $table->string('workspace_code', 50)->unique();
                $table->string('name', 150)->default('PT Susanti Megah Perkasa');
                $table->text('description')->nullable();
                $table->string('logo_url', 255)->nullable();
                $table->unsignedBigInteger('owner_user_id');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. tm_spaces
        if (!Schema::connection($conn)->hasTable('tm_spaces')) {
            Schema::connection($conn)->create('tm_spaces', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('tm_workspaces')->onDelete('cascade');
                $table->foreignId('department_id')->nullable()->constrained('hris_departments')->onDelete('set null');
                $table->string('space_name', 150);
                $table->string('space_slug', 150)->unique();
                $table->string('color_hex', 10)->default('#4F46E5');
                $table->string('icon_name', 50)->default('folder');
                $table->text('description')->nullable();
                $table->boolean('is_private')->default(false);
                $table->unsignedBigInteger('created_by_user_id');
                $table->timestamps();

                $table->index('department_id');
            });
        }

        // 3. tm_space_members
        if (!Schema::connection($conn)->hasTable('tm_space_members')) {
            Schema::connection($conn)->create('tm_space_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('space_id')->constrained('tm_spaces')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('hris_employees')->onDelete('cascade');
                $table->string('space_role', 30)->default('MEMBER'); // OWNER, ADMIN, MEMBER, VIEWER
                $table->timestamps();

                $table->unique(['space_id', 'employee_id']);
            });
        }

        // 4. tm_folders
        if (!Schema::connection($conn)->hasTable('tm_folders')) {
            Schema::connection($conn)->create('tm_folders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('space_id')->constrained('tm_spaces')->onDelete('cascade');
                $table->string('folder_name', 150);
                $table->text('description')->nullable();
                $table->string('color_hex', 10)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_hidden')->default(false);
                $table->boolean('is_archived')->default(false);
                $table->unsignedBigInteger('created_by_user_id');
                $table->timestamps();

                $table->index('space_id');
            });
        }

        // 5. tm_lists
        if (!Schema::connection($conn)->hasTable('tm_lists')) {
            Schema::connection($conn)->create('tm_lists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('space_id')->constrained('tm_spaces')->onDelete('cascade');
                $table->foreignId('folder_id')->nullable()->constrained('tm_folders')->onDelete('set null');
                $table->string('list_name', 150);
                $table->text('description')->nullable();
                $table->string('color_hex', 10)->nullable();
                $table->string('default_view', 30)->default('LIST'); // LIST, BOARD, CALENDAR, GANTT
                $table->integer('sort_order')->default(0);
                $table->boolean('is_archived')->default(false);
                $table->unsignedBigInteger('created_by_user_id');
                $table->timestamps();

                $table->index('space_id');
                $table->index('folder_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->getConnection();
        Schema::connection($conn)->dropIfExists('tm_lists');
        Schema::connection($conn)->dropIfExists('tm_folders');
        Schema::connection($conn)->dropIfExists('tm_space_members');
        Schema::connection($conn)->dropIfExists('tm_spaces');
        Schema::connection($conn)->dropIfExists('tm_workspaces');
    }
};
