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
        if (Schema::connection($conn)->hasTable('vendors') && !Schema::connection($conn)->hasColumn('vendors', 'company_npwp')) {
            Schema::connection($conn)->table('vendors', function (Blueprint $table) {
                $table->string('company_npwp', 50)->nullable()->after('company_phone')->comment('Nomor Pokok Wajib Pajak (NPWP) Perusahaan');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->getConnection();
        if (Schema::connection($conn)->hasTable('vendors') && Schema::connection($conn)->hasColumn('vendors', 'company_npwp')) {
            Schema::connection($conn)->table('vendors', function (Blueprint $table) {
                $table->dropColumn('company_npwp');
            });
        }
    }
};
