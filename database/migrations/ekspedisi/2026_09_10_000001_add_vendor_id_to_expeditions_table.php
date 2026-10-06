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
        if (Schema::connection($this->connection)->hasTable('expeditions')) {
            Schema::connection($this->connection)->table('expeditions', function (Blueprint $table) {
                if (!Schema::connection($this->connection)->hasColumn('expeditions', 'vendor_id')) {
                    $table->unsignedBigInteger('vendor_id')->nullable()->after('id')->index()->comment('ID Vendor Partner di schema vendor.vendors');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('expeditions')) {
            Schema::connection($this->connection)->table('expeditions', function (Blueprint $table) {
                if (Schema::connection($this->connection)->hasColumn('expeditions', 'vendor_id')) {
                    $table->dropColumn('vendor_id');
                }
            });
        }
    }
};
