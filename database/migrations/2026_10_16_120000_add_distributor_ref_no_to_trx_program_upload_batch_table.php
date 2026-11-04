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
        Schema::table('trx_program_upload_batch', function (Blueprint $table) {
            $table->string('distributor_ref_no', 100)->nullable()->after('uploaded_by')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_program_upload_batch', function (Blueprint $table) {
            $table->dropIndex(['distributor_ref_no']);
            $table->dropColumn('distributor_ref_no');
        });
    }
};
