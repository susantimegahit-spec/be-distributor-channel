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
        if (Schema::hasTable('purchase_requests')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_requests', 'req_date')) {
                    $table->date('req_date')->nullable()->after('req_type');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_requests')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_requests', 'req_date')) {
                    $table->dropColumn('req_date');
                }
            });
        }
    }
};
