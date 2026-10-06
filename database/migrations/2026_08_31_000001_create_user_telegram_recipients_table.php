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
        Schema::create('user_telegram_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('telegram_chat_id', 100)->index();
            $table->string('recipient_name', 255)->nullable()->comment('Label/nama penerima atau nama grup');
            $table->string('chat_type', 50)->default('private')->comment('private|group|supergroup|channel');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'telegram_chat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_telegram_recipients');
    }
};
