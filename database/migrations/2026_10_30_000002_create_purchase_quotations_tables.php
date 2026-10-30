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
        Schema::create('purchase_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique()->index();
            $table->string('token', 64)->unique()->index();
            $table->text('link')->nullable();
            $table->string('status', 30)->default('OPEN')->index();
            $table->string('title', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('valid_until')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_quotation_id')->constrained('purchase_quotations')->cascadeOnDelete();
            $table->string('request_id', 50)->nullable();
            $table->string('request_number', 50)->nullable();
            $table->string('line_key', 100)->nullable();
            $table->string('item_code', 50)->index();
            $table->string('item_name', 255);
            $table->decimal('quantity', 18, 4)->default(1);
            $table->string('unit', 50)->nullable();
            $table->string('warehouse', 50)->nullable();
            $table->date('required_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_quotation_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_quotation_id')->constrained('purchase_quotations')->cascadeOnDelete();
            $table->string('company_name', 255);
            $table->string('pic_name', 255);
            $table->string('email', 255);
            $table->string('phone', 50);
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('SUBMITTED')->index();
            $table->decimal('total_price', 18, 2)->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_quotation_offer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_quotation_offer_id')->constrained('purchase_quotation_offers')->cascadeOnDelete();
            $table->foreignId('purchase_quotation_item_id')->nullable()->constrained('purchase_quotation_items')->nullOnDelete();
            $table->string('item_code', 50)->nullable();
            $table->string('item_name', 255)->nullable();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('total_price', 18, 2)->default(0);
            $table->string('lead_time', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_quotation_offer_items');
        Schema::dropIfExists('purchase_quotation_offers');
        Schema::dropIfExists('purchase_quotation_items');
        Schema::dropIfExists('purchase_quotations');
    }
};

