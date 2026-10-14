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

        if (!Schema::connection($conn)->hasTable('picklist_item_label_photos')) {
            Schema::connection($conn)->create('picklist_item_label_photos', function (Blueprint $table) use ($isSqlite) {
                $table->id();
                $table->unsignedBigInteger('picklist_id')->index();
                $table->unsignedBigInteger('picklist_item_id')->index();
                $table->string('photo_path', 255)->comment('Storage path of label photo');
                $table->string('file_name', 255)->nullable()->comment('Original or generated file name');
                $table->integer('file_size')->nullable()->comment('File size in bytes');
                $table->string('mime_type', 50)->nullable()->comment('Mime type of image');
                $table->text('caption')->nullable()->comment('Optional caption or notes on the label');
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $picklistTable = $isSqlite ? 'picklists' : 'ekspedisi.picklists';
                $picklistItemTable = $isSqlite ? 'picklist_items' : 'ekspedisi.picklist_items';
                $userTable = $isSqlite ? 'users' : 'public.users';

                $table->foreign('picklist_id')->references('id')->on($picklistTable)->onDelete('cascade');
                $table->foreign('picklist_item_id')->references('id')->on($picklistItemTable)->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on($userTable)->onDelete('set null');

                $table->index(['picklist_id', 'picklist_item_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->getConnection();
        Schema::connection($conn)->dropIfExists('picklist_item_label_photos');
    }
};
