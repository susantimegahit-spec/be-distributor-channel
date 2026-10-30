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

        if (!Schema::connection($conn)->hasTable('picklist_workers')) {
            Schema::connection($conn)->create('picklist_workers', function (Blueprint $table) use ($isSqlite) {
                $table->id();
                $table->unsignedBigInteger('picklist_id')->index();
                $table->string('item_code', 50)->index()->comment('Kode item yang diangkut');
                $table->string('item_name', 255)->nullable()->comment('Nama item');
                $table->string('worker_name', 150)->nullable()->comment('Nama kuli/tenaga kerja (opsional)');
                $table->decimal('qty', 15, 4)->default(0)->comment('Kuantitas / berat yang diangkut');
                $table->string('uom', 20)->nullable()->comment('Satuan (Uom), misal KG, SAK');
                $table->string('rate_type', 50)->default('OVERTIME')->comment('Tipe tarif: OVERTIME, REGULAR');
                $table->decimal('rate', 15, 2)->default(0)->comment('Tarif ongkos kuli lembur per satuan');
                $table->decimal('lembur_fee', 15, 2)->default(0)->comment('Hasil hitungan lembur = qty * rate');
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
        Schema::connection($conn)->dropIfExists('picklist_workers');
    }
};
