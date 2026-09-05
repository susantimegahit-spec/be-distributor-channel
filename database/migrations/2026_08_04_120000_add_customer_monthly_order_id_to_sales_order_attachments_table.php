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
        Schema::table('sales_order_attachments', function (Blueprint $table) {
            // Make sales_order_id nullable so CMO attachments can exist without a SalesOrder record
            $table->unsignedBigInteger('sales_order_id')->nullable()->change();

            // Add customer_monthly_order_id column
            if (!Schema::hasColumn('sales_order_attachments', 'customer_monthly_order_id')) {
                $table->foreignId('customer_monthly_order_id')
                    ->nullable()
                    ->after('sales_order_id')
                    ->constrained('customer_monthly_orders')
                    ->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_attachments', 'customer_monthly_order_id')) {
                $table->dropForeign(['customer_monthly_order_id']);
                $table->dropColumn('customer_monthly_order_id');
            }
        });
    }
};
