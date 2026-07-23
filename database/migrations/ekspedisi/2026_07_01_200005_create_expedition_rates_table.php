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
        if (!Schema::connection($this->connection)->hasTable('expedition_rates')) {
            Schema::connection($this->connection)->create('expedition_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expedition_id')->constrained('ekspedisi.expeditions')->onDelete('cascade');
                $table->unsignedBigInteger('warehouse_id')->nullable()->comment('Gudang Asal');
                $table->unsignedBigInteger('destination_id')->nullable()->comment('Tujuan');
                $table->string('transport_mode', 50)->nullable()->comment('Moda');
                $table->string('service_type', 50)->nullable()->comment('Jenis Layanan');
                $table->decimal('min_tonnage', 12, 2)->default(0)->comment('Tonase Minimal');
                $table->decimal('max_tonnage', 12, 2)->default(0)->comment('Tonase Maksimal');
                $table->decimal('price', 15, 2)->default(0)->comment('Harga');
                $table->integer('eta_days')->nullable()->comment('ETA Hari');
                $table->decimal('min_shipment_qty', 12, 2)->default(0)->comment('Minimal Pengiriman');
                $table->decimal('max_shipment_qty', 12, 2)->default(0)->comment('Maksimal Pengiriman');
                $table->date('valid_from')->nullable()->comment('Berlaku Mulai');
                $table->date('valid_until')->nullable()->comment('Berlaku Sampai');
                $table->string('status', 20)->default('ACTIVE')->comment('Status: ACTIVE, INACTIVE');
                $table->text('remarks')->nullable()->comment('Keterangan');
                $table->string('upload_batch_id', 100)->nullable()->comment('ID Batch Upload');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('public.warehouses')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('public.users')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('public.users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('expedition_rates');
    }
};
