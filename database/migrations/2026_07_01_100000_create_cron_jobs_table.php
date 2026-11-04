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
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('command')->unique();
            $table->string('expression')->default('*/15 * * * *');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable();
            $table->timestamps();
        });

        // Seed initial cronjob
        \DB::table('cron_jobs')->insert([
            'name' => 'Sync Status Order SAP',
            'command' => 'sap:sync-order-status',
            'expression' => '*/15 * * * *',
            'description' => 'Sinkronisasi status order dari SAP ke distributor channel.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
