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

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conn = $this->connection;

        if (Schema::connection($conn)->hasTable('picklists')) {
            Schema::connection($conn)->table('picklists', function (Blueprint $table) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('picklists', 'series')) {
                    $table->integer('series')->nullable()->after('status')->comment('SAP Document Series for DO');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'series_name')) {
                    $table->string('series_name', 100)->nullable()->after('series')->comment('SAP Document Series Name for DO');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'seal_number')) {
                    $table->string('seal_number', 100)->nullable()->after('checker_name')->comment('Seal number (Noseal) for delivery container/truck');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->connection;

        if (Schema::connection($conn)->hasTable('picklists')) {
            Schema::connection($conn)->table('picklists', function (Blueprint $table) use ($conn) {
                $cols = ['series', 'series_name', 'seal_number'];
                foreach ($cols as $col) {
                    if (Schema::connection($conn)->hasColumn('picklists', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
