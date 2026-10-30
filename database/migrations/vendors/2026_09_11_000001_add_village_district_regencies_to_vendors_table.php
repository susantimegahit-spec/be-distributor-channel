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
        if (Schema::connection($conn)->hasTable('vendors')) {
            Schema::connection($conn)->table('vendors', function (Blueprint $table) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('vendors', 'village')) {
                    $table->string('village', 100)->nullable()->after('address')->comment('Desa / Kelurahan');
                }
                if (!Schema::connection($conn)->hasColumn('vendors', 'district')) {
                    $table->string('district', 100)->nullable()->after('village')->comment('Kecamatan');
                }
                if (!Schema::connection($conn)->hasColumn('vendors', 'regencies')) {
                    $table->string('regencies', 100)->nullable()->after('city')->comment('Kabupaten / Kota');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->getConnection();
        if (Schema::connection($conn)->hasTable('vendors')) {
            Schema::connection($conn)->table('vendors', function (Blueprint $table) use ($conn) {
                $cols = ['village', 'district', 'regencies'];
                foreach ($cols as $col) {
                    if (Schema::connection($conn)->hasColumn('vendors', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
