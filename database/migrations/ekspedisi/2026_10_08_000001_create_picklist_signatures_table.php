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
        $isSqlite = Schema::connection($conn)->getConnection()->getDriverName() === 'sqlite';

        if (!Schema::connection($conn)->hasTable('picklist_signatures')) {
            Schema::connection($conn)->create('picklist_signatures', function (Blueprint $table) use ($isSqlite) {
                $table->id();
                $table->unsignedBigInteger('picklist_id')->index();
                $table->string('signer_type', 30)->index()->comment('checker, driver, supervisor');
                $table->string('signer_role_title', 50)->nullable()->comment('Label e.g. Checker 1, Checker 2, Supir');
                $table->string('signer_name', 150);
                $table->string('signature_path', 255);
                $table->timestamp('signed_at')->nullable();
                $table->smallInteger('sort_order')->default(1);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $picklistTable = $isSqlite ? 'picklists' : 'ekspedisi.picklists';
                $userTable = $isSqlite ? 'users' : 'public.users';

                $table->foreign('picklist_id')->references('id')->on($picklistTable)->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on($userTable)->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->getConnection();
        Schema::connection($conn)->dropIfExists('picklist_signatures');
    }
};
