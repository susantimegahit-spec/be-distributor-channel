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
        if (!Schema::hasTable('document_approval_histories')) {
            Schema::create('document_approval_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_approval_id')->constrained('document_approvals')->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->string('user_name', 150)->nullable();
                $table->string('user_role', 100)->nullable();
                $table->integer('level')->default(1);
                $table->string('stage_name', 100)->nullable(); // e.g. "Review Purchasing Manager", "Approval Direktur"
                $table->string('action', 50); // SUBMIT, APPROVE, REJECT, REVISE, CANCEL
                $table->text('notes')->nullable();
                $table->json('payload_snapshot')->nullable(); // snapshot of document values at time of action
                $table->timestamp('action_at')->useCurrent();
                $table->timestamps();

                $table->index(['document_approval_id', 'level']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_approval_histories');
    }
};
