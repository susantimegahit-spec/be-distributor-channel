<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'pgsql_production';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('production_items')) {
            Schema::connection($this->connection)->create('production_items', function (Blueprint $table) {
                $table->id();
                $table->string('item_code', 50)->unique()->comment('Item Code (ItemCode)');
                $table->string('item_name', 255)->comment('Item Name (ItemName)');
                $table->integer('i_uom_entry')->nullable()->comment('UoM Entry (IUoMEntry)');
                $table->string('invntry_uom', 50)->nullable()->comment('Inventory UoM (InvntryUom)');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('production_items');
    }
};
