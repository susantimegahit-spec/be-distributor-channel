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
        // 1. Fix key_prefix column length (10 → 30) jika belum
        if (Schema::hasTable('distributor_api_keys')) {
            Schema::table('distributor_api_keys', function (Blueprint $table) {
                if (Schema::hasColumn('distributor_api_keys', 'key_prefix')) {
                    $table->string('key_prefix', 30)->change();
                }
                // Make distributor_id nullable (single distributor optional, multi via pivot)
                if (Schema::hasColumn('distributor_api_keys', 'distributor_id')) {
                    $table->unsignedBigInteger('distributor_id')->nullable()->change();
                }
                // Add optional company/group name for multi-distributor context
                if (!Schema::hasColumn('distributor_api_keys', 'company_name')) {
                    $table->string('company_name', 200)->nullable()->after('name');
                }
            });
        }

        // 2. Buat pivot table distributor_api_key_distributor (many-to-many)
        if (!Schema::hasTable('distributor_api_key_distributor')) {
            Schema::create('distributor_api_key_distributor', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('distributor_api_key_id');
                $table->unsignedBigInteger('distributor_id');
                $table->timestamps();

                $table->foreign('distributor_api_key_id')
                    ->references('id')
                    ->on('distributor_api_keys')
                    ->onDelete('cascade');

                $table->foreign('distributor_id')
                    ->references('id')
                    ->on('distributors')
                    ->onDelete('cascade');

                $table->unique(['distributor_api_key_id', 'distributor_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributor_api_key_distributor');

        if (Schema::hasTable('distributor_api_keys')) {
            Schema::table('distributor_api_keys', function (Blueprint $table) {
                $table->string('key_prefix', 10)->change();
                $table->unsignedBigInteger('distributor_id')->nullable(false)->change();
                if (Schema::hasColumn('distributor_api_keys', 'company_name')) {
                    $table->dropColumn('company_name');
                }
            });
        }
    }
};
