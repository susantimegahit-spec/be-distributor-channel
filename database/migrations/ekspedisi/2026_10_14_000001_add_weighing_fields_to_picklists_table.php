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
        if (Schema::connection($conn)->hasTable('picklists')) {
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

        if (Schema::connection($conn)->hasTable('picklists')) {
            Schema::connection($conn)->table('picklists', function (Blueprint $table) use ($conn) {
                // Timbang Masuk (Tare Weighing / Timbang Kosong)
                if (!Schema::connection($conn)->hasColumn('picklists', 'weigh_in_weight')) {
                    $table->decimal('weigh_in_weight', 18, 4)->nullable()->after('total_weight')->comment('Tare weight / berat kosong kendaraan in KG');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'weigh_in_ticket_no')) {
                    $table->string('weigh_in_ticket_no', 50)->nullable()->after('weigh_in_weight')->comment('Weigh-in scale ticket number');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'weigh_in_by')) {
                    $table->string('weigh_in_by', 150)->nullable()->after('weigh_in_ticket_no')->comment('Weigh-in scale operator name');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'weigh_in_at')) {
                    $table->timestamp('weigh_in_at')->nullable()->after('weigh_in_by')->comment('Timestamp of weigh-in scale');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'weigh_in_notes')) {
                    $table->text('weigh_in_notes')->nullable()->after('weigh_in_at')->comment('Notes from weigh-in scale');
                }

                // Timbang Keluar (Gross Weighing / Timbang Isi)
                if (!Schema::connection($conn)->hasColumn('picklists', 'weigh_out_weight')) {
                    $table->decimal('weigh_out_weight', 18, 4)->nullable()->after('weigh_in_notes')->comment('Gross weight / berat kotor kendaraan in KG');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'weigh_out_net_weight')) {
                    $table->decimal('weigh_out_net_weight', 18, 4)->nullable()->after('weigh_out_weight')->comment('Calculated Net weight (Gross - Tare) in KG');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'weigh_out_ticket_no')) {
                    $table->string('weigh_out_ticket_no', 50)->nullable()->after('weigh_out_net_weight')->comment('Weigh-out scale ticket number');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'weigh_out_by')) {
                    $table->string('weigh_out_by', 150)->nullable()->after('weigh_out_ticket_no')->comment('Weigh-out scale operator name');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'weigh_out_at')) {
                    $table->timestamp('weigh_out_at')->nullable()->after('weigh_out_by')->comment('Timestamp of weigh-out scale');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'weigh_out_notes')) {
                    $table->text('weigh_out_notes')->nullable()->after('weigh_out_at')->comment('Notes from weigh-out scale');
                }
                if (!Schema::connection($conn)->hasColumn('picklists', 'weight_check_status')) {
                    $table->string('weight_check_status', 30)->nullable()->after('weigh_out_notes')->comment('Check result: sesuai, overload, underload');
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

        if (Schema::connection($conn)->hasTable('picklists')) {
            Schema::connection($conn)->table('picklists', function (Blueprint $table) use ($conn) {
                $cols = [
                    'weigh_in_weight',
                    'weigh_in_ticket_no',
                    'weigh_in_by',
                    'weigh_in_at',
                    'weigh_in_notes',
                    'weigh_out_weight',
                    'weigh_out_net_weight',
                    'weigh_out_ticket_no',
                    'weigh_out_by',
                    'weigh_out_at',
                    'weigh_out_notes',
                    'weight_check_status',
                ];
                foreach ($cols as $col) {
                    if (Schema::connection($conn)->hasColumn('picklists', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
