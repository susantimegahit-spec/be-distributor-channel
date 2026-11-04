<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        if (Schema::connection($conn)->hasTable('loading_rate_types')) {
            Schema::connection($conn)->table('loading_rate_types', function (Blueprint $table) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('loading_rate_types', 'start_time')) {
                    $table->time('start_time')->nullable()->after('description')->comment('Jam mulai berlaku (misal 16:30:00)');
                }
                if (!Schema::connection($conn)->hasColumn('loading_rate_types', 'end_time')) {
                    $table->time('end_time')->nullable()->after('start_time')->comment('Jam selesai berlaku (misal 02:00:00)');
                }
            });

            // Update default seed times for existing records if empty
            DB::connection($conn)->table('loading_rate_types')
                ->where('code', 'REGULAR')
                ->whereNull('start_time')
                ->update([
                    'start_time' => '08:00:00',
                    'end_time'   => '16:30:00',
                ]);

            DB::connection($conn)->table('loading_rate_types')
                ->where('code', 'OVERTIME')
                ->whereNull('start_time')
                ->update([
                    'start_time' => '16:30:00',
                    'end_time'   => '02:00:00',
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->getConnection();

        if (Schema::connection($conn)->hasTable('loading_rate_types')) {
            Schema::connection($conn)->table('loading_rate_types', function (Blueprint $table) use ($conn) {
                if (Schema::connection($conn)->hasColumn('loading_rate_types', 'end_time')) {
                    $table->dropColumn('end_time');
                }
                if (Schema::connection($conn)->hasColumn('loading_rate_types', 'start_time')) {
                    $table->dropColumn('start_time');
                }
            });
        }
    }
};
