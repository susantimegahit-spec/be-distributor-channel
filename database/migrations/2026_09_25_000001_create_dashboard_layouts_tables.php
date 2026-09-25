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
        Schema::create('dashboard_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->unique()->constrained('roles')->onDelete('cascade');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dashboard_layout_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_layout_id')->constrained('dashboard_layouts')->onDelete('cascade');
            $table->unsignedSmallInteger('row_number');
            $table->unsignedSmallInteger('columns');
            $table->timestamps();

            $table->unique(['dashboard_layout_id', 'row_number']);
        });

        Schema::create('dashboard_layout_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_layout_id')->constrained('dashboard_layouts')->onDelete('cascade');
            $table->string('widget_key', 100);
            $table->integer('sort_order');
            $table->unsignedSmallInteger('row_number');
            $table->unsignedSmallInteger('column_number');
            $table->unsignedSmallInteger('column_span');
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->unique(['dashboard_layout_id', 'widget_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_layout_widgets');
        Schema::dropIfExists('dashboard_layout_rows');
        Schema::dropIfExists('dashboard_layouts');
    }
};
