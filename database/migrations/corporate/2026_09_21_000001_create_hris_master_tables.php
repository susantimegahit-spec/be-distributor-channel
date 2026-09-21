<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        if (config('database.default') !== 'sqlite') {
            try {
                DB::connection('pgsql')->statement('CREATE SCHEMA IF NOT EXISTS corporate');
            } catch (\Throwable $e) {
                Log::warning("Failed to auto-create schema 'corporate': " . $e->getMessage());
            }
        }

        // 1. hris_departments
        if (!Schema::connection($this->connection)->hasTable('hris_departments')) {
            Schema::connection($this->connection)->create('hris_departments', function (Blueprint $table) {
                $table->id();
                $table->string('dept_code', 50)->unique();
                $table->string('dept_name', 150);
                $table->string('sap_ocr_code3', 50)->nullable()->index();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('head_employee_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('dept_code');
            });
        }

        // 2. hris_divisions
        if (!Schema::connection($this->connection)->hasTable('hris_divisions')) {
            Schema::connection($this->connection)->create('hris_divisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->constrained('hris_departments')->onDelete('cascade');
                $table->string('division_code', 50);
                $table->string('division_name', 150);
                $table->unsignedBigInteger('lead_employee_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['department_id', 'division_code']);
            });
        }

        // 3. hris_positions
        if (!Schema::connection($this->connection)->hasTable('hris_positions')) {
            Schema::connection($this->connection)->create('hris_positions', function (Blueprint $table) {
                $table->id();
                $table->string('position_code', 50)->unique();
                $table->string('position_name', 150);
                $table->unsignedTinyInteger('level_grade')->default(1)->index();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 4. hris_employees
        if (!Schema::connection($this->connection)->hasTable('hris_employees')) {
            Schema::connection($this->connection)->create('hris_employees', function (Blueprint $table) {
                $table->id();
                $table->string('nik', 50)->unique()->index();
                $table->unsignedBigInteger('user_id')->nullable()->unique();
                $table->string('full_name', 150);
                $table->string('nickname', 100)->nullable();
                $table->string('email_office', 150)->unique();
                $table->string('phone_number', 50)->nullable();
                $table->string('telegram_chat_id', 100)->nullable();

                $table->foreignId('department_id')->constrained('hris_departments')->onDelete('restrict');
                $table->foreignId('division_id')->nullable()->constrained('hris_divisions')->onDelete('set null');
                $table->foreignId('position_id')->constrained('hris_positions')->onDelete('restrict');
                $table->foreignId('direct_supervisor_id')->nullable()->constrained('hris_employees')->onDelete('set null');

                $table->string('employment_status', 30)->default('PROBATION'); // PERMANENT, CONTRACT, PROBATION, INTERNSHIP, RESIGNED
                $table->date('join_date')->nullable();
                $table->date('resign_date')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->string('avatar_url', 255)->nullable();
                $table->timestamps();

                $table->index('department_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('hris_employees');
        Schema::connection($this->connection)->dropIfExists('hris_positions');
        Schema::connection($this->connection)->dropIfExists('hris_divisions');
        Schema::connection($this->connection)->dropIfExists('hris_departments');
    }
};
