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
        if (!Schema::hasTable('approval_workflows')) {
            Schema::create('approval_workflows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_type_id')->constrained('document_types')->onDelete('cascade');
                $table->string('name', 150);
                $table->decimal('min_amount', 18, 4)->default(0);
                $table->decimal('max_amount', 18, 4)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('approval_workflow_stages')) {
            Schema::create('approval_workflow_stages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->onDelete('cascade');
                $table->integer('level')->default(1);
                $table->string('stage_name', 100);
                $table->foreignId('role_id')->nullable()->constrained('roles');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->string('notification_type', 50)->default('web');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_stages');
        Schema::dropIfExists('approval_workflows');
    }
};
