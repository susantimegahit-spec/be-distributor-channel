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
        if (!Schema::hasTable('user_organization_assignments')) {
            Schema::create('user_organization_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('type', 50)->comment('warehouse, branch, business_unit, department, expedition, distributor');
                $table->string('value', 100)->comment('Code or ID of the assigned entity (e.g. whs_code, ocr_code, etc.)');
                $table->string('name', 255)->nullable()->comment('Optional label/name of the entity');
                $table->timestamps();

                $table->unique(['user_id', 'type', 'value'], 'uq_user_org_assignment');
                $table->index(['user_id', 'type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_organization_assignments');
    }
};
