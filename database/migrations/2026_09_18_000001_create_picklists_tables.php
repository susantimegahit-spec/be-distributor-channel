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
        if (!Schema::hasTable('picklists')) {
            Schema::create('picklists', function (Blueprint $table) {
                $table->id();
                $table->string('picklist_no', 50)->unique()->comment('Unique picklist number, e.g., PKL-202609-0001');
                $table->string('shipping_type', 20)->index()->comment('internal, external, pickup');
                $table->string('status', 20)->default('OPEN')->index()->comment('OPEN, COMPLETED, CANCELLED');
                $table->date('posting_date')->comment('Picklist posting date');
                $table->date('due_date')->comment('Picklist delivery due date');
                $table->string('delivery_order_no', 50)->nullable()->comment('Delivery order number if assigned');
                $table->decimal('total_weight_limit', 18, 4)->nullable()->comment('Total vehicle capacity limit in kg');
                $table->decimal('total_weight', 18, 4)->default(0)->comment('Aggregated actual weight of picked items in kg');
                $table->text('comments')->nullable()->comment('Picking or delivery instructions');

                // Internal fleet specific fields
                $table->string('license_plate', 50)->nullable()->index()->comment('License plate number (vehicle)');
                $table->string('driver_name', 100)->nullable()->comment('Driver name');
                $table->string('checker_name', 100)->nullable()->comment('Checker name');

                // External shipping specific fields
                $table->unsignedBigInteger('expedition_id')->nullable()->index()->comment('Selected expedition vendor ID');
                $table->string('expedition_name', 150)->nullable()->comment('Selected expedition vendor name');
                $table->unsignedBigInteger('expedition_rate_id')->nullable()->index()->comment('Selected expedition rate ID');
                $table->string('service_type', 50)->nullable()->comment('Expedition service type (e.g., Regular, Cargo)');
                $table->decimal('estimated_cost', 18, 2)->nullable()->comment('Estimated shipping cost from selected rate');

                // Audit fields
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('picklist_items')) {
            Schema::create('picklist_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('picklist_id')->constrained('picklists')->onDelete('cascade');
                $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
                $table->unsignedBigInteger('sales_order_detail_id')->nullable()->index()->comment('Reference to sales_order_details.id');
                $table->string('item_code', 50)->index()->comment('Item SKU code');
                $table->string('item_name', 255)->comment('Item description');
                $table->string('whs_code', 50)->nullable()->index()->comment('Warehouse code');
                $table->string('unit_msr', 20)->nullable()->comment('Sales unit of measurement');
                $table->decimal('ordered_qty', 18, 4)->default(0)->comment('Ordered quantity on Sales Order line');
                $table->decimal('pick_qty', 18, 4)->default(0)->comment('Quantity to be picked');
                $table->decimal('unit_weight', 18, 4)->default(0)->comment('Weight per unit in kg');
                $table->decimal('total_weight', 18, 4)->default(0)->comment('pick_qty * unit_weight in kg');
                $table->timestamps();

                $table->index(['picklist_id', 'sales_order_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('picklist_items');
        Schema::dropIfExists('picklists');
    }
};
