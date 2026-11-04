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
        $conn = $this->connection;
        if (Schema::connection($conn)->hasTable('picklist_items')) {
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

        if (Schema::connection($conn)->hasTable('picklist_items')) {
            Schema::connection($conn)->table('picklist_items', function (Blueprint $table) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('picklist_items', 'start_time')) {
                    $table->timestamp('start_time')->nullable()->after('checked_at')->comment('KPI: timestamp when checker starts checking this item');
                }
                if (!Schema::connection($conn)->hasColumn('picklist_items', 'end_time')) {
                    $table->timestamp('end_time')->nullable()->after('start_time')->comment('KPI: timestamp when checker finishes checking this item');
                }
                if (!Schema::connection($conn)->hasColumn('picklist_items', 'production_date')) {
                    $table->date('production_date')->nullable()->after('end_time')->comment('Production date for this item line');
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

        if (Schema::connection($conn)->hasTable('picklist_items')) {
            Schema::connection($conn)->table('picklist_items', function (Blueprint $table) use ($conn) {
                $cols = ['start_time', 'end_time', 'production_date'];
                foreach ($cols as $col) {
                    if (Schema::connection($conn)->hasColumn('picklist_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
