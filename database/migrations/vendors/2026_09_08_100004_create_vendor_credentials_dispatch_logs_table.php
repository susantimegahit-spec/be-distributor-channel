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
        if (!Schema::connection($conn)->hasTable('vendor_credentials_dispatch_logs')) {
            Schema::connection($conn)->create('vendor_credentials_dispatch_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_user_id')->constrained('vendor_users')->onDelete('cascade');
                $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
                $table->string('recipient_email', 150)->comment('Alamat email penerima kredensial');
                $table->string('dispatch_channel', 30)->default('EMAIL')->comment('Channel pengiriman: EMAIL, WHATSAPP');
                $table->string('dispatch_status', 30)->default('SENT')->comment('Status: QUEUED, SENT, FAILED');
                $table->timestamp('sent_at')->nullable()->comment('Waktu pengiriman sukses');
                $table->text('error_message')->nullable()->comment('Pesan error jika pengiriman gagal');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('vendor_credentials_dispatch_logs');
    }
};
