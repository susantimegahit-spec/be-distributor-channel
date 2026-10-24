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
        if (!Schema::hasTable('distributor_api_keys')) {
            Schema::create('distributor_api_keys', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('distributor_id');
                $table->string('name', 150);
                $table->string('key_prefix', 10);
                $table->string('api_key_hash', 64)->unique();
                $table->text('allowed_ips')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->foreign('distributor_id')
                    ->references('id')
                    ->on('distributors')
                    ->onDelete('cascade');

                $table->index(['api_key_hash', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributor_api_keys');
    }
};
