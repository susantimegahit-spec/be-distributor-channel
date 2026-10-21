<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $driver = DB::connection($conn)->getDriverName();

        // 1. Ensure ekspedisi schema exists in postgres
        if ($driver !== 'sqlite') {
            try {
                $schemaExists = DB::select("SELECT schema_name FROM information_schema.schemata WHERE schema_name = 'ekspedisi'");
                if (empty($schemaExists)) {
                    DB::statement('CREATE SCHEMA ekspedisi');
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to auto-create schema 'ekspedisi': " . $e->getMessage());
            }
        }

        // 2. Table: loading_rate_types (Jenis perhitungan: REGULAR / OVERTIME)
        if (!Schema::connection($conn)->hasTable('loading_rate_types')) {
            Schema::connection($conn)->create('loading_rate_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique()->comment('Calculation type code: REGULAR, OVERTIME');
                $table->string('name', 100)->comment('Display name: Regular, Overtime / Lembur');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed default types
            DB::connection($conn)->table('loading_rate_types')->insert([
                [
                    'code'        => 'REGULAR',
                    'name'        => 'Regular',
                    'description' => 'Ongkos angkut kuli jam kerja reguler',
                    'is_active'   => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ],
                [
                    'code'        => 'OVERTIME',
                    'name'        => 'Overtime',
                    'description' => 'Ongkos angkut kuli lembur',
                    'is_active'   => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ],
            ]);
        }

        // 3. Table: loading_rates (Master data tarif ongkos angkut)
        if (!Schema::connection($conn)->hasTable('loading_rates')) {
            Schema::connection($conn)->create('loading_rates', function (Blueprint $table) use ($driver) {
                $table->id();
                $table->string('item_code', 50)->index()->comment('Kode Item');
                $table->string('item_name', 255)->comment('Nama Item');
                $table->unsignedBigInteger('rate_type_id')->comment('ID Jenis Perhitungan');
                $table->string('rate_type', 50)->comment('Type rate: REGULAR, OVERTIME');
                $table->string('uom', 20)->nullable()->comment('Unit of Measure (Uom)');
                $table->decimal('rate', 15, 2)->default(0)->comment('Biaya ongkos angkut per satuan');
                $table->string('status', 20)->default('ACTIVE')->comment('Status: ACTIVE, INACTIVE');
                $table->text('remarks')->nullable()->comment('Catatan tambahan');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['item_code', 'rate_type'], 'loading_rates_item_rate_type_unique');
                $table->foreign('rate_type_id')->references('id')->on('loading_rate_types')->onDelete('cascade');

                if ($driver !== 'sqlite') {
                    $table->foreign('created_by')->references('id')->on('public.users')->onDelete('set null');
                    $table->foreign('updated_by')->references('id')->on('public.users')->onDelete('set null');
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
        Schema::connection($conn)->dropIfExists('loading_rates');
        Schema::connection($conn)->dropIfExists('loading_rate_types');
    }
};
