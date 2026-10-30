<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'pgsql_vendor';

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
        if (Schema::connection($conn)->hasTable('vendor_documents') && !Schema::connection($conn)->hasColumn('vendor_documents', 'notes')) {
            Schema::connection($conn)->table('vendor_documents', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('verification_notes')->comment('Catatan keterangan per dokumen');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->getConnection();
        if (Schema::connection($conn)->hasTable('vendor_documents') && Schema::connection($conn)->hasColumn('vendor_documents', 'notes')) {
            Schema::connection($conn)->table('vendor_documents', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
