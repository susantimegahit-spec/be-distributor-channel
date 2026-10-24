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
        // 1. Departments for mailbox mapping
        if (!Schema::hasTable('mailbox_departments')) {
            Schema::create('mailbox_departments', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->string('color', 20)->default('#3b82f6');
                $table->timestamps();
            });
        }

        // 2. Mailboxes
        if (!Schema::hasTable('mailboxes')) {
            Schema::create('mailboxes', function (Blueprint $table) {
                $table->id();
                $table->string('email', 191)->unique();
                $table->string('user_name', 150)->nullable();
                $table->foreignId('department_id')->nullable()->constrained('mailbox_departments')->onDelete('set null');
                $table->unsignedBigInteger('quota_bytes')->default(0)->comment('0 means unlimited');
                $table->unsignedBigInteger('current_usage_bytes')->default(0);
                $table->decimal('usage_percentage', 5, 2)->default(0.00);
                $table->string('status', 20)->default('SAFE')->index()->comment('SAFE, MONITORING, WARNING, CRITICAL');
                $table->string('domain', 100)->default('susantimegah.com');
                $table->boolean('suspended_incoming')->default(false);
                $table->boolean('suspended_login')->default(false);
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
            });
        }

        // 3. Usage Snapshots (Historical usage for trend & forecast)
        if (!Schema::hasTable('mailbox_usage_snapshots')) {
            Schema::create('mailbox_usage_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mailbox_id')->constrained('mailboxes')->onDelete('cascade');
                $table->unsignedBigInteger('usage_bytes');
                $table->unsignedBigInteger('quota_bytes')->default(0);
                $table->decimal('usage_percentage', 5, 2)->default(0.00);
                $table->timestamp('recorded_at')->index();
                $table->timestamps();

                $table->index(['mailbox_id', 'recorded_at']);
            });
        }

        // 4. Quota Recommendations
        if (!Schema::hasTable('mailbox_quota_recommendations')) {
            Schema::create('mailbox_quota_recommendations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mailbox_id')->constrained('mailboxes')->onDelete('cascade');
                $table->unsignedBigInteger('current_quota_bytes');
                $table->unsignedBigInteger('recommended_quota_bytes');
                $table->unsignedBigInteger('additional_quota_bytes');
                $table->text('reason');
                $table->string('status', 20)->default('PENDING')->index()->comment('PENDING, REVIEWED, APPROVED, DISMISSED');
                $table->bigInteger('growth_rate_bytes_per_day')->default(0);
                $table->integer('estimated_days_to_full')->nullable();
                $table->string('risk_level', 20)->default('LOW')->comment('LOW, MEDIUM, HIGH');
                $table->timestamps();
            });
        }

        // 5. Configurable Settings
        if (!Schema::hasTable('mailbox_settings')) {
            Schema::create('mailbox_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->text('value');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 6. Audit Logs
        if (!Schema::hasTable('mailbox_audit_logs')) {
            Schema::create('mailbox_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 100);
                $table->string('target_type', 100)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailbox_audit_logs');
        Schema::dropIfExists('mailbox_settings');
        Schema::dropIfExists('mailbox_quota_recommendations');
        Schema::dropIfExists('mailbox_usage_snapshots');
        Schema::dropIfExists('mailboxes');
        Schema::dropIfExists('mailbox_departments');
    }
};
