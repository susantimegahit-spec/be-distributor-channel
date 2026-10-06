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
        Schema::table('customer_shiptos', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_shiptos', 'alias')) {
                $table->string('alias', 255)->nullable()->after('name')->comment('Alias Destination');
            }
            if (!Schema::hasColumn('customer_shiptos', 'transport_mode')) {
                $table->string('transport_mode', 10)->nullable()->after('street')->comment('D: Darat, L: Laut, U: Udara');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_shiptos', function (Blueprint $table) {
            if (Schema::hasColumn('customer_shiptos', 'alias')) {
                $table->dropColumn('alias');
            }
            if (Schema::hasColumn('customer_shiptos', 'transport_mode')) {
                $table->dropColumn('transport_mode');
            }
        });
    }
};
