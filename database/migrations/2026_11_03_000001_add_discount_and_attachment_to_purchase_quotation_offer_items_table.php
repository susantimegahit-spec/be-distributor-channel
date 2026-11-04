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
        if (Schema::hasTable('purchase_quotation_offer_items')) {
            Schema::table('purchase_quotation_offer_items', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_quotation_offer_items', 'discount_type')) {
                    $table->string('discount_type', 20)->nullable()->after('unit_price');
                }
                if (!Schema::hasColumn('purchase_quotation_offer_items', 'discount_value')) {
                    $table->decimal('discount_value', 18, 2)->default(0)->after('discount_type');
                }
                if (!Schema::hasColumn('purchase_quotation_offer_items', 'nominal_disc')) {
                    $table->decimal('nominal_disc', 18, 2)->default(0)->after('discount_value');
                }
                if (!Schema::hasColumn('purchase_quotation_offer_items', 'attachment_path')) {
                    $table->text('attachment_path')->nullable()->after('notes');
                }
                if (!Schema::hasColumn('purchase_quotation_offer_items', 'attachment_name')) {
                    $table->string('attachment_name', 255)->nullable()->after('attachment_path');
                }
                if (!Schema::hasColumn('purchase_quotation_offer_items', 'attachment_mime')) {
                    $table->string('attachment_mime', 100)->nullable()->after('attachment_name');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_quotation_offer_items')) {
            Schema::table('purchase_quotation_offer_items', function (Blueprint $table) {
                $cols = [
                    'discount_type',
                    'discount_value',
                    'nominal_disc',
                    'attachment_path',
                    'attachment_name',
                    'attachment_mime',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('purchase_quotation_offer_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

