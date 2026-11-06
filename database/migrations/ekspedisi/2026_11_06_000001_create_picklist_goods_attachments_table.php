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
        $conn = $this->connection;
        if (Schema::connection($conn)->hasTable('picklists')) {
            return $conn;
        }
        if (config('database.default') === 'sqlite') {
            return 'sqlite';
        }
        return $conn;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conn = $this->getConnection();
        $isSqlite = Schema::connection($conn)->getConnection()->getDriverName() === 'sqlite';

        if (!Schema::connection($conn)->hasTable('picklist_goods_attachments')) {
            Schema::connection($conn)->create('picklist_goods_attachments', function (Blueprint $table) use ($isSqlite) {
                $table->id();
                $table->unsignedBigInteger('picklist_id')->index();
                $table->string('category', 30)->index()->comment('barang_masuk, barang_keluar');
                $table->string('file_path', 255);
                $table->string('file_name', 255)->nullable();
                $table->unsignedInteger('file_size')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->text('caption')->nullable();
                $table->smallInteger('sort_order')->default(1);
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
        Schema::connection($conn)->dropIfExists('picklist_goods_attachments');
    }
};
