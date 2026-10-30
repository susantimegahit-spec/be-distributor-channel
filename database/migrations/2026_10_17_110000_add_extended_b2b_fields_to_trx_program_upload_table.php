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
        // 1. Add depo to trx_program_upload_batch if batch-level depo is provided
        Schema::table('trx_program_upload_batch', function (Blueprint $table) {
            if (!Schema::hasColumn('trx_program_upload_batch', 'depo')) {
                $table->string('depo', 100)->nullable()->after('distributor_ref_no');
            }
        });

        // 2. Add extended B2B fields to trx_program_upload
        Schema::table('trx_program_upload', function (Blueprint $table) {
            if (!Schema::hasColumn('trx_program_upload', 'depo')) {
                $table->string('depo', 100)->nullable()->after('customer_name');
            }
            if (!Schema::hasColumn('trx_program_upload', 'distributor_customer_code')) {
                $table->string('distributor_customer_code', 100)->nullable()->after('depo');
            }
            if (!Schema::hasColumn('trx_program_upload', 'distributor_customer_name')) {
                $table->string('distributor_customer_name', 255)->nullable()->after('distributor_customer_code');
            }
            if (!Schema::hasColumn('trx_program_upload', 'address')) {
                $table->text('address')->nullable()->after('distributor_customer_name');
            }
            if (!Schema::hasColumn('trx_program_upload', 'city')) {
                $table->string('city', 100)->nullable()->after('address');
            }
            if (!Schema::hasColumn('trx_program_upload', 'district')) {
                $table->string('district', 100)->nullable()->after('city');
            }
            if (!Schema::hasColumn('trx_program_upload', 'salesman_name')) {
                $table->string('salesman_name', 100)->nullable()->after('district');
            }
            if (!Schema::hasColumn('trx_program_upload', 'item_category')) {
                $table->string('item_category', 100)->nullable()->after('item_name');
            }
            if (!Schema::hasColumn('trx_program_upload', 'transaction_type')) {
                $table->string('transaction_type', 100)->nullable()->after('customer_type');
            }
            if (!Schema::hasColumn('trx_program_upload', 'channel')) {
                $table->string('channel', 50)->nullable()->after('transaction_type');
            }
            if (!Schema::hasColumn('trx_program_upload', 'invoice_no')) {
                $table->string('invoice_no', 100)->nullable()->after('channel')->index();
            }
            if (!Schema::hasColumn('trx_program_upload', 'dpp')) {
                $table->decimal('dpp', 18, 2)->nullable()->after('sell_price_per_kg');
            }
            if (!Schema::hasColumn('trx_program_upload', 'diskon_1')) {
                $table->decimal('diskon_1', 18, 2)->nullable()->after('dpp');
            }
            if (!Schema::hasColumn('trx_program_upload', 'diskon_2')) {
                $table->decimal('diskon_2', 18, 2)->nullable()->after('diskon_1');
            }
            if (!Schema::hasColumn('trx_program_upload', 'diskon_3')) {
                $table->decimal('diskon_3', 18, 2)->nullable()->after('diskon_2');
            }
            if (!Schema::hasColumn('trx_program_upload', 'diskon_4')) {
                $table->decimal('diskon_4', 18, 2)->nullable()->after('diskon_3');
            }
            if (!Schema::hasColumn('trx_program_upload', 'diskon_5')) {
                $table->decimal('diskon_5', 18, 2)->nullable()->after('diskon_4');
            }
            if (!Schema::hasColumn('trx_program_upload', 'ppn')) {
                $table->decimal('ppn', 18, 2)->nullable()->after('diskon_5');
            }
            if (!Schema::hasColumn('trx_program_upload', 'net')) {
                $table->decimal('net', 18, 2)->nullable()->after('ppn');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_program_upload_batch', function (Blueprint $table) {
            if (Schema::hasColumn('trx_program_upload_batch', 'depo')) {
                $table->dropColumn('depo');
            }
        });

        Schema::table('trx_program_upload', function (Blueprint $table) {
            $columnsToDrop = [
                'depo',
                'distributor_customer_code',
                'distributor_customer_name',
                'address',
                'city',
                'district',
                'salesman_name',
                'item_category',
                'transaction_type',
                'channel',
                'invoice_no',
                'dpp',
                'diskon_1',
                'diskon_2',
                'diskon_3',
                'diskon_4',
                'diskon_5',
                'ppn',
                'net',
            ];

            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('trx_program_upload', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
