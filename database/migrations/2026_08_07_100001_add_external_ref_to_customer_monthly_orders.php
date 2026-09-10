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
        if (Schema::hasTable('customer_monthly_orders')) {
            Schema::table('customer_monthly_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_monthly_orders', 'distributor_ref_no')) {
                    $table->string('distributor_ref_no', 100)->nullable()->after('po_number');
                    $table->index(['distributor_id', 'distributor_ref_no']);
                }
                if (!Schema::hasColumn('customer_monthly_orders', 'created_via')) {
                    $table->string('created_via', 50)->default('PORTAL_UI')->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('customer_monthly_orders')) {
            Schema::table('customer_monthly_orders', function (Blueprint $table) {
                if (Schema::hasColumn('customer_monthly_orders', 'distributor_ref_no')) {
                    $table->dropIndex(['distributor_id', 'distributor_ref_no']);
                    $table->dropColumn('distributor_ref_no');
                }
                if (Schema::hasColumn('customer_monthly_orders', 'created_via')) {
                    $table->dropColumn('created_via');
                }
            });
        }
    }
};
