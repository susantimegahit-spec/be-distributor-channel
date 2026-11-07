<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'pgsql_production';

    public function getConnection()
    {
        $conn = $this->connection;
        if (Schema::connection($conn)->hasTable('production_receipt_items')) {
            return $conn;
        }
        if (config('database.default') === 'sqlite') {
            return 'sqlite';
        }
        return $conn;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conn = $this->getConnection();

        if (Schema::connection($conn)->hasTable('production_receipt_items')) {
            Schema::connection($conn)->table('production_receipt_items', function (Blueprint $table) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('production_receipt_items', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('line_num')->comment('Urutan baris centang/pilihan user');
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

        if (Schema::connection($conn)->hasTable('production_receipt_items')) {
            Schema::connection($conn)->table('production_receipt_items', function (Blueprint $table) use ($conn) {
                if (Schema::connection($conn)->hasColumn('production_receipt_items', 'sort_order')) {
                    $table->dropColumn('sort_order');
                }
            });
        }
    }
};

