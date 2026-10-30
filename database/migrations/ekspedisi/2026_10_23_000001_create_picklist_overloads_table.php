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

        if (!Schema::connection($conn)->hasTable('picklist_overloads')) {
            Schema::connection($conn)->create('picklist_overloads', function (Blueprint $table) use ($isSqlite) {
                $table->id();
                $table->unsignedBigInteger('picklist_id')->index();
                $table->string('picklist_no', 50)->index()->comment('Nomor picklist terkait');
                $table->decimal('gross_weight', 18, 4)->default(0)->comment('Berat kotor timbang keluar (KG)');
                $table->decimal('tare_weight', 18, 4)->default(0)->comment('Berat tara timbang masuk (KG)');
                $table->decimal('net_weight', 18, 4)->default(0)->comment('Berat bersih muatan aktual = gross - tare (KG)');
                $table->decimal('weight_limit', 18, 4)->default(0)->comment('Batas kapasitas armada / picklist limit (KG)');
                $table->decimal('overload_weight', 18, 4)->default(0)->comment('Kelebihan muatan = net_weight - weight_limit (KG)');
                $table->string('ticket_no', 50)->nullable()->comment('Nomor tiket jembatan timbang');
                $table->string('operator_name', 150)->nullable()->comment('Nama operator jembatan timbang');
                $table->text('rejection_reason')->nullable()->comment('Alasan reject / catatan kelebihan beban');
                $table->string('status', 30)->default('PENDING_ADJUSTMENT')->index()->comment('Status siklus: PENDING_ADJUSTMENT, UNLOADED, RESOLVED, CANCELLED');
                $table->json('items_snapshot')->nullable()->comment('Snapshot item picklist saat overload');
                $table->text('action_taken')->nullable()->comment('Tindakan koreksi / pembongkaran yang diambil');
                $table->timestamp('weighed_at')->nullable()->comment('Waktu penimbangan timbang keluar');
                $table->timestamp('resolved_at')->nullable()->comment('Waktu penyelesaian overload');
                $table->unsignedBigInteger('resolved_by')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $picklistTable = $isSqlite ? 'picklists' : 'ekspedisi.picklists';
                $userTable = $isSqlite ? 'users' : 'public.users';

                $table->foreign('picklist_id')->references('id')->on($picklistTable)->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on($userTable)->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on($userTable)->onDelete('set null');
                $table->foreign('resolved_by')->references('id')->on($userTable)->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = $this->getConnection();
        Schema::connection($conn)->dropIfExists('picklist_overloads');
    }
};
