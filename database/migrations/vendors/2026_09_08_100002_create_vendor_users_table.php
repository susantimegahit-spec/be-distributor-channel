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
        if (!Schema::connection($conn)->hasTable('vendor_users')) {
            Schema::connection($conn)->create('vendor_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
                $table->string('name', 150)->comment('Nama User Vendor');
                $table->string('email', 150)->unique()->comment('Email Login Vendor');
                $table->string('password', 255)->comment('Password Akun Vendor Terenkripsi');
                $table->string('role', 50)->default('VENDOR_ADMIN')->comment('Role: VENDOR_ADMIN, VENDOR_OPERATOR, VENDOR_FINANCE');
                $table->string('status', 30)->default('ACTIVE')->comment('Status: ACTIVE, INACTIVE, BLOCKED');
                $table->boolean('must_change_password')->default(true)->comment('Wajib mengganti password pada login pertama');
                $table->timestamp('initial_password_sent_at')->nullable()->comment('Waktu pengiriman kredensial awal');
                $table->timestamp('last_login_at')->nullable()->comment('Waktu login terakhir');
                $table->string('last_login_ip', 45)->nullable()->comment('Alamat IP login terakhir');
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('vendor_users');
    }
};
