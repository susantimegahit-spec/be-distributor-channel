<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the database connection for this migration.
     */
    public function getConnection(): string
    {
        return config('database.connections.pgsql_ekspedisi') ? 'pgsql_ekspedisi' : config('database.default');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conn = $this->getConnection();

        if (Schema::connection($conn)->hasTable('picklist_item_label_photos')) {
            Schema::connection($conn)->table('picklist_item_label_photos', function (Blueprint $table) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('picklist_item_label_photos', 'production_date')) {
                    $table->date('production_date')->nullable()->after('caption')->comment('Production date for this label/photo');
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

        if (Schema::connection($conn)->hasTable('picklist_item_label_photos')) {
            Schema::connection($conn)->table('picklist_item_label_photos', function (Blueprint $table) use ($conn) {
                if (Schema::connection($conn)->hasColumn('picklist_item_label_photos', 'production_date')) {
                    $table->dropColumn('production_date');
                }
            });
        }
    }
};
