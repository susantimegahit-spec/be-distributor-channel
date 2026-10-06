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
        if (!Schema::connection($conn)->hasTable('vendor_approval_histories')) {
            Schema::connection($conn)->create('vendor_approval_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
                $table->string('action', 50)->comment('REGISTRATION_SUBMITTED, REVIEW_STARTED, REVISION_REQUESTED, APPROVED, REJECTED, CREDENTIALS_GENERATED, STATUS_CHANGED');
                $table->string('from_status', 50)->nullable()->comment('Status sebelum aksi');
                $table->string('to_status', 50)->comment('Status sesudah aksi');
                $table->unsignedBigInteger('actor_id')->nullable()->comment('ID User internal pelaku aksi (null jika vendor/sistem)');
                $table->string('actor_name', 150)->comment('Nama pengguna atau sistem pelaku aksi');
                $table->text('notes')->nullable()->comment('Catatan pertimbangan / feedback / alasan penolakan');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('vendor_approval_histories');
    }
};
