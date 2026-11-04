x<?php

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
                    if (!Schema::hasColumn('document_types', 'icon_path')) {
                        $table->string('icon_path', 255)->nullable()->after('description');
                    }
                    if (!Schema::hasColumn('document_types', 'attachment_path')) {
                        $table->string('attachment_path', 255)->nullable()->after('icon_path');
                    }
                    if (!Schema::hasColumn('document_types', 'attachment_name')) {
                        $table->string('attachment_name', 255)->nullable()->after('attachment_path');
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
                    $cols = ['icon_path', 'attachment_path', 'attachment_name'];
                    foreach ($cols as $col) {
                        if (Schema::hasColumn('document_types', $col)) {
                            $table->dropColumn($col);
                        }
                    }
                });
            }
        }
    };
