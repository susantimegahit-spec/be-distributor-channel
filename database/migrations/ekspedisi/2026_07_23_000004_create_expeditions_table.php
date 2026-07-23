<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        DB::statement('CREATE SCHEMA IF NOT EXISTS ekspedisi');

        if (!Schema::connection($this->connection)->hasTable('expeditions')) {
            Schema::connection($this->connection)->create('expeditions', function (Blueprint $table) {
                $table->id();
                $table->string('expedition_code', 50)->unique()->comment('Kode Ekspedisi');
                $table->string('expedition_name', 150)->comment('Nama Ekspedisi');
                $table->text('address')->nullable()->comment('Alamat');
                $table->string('city', 100)->nullable()->comment('Kota');
                $table->string('province', 100)->nullable()->comment('Provinsi');
                $table->string('postal_code', 20)->nullable()->comment('Kode Pos');
                $table->string('pic_name', 100)->nullable()->comment('PIC');
                $table->string('pic_phone', 30)->nullable()->comment('No HP PIC');
                $table->string('email', 100)->nullable()->comment('Email');
                $table->string('npwp', 50)->nullable()->comment('NPWP');
                $table->string('vehicle_type', 50)->nullable()->comment('Jenis Kendaraan');
                $table->string('transport_mode', 50)->nullable()->comment('Moda Pengiriman');
                $table->string('status', 20)->default('ACTIVE')->comment('Status: ACTIVE, INACTIVE');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

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
        Schema::connection($this->connection)->dropIfExists('expeditions');
    }
};
