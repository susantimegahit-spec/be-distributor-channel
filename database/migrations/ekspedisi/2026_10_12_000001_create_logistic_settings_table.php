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

        if (!Schema::connection($conn)->hasTable('logistic_settings')) {
            Schema::connection($conn)->create('logistic_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique()->index()->comment('Identifier setting logistik (misal: virtual_warehouse_code)');
                $table->text('value')->nullable()->comment('Nilai setting logistik');
                $table->string('description', 255)->nullable()->comment('Keterangan setting logistik');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->getConnection();
        Schema::connection($conn)->dropIfExists('logistic_settings');
    }
};
