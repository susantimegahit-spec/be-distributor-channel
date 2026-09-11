<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'pgsql_vendor';

    public function getConnection()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : $this->connection;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conn = $this->getConnection();
        $driver = DB::connection($conn)->getDriverName();
        $schemaExists = $driver === 'sqlite' ? [true] : DB::select("SELECT schema_name FROM information_schema.schemata WHERE schema_name = 'vendor'");

        if (empty($schemaExists) && $driver !== 'sqlite') {
            try {
                DB::statement('CREATE SCHEMA vendor');
            } catch (\Throwable $e) {
                Log::warning("Failed to auto-create schema 'vendor': " . $e->getMessage());
            }
        }

        if (!Schema::connection($conn)->hasTable('vendors')) {
            Schema::connection($conn)->create('vendors', function (Blueprint $table) {
                $table->id();
                $table->string('vendor_code', 50)->unique()->comment('Kode Unik Vendor (e.g. VND-202609-0001)');
                $table->string('vendor_type', 50)->index()->comment('Tipe Vendor: EXPEDITION, DISTRIBUTOR, RAW_MATERIAL, PACKAGING, GENERAL_SUPPLIER');
                $table->string('company_name', 200)->comment('Nama Resmi Perusahaan');
                $table->string('company_email', 150)->index()->comment('Email Resmi Perusahaan');
                $table->string('company_phone', 50)->nullable()->comment('Nomor Telepon Kantor');
                $table->string('company_npwp', 50)->nullable()->comment('Nomor Pokok Wajib Pajak (NPWP) Perusahaan');
                $table->text('address')->nullable()->comment('Alamat Domisili Perusahaan');
                $table->string('village', 100)->nullable()->comment('Desa / Kelurahan');
                $table->string('district', 100)->nullable()->comment('Kecamatan');
                $table->string('city', 100)->nullable()->comment('Kota');
                $table->string('regencies', 100)->nullable()->comment('Kabupaten / Kota');
                $table->string('province', 100)->nullable()->comment('Provinsi');
                $table->string('postal_code', 20)->nullable()->comment('Kode Pos');
                $table->string('pic_name', 150)->comment('Nama PIC / Penanggung Jawab');
                $table->string('pic_phone', 50)->comment('Nomor Handphone / WA PIC');
                $table->string('pic_email', 150)->nullable()->comment('Email Personal PIC');
                $table->boolean('terms_agreed')->default(true)->comment('Persetujuan Syarat & Kebijakan Kemitraan');
                $table->timestamp('terms_agreed_at')->nullable()->comment('Waktu Persetujuan Syarat');
                $table->string('registration_status', 30)->default('PENDING_LEGAL_APPROVAL')->index()->comment('DRAFT, PENDING_LEGAL_APPROVAL, REVISION_REQUIRED, APPROVED, REJECTED');
                $table->string('legal_approval_status', 30)->default('PENDING')->index()->comment('PENDING, IN_REVIEW, APPROVED, REJECTED, REVISION');
                $table->unsignedBigInteger('legal_approved_by')->nullable()->comment('ID User Legal yang menyetujui');
                $table->timestamp('legal_approved_at')->nullable()->comment('Waktu Persetujuan Legal');
                $table->text('legal_notes')->nullable()->comment('Catatan pertimbangan / instruksi revisi / alasan penolakan tim legal');
                $table->string('sap_vendor_code', 50)->nullable()->comment('Kode BP / CardCode Vendor di SAP Business One');
                $table->unsignedBigInteger('expedition_id')->nullable()->comment('Relasi ke master ekspedisi jika tipe vendor EXPEDITION');
                $table->string('distributor_code', 50)->nullable()->comment('Relasi ke code_customer distributor jika tipe vendor DISTRIBUTOR');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('vendors');
    }
};
