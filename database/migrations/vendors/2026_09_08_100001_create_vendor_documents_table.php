<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        if (!Schema::connection($conn)->hasTable('vendor_documents')) {
            Schema::connection($conn)->create('vendor_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
                $table->string('document_type', 50)->index()->comment('AKTA, NIB, NPWP, SUPPORT, OTHER');
                $table->string('document_number', 100)->nullable()->comment('Nomor identitas dokumen legalitas jika ada');
                $table->text('file_path')->comment('Path penyimpanan berkas pada disk storage');
                $table->string('file_name', 255)->comment('Nama file asli saat diunggah');
                $table->unsignedBigInteger('file_size')->default(0)->comment('Ukuran berkas dalam bytes');
                $table->string('file_mime', 100)->nullable()->comment('Format MIME (e.g. application/pdf, image/png)');
                $table->string('verification_status', 30)->default('PENDING')->index()->comment('PENDING, VALID, INVALID, NEEDS_REVISION');
                $table->unsignedBigInteger('verified_by')->nullable()->comment('ID User legal yang memverifikasi dokumen');
                $table->timestamp('verified_at')->nullable()->comment('Waktu verifikasi dokumen');
                $table->text('verification_notes')->nullable()->comment('Catatan hasil verifikasi dokumen');
                $table->text('notes')->nullable()->comment('Catatan keterangan per dokumen');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('vendor_documents');
    }
};
