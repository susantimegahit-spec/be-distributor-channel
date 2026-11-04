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
        Schema::table('users', function (Blueprint $table) {
            $table->string('whs_code', 100)->nullable()->after('production_code')->comment('Kode Gudang (Warehouse Code)');
            $table->string('ocr_code', 100)->nullable()->after('whs_code')->comment('Dimension 1 SAP / Cost Center');
            $table->string('ocr_code2', 100)->nullable()->after('ocr_code')->comment('Dimension 2 SAP');
            $table->string('ocr_code3', 100)->nullable()->after('ocr_code2')->comment('Dimension 3 SAP');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whs_code', 'ocr_code', 'ocr_code2', 'ocr_code3']);
        });
    }
};
