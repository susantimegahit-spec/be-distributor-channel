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

    public function getConnection()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : $this->connection;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conn = $this->getConnection();

        if (Schema::connection($conn)->hasTable('picklist_items')) {
            Schema::connection($conn)->table('picklist_items', function (Blueprint $table) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('picklist_items', 'checked_qty')) {
                    $table->decimal('checked_qty', 18, 4)->default(0)->after('pick_qty')->comment('Quantity checked by warehouse checker');
                }
                if (!Schema::connection($conn)->hasColumn('picklist_items', 'is_checked')) {
                    $table->boolean('is_checked')->default(false)->after('checked_qty')->comment('Whether item has been inspected and verified');
                }
                if (!Schema::connection($conn)->hasColumn('picklist_items', 'checked_by_checker')) {
                    $table->string('checked_by_checker', 150)->nullable()->after('is_checked')->comment('Name of checker who verified this item');
                }
                if (!Schema::connection($conn)->hasColumn('picklist_items', 'checked_at')) {
                    $table->timestamp('checked_at')->nullable()->after('checked_by_checker')->comment('Timestamp when item was verified');
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

        if (Schema::connection($conn)->hasTable('picklist_items')) {
            Schema::connection($conn)->table('picklist_items', function (Blueprint $table) use ($conn) {
                $cols = ['checked_qty', 'is_checked', 'checked_by_checker', 'checked_at'];
                foreach ($cols as $col) {
                    if (Schema::connection($conn)->hasColumn('picklist_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
