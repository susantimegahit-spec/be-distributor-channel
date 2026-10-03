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
        if (Schema::hasTable('document_types')) {
            Schema::table('document_types', function (Blueprint $table) {
                if (!Schema::hasColumn('document_types', 'sap_object_type')) {
                    $table->integer('sap_object_type')->nullable()->after('name');
                }
                if (!Schema::hasColumn('document_types', 'module')) {
                    $table->string('module', 50)->nullable()->default('Purchasing')->after('sap_object_type');
                }
                if (!Schema::hasColumn('document_types', 'header_source')) {
                    $table->string('header_source', 50)->nullable()->after('module');
                }
                if (!Schema::hasColumn('document_types', 'line_source')) {
                    $table->string('line_source', 50)->nullable()->after('header_source');
                }
                if (!Schema::hasColumn('document_types', 'adapter_class')) {
                    $table->string('adapter_class', 255)->nullable()->after('line_source');
                }
                if (!Schema::hasColumn('document_types', 'description')) {
                    $table->text('description')->nullable()->after('adapter_class');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('document_types')) {
            Schema::table('document_types', function (Blueprint $table) {
                $columns = ['sap_object_type', 'module', 'header_source', 'line_source', 'adapter_class', 'description'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('document_types', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
