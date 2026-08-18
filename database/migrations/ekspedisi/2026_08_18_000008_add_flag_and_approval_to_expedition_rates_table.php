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
        if (Schema::connection($this->connection)->hasTable('expedition_rates')) {
            Schema::connection($this->connection)->table('expedition_rates', function (Blueprint $table) {
                if (!Schema::connection($this->connection)->hasColumn('expedition_rates', 'flag')) {
                    $table->boolean('flag')->default(false)->after('status')->comment('Flag Persetujuan Atasan (false: Pending/Draft, true: Approved/Aktif)');
                }
                if (!Schema::connection($this->connection)->hasColumn('expedition_rates', 'approval_status')) {
                    $table->string('approval_status', 20)->default('PENDING')->after('flag')->comment('Status Approval: PENDING, APPROVED, REJECTED');
                }
                if (!Schema::connection($this->connection)->hasColumn('expedition_rates', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('approval_status')->comment('ID Atasan yang menyetujui');
                }
                if (!Schema::connection($this->connection)->hasColumn('expedition_rates', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by')->comment('Waktu persetujuan atasan');
                }
                if (!Schema::connection($this->connection)->hasColumn('expedition_rates', 'approval_notes')) {
                    $table->text('approval_notes')->nullable()->after('approved_at')->comment('Catatan approval atasan');
                }

                $table->foreign('approved_by')->references('id')->on('public.users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('expedition_rates')) {
            Schema::connection($this->connection)->table('expedition_rates', function (Blueprint $table) {
                $cols = ['flag', 'approval_status', 'approved_by', 'approved_at', 'approval_notes'];
                foreach ($cols as $col) {
                    if (Schema::connection($this->connection)->hasColumn('expedition_rates', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
