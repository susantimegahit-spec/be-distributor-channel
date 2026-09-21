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

        // 1. tm_master_statuses
        if (!Schema::connection($conn)->hasTable('tm_master_statuses')) {
            Schema::connection($conn)->create('tm_master_statuses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('space_id')->nullable()->constrained('tm_spaces')->onDelete('cascade');
                $table->string('status_name', 100);
                $table->string('status_category', 30)->default('TO_DO'); // TO_DO, IN_PROGRESS, REVIEW, DONE, CANCELLED
                $table->string('color_hex', 10)->default('#94A3B8');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_closed_status')->default(false);
                $table->timestamps();

                $table->index('status_category');
            });
        }

        // 2. tm_master_priorities
        if (!Schema::connection($conn)->hasTable('tm_master_priorities')) {
            Schema::connection($conn)->create('tm_master_priorities', function (Blueprint $table) {
                $table->id();
                $table->string('priority_code', 50)->unique();
                $table->string('priority_name', 50);
                $table->string('color_hex', 10)->default('#3B82F6');
                $table->unsignedTinyInteger('level_weight')->default(3);
                $table->integer('target_sla_hours')->nullable();
                $table->timestamps();
            });
        }

        // 3. tm_master_task_types
        if (!Schema::connection($conn)->hasTable('tm_master_task_types')) {
            Schema::connection($conn)->create('tm_master_task_types', function (Blueprint $table) {
                $table->id();
                $table->string('type_code', 50)->unique();
                $table->string('type_name', 100);
                $table->string('icon_name', 50)->default('check-square');
                $table->string('color_hex', 10)->default('#3B82F6');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 4. tm_master_tags
        if (!Schema::connection($conn)->hasTable('tm_master_tags')) {
            Schema::connection($conn)->create('tm_master_tags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('space_id')->nullable()->constrained('tm_spaces')->onDelete('cascade');
                $table->string('tag_name', 100);
                $table->string('color_hex', 10)->default('#64748B');
                $table->timestamps();

                $table->unique(['space_id', 'tag_name']);
            });
        }

        // 5. tm_master_custom_fields
        if (!Schema::connection($conn)->hasTable('tm_master_custom_fields')) {
            Schema::connection($conn)->create('tm_master_custom_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignId('space_id')->nullable()->constrained('tm_spaces')->onDelete('cascade');
                $table->string('field_name', 100);
                $table->string('field_key', 100)->unique();
                $table->string('field_type', 30); // TEXT, NUMBER, DROPDOWN, DATE, CHECKBOX, CURRENCY, URL, RATING
                $table->json('options_json')->nullable();
                $table->boolean('is_required')->default(false);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->getConnection();
        Schema::connection($conn)->dropIfExists('tm_master_custom_fields');
        Schema::connection($conn)->dropIfExists('tm_master_tags');
        Schema::connection($conn)->dropIfExists('tm_master_task_types');
        Schema::connection($conn)->dropIfExists('tm_master_priorities');
        Schema::connection($conn)->dropIfExists('tm_master_statuses');
    }
};
