<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'pgsql_ekspedisi';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('warehouse_origins')) {
            Schema::connection($this->connection)->create('warehouse_origins', function (Blueprint $table) {
                $table->id();
                $table->string('whs_name_origin', 255);
                $table->string('whs_code', 50);
                $table->string('whs_name', 255);
                $table->text('street')->nullable();
                $table->string('status', 20)->default('ACTIVE');
                $table->foreignId('created_by')->nullable()->constrained('public.users')->onDelete('set null');
                $table->foreignId('updated_by')->nullable()->constrained('public.users')->onDelete('set null');
                $table->timestamps();

                // Foreign key constraint referencing public.warehouses(whs_code)
                $table->foreign('whs_code')->references('whs_code')->on('public.warehouses')->onDelete('restrict');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('warehouse_origins');
    }
};
