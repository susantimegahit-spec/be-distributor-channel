<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration:
     * 1. Adds customer_monthly_order_id to sales_orders (nullable FK to CMOs)
     * 2. Backfills existing records by matching distributor_id + card_code + doc_date
     */
    public function up(): void
    {
        // Step 1: Add the column
        Schema::table('sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_orders', 'customer_monthly_order_id')) {
                $table->unsignedBigInteger('customer_monthly_order_id')
                      ->nullable()
                      ->after('id')
                      ->comment('FK to customer_monthly_orders — the CMO that generated this SO');
            }
        });

        // Step 2: Backfill existing records
        // Strategy: match CMO (status=POSTED) to SO by distributor_id + card_code + doc_date.
        // If multiple CMOs match the same SO on those fields, use doc_total as tiebreaker.
        // Records that cannot be uniquely matched are left as NULL (safe for historical data).

        $soTable  = (new \App\Models\SalesOrder)->getTable();
        $cmoTable = (new \App\Models\CustomerMonthlyOrder)->getTable();

        // Fetch all Sales Orders that don't have a CMO link yet
        $salesOrders = DB::table($soTable)
            ->whereNull('customer_monthly_order_id')
            ->get(['id', 'distributor_id', 'card_code', 'doc_date', 'doc_total', 'order_no']);

        $linked   = 0;
        $skipped  = 0;
        $ambiguous = 0;

        foreach ($salesOrders as $so) {
            // Find matching CMOs — a POSTED CMO with same distributor+card+date
            $candidates = DB::table($cmoTable)
                ->where('distributor_id', $so->distributor_id)
                ->where('card_code', $so->card_code)
                ->where('doc_date', $so->doc_date)
                ->where('status', 'POSTED')
                ->get(['id', 'doc_total', 'order_no']);

            if ($candidates->isEmpty()) {
                $skipped++;
                continue;
            }

            // Unique match — use it directly
            if ($candidates->count() === 1) {
                DB::table($soTable)
                    ->where('id', $so->id)
                    ->update(['customer_monthly_order_id' => $candidates->first()->id]);
                $linked++;
                continue;
            }

            // Multiple candidates — try to narrow down by doc_total
            $byTotal = $candidates->filter(function ($cmo) use ($so) {
                return abs((float)$cmo->doc_total - (float)$so->doc_total) < 0.01;
            });

            if ($byTotal->count() === 1) {
                DB::table($soTable)
                    ->where('id', $so->id)
                    ->update(['customer_monthly_order_id' => $byTotal->first()->id]);
                $linked++;
            } else {
                // Still ambiguous — leave null, log for manual review
                $ambiguous++;
                Log::warning("[Migration backfill] Ambiguous CMO match for SO #{$so->id} ({$so->order_no}). Candidates: " .
                    $candidates->pluck('order_no')->join(', '));
            }
        }

        Log::info("[Migration backfill] customer_monthly_order_id: linked={$linked}, skipped={$skipped}, ambiguous={$ambiguous}");

        // Optional: print summary to console
        echo PHP_EOL . "[Backfill] customer_monthly_order_id hasil:";
        echo PHP_EOL . "  ✅ Berhasil di-link : {$linked}";
        echo PHP_EOL . "  ⚠️  Tidak ada CMO   : {$skipped}";
        echo PHP_EOL . "  ❌ Ambiguous (null) : {$ambiguous}";
        echo PHP_EOL;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sales_orders', 'customer_monthly_order_id')) {
                $table->dropColumn('customer_monthly_order_id');
            }
        });
    }
};
