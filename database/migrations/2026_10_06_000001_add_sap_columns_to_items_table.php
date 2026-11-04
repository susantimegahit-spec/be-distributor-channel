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
        if (Schema::hasTable('items')) {
            Schema::table('items', function (Blueprint $table) {
                if (!Schema::hasColumn('items', 'iuom_entry')) {
                    $table->integer('iuom_entry')->nullable()->comment('Inventory UoM Entry (IUoMEntry)');
                }
                if (!Schema::hasColumn('items', 'invntry_uom')) {
                    $table->string('invntry_uom', 50)->nullable()->comment('Inventory UoM (InvntryUom)');
                }
                if (!Schema::hasColumn('items', 'puom_entry')) {
                    $table->integer('puom_entry')->nullable()->comment('Purchase UoM Entry (PUoMEntry)');
                }
                if (!Schema::hasColumn('items', 'pur_pack_msr')) {
                    $table->string('pur_pack_msr', 50)->nullable()->comment('Purchase Package Measure (PurPackMsr)');
                }
                if (!Schema::hasColumn('items', 'prchse_item')) {
                    $table->string('prchse_item', 1)->nullable()->index()->comment('Purchase Item flag (PrchseItem: Y/N)');
                }
                if (!Schema::hasColumn('items', 'sell_item')) {
                    $table->string('sell_item', 1)->nullable()->index()->comment('Sell Item flag (SellItem: Y/N)');
                }
                if (!Schema::hasColumn('items', 'invnt_item')) {
                    $table->string('invnt_item', 1)->nullable()->index()->comment('Inventory Item flag (InvntItem: Y/N)');
                }
                if (!Schema::hasColumn('items', 'itms_grp_cod')) {
                    $table->string('itms_grp_cod', 50)->nullable()->index()->comment('Item Group Code (ItmsGrpCod)');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('items')) {
            Schema::table('items', function (Blueprint $table) {
                $columns = [
                    'iuom_entry',
                    'invntry_uom',
                    'puom_entry',
                    'pur_pack_msr',
                    'prchse_item',
                    'sell_item',
                    'invnt_item',
                    'itms_grp_cod'
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
