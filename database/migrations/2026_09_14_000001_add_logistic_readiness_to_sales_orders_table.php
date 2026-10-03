<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_orders', 'req_due_date')) {
                    $table->date('req_due_date')->nullable()->after('doc_due_date')->comment('Tanggal permintaan pengiriman (disinkronkan dengan doc_due_date)');
                }
                if (!Schema::hasColumn('sales_orders', 'logistic_status')) {
                    $table->string('logistic_status', 50)->default('PENDING')->index()->after('status')->comment('Status kesiapan logistik: PENDING, APPROVED, RESCHEDULE_REQUESTED, RESCHEDULE_APPROVED, RESCHEDULE_REJECTED');
                }
                if (!Schema::hasColumn('sales_orders', 'proposed_delivery_date')) {
                    $table->date('proposed_delivery_date')->nullable()->after('logistic_status')->comment('Usulan tanggal pengiriman baru dari logistik');
                }
                if (!Schema::hasColumn('sales_orders', 'proposed_eta_date')) {
                    $table->date('proposed_eta_date')->nullable()->after('proposed_delivery_date')->comment('Usulan estimasi tanggal tiba baru dari logistik');
                }
                if (!Schema::hasColumn('sales_orders', 'logistic_notes')) {
                    $table->text('logistic_notes')->nullable()->after('proposed_eta_date')->comment('Catatan/alasan dari tim logistik');
                }
                if (!Schema::hasColumn('sales_orders', 'logistic_action_at')) {
                    $table->timestamp('logistic_action_at')->nullable()->after('logistic_notes')->comment('Waktu aksi logistik terakhir');
                }
                if (!Schema::hasColumn('sales_orders', 'logistic_action_by')) {
                    $table->unsignedBigInteger('logistic_action_by')->nullable()->after('logistic_action_at')->comment('User logistik yang melakukan aksi terakhir');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $columns = [
                    'req_due_date',
                    'logistic_status',
                    'proposed_delivery_date',
                    'proposed_eta_date',
                    'logistic_notes',
                    'logistic_action_at',
                    'logistic_action_by',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('sales_orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
