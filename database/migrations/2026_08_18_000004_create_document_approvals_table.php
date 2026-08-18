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
        if (!Schema::hasTable('document_approvals')) {
            Schema::create('document_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_type_id')->constrained('document_types');
                $table->integer('sap_object_type');
                $table->bigInteger('sap_doc_entry');
                $table->string('sap_doc_num', 100);
                $table->foreignId('requester_id')->nullable()->constrained('users');
                $table->string('requester_name', 150)->nullable();
                $table->string('status', 50)->default('PENDING'); // DRAFT, PENDING, APPROVED, REJECTED, REVISED, CANCELLED
                $table->integer('current_level')->default(1);
                $table->integer('max_level')->default(1);
                $table->date('doc_date')->nullable();
                $table->date('doc_due_date')->nullable();
                $table->decimal('total_amount', 18, 4)->default(0);
                $table->string('currency', 10)->default('IDR');
                $table->text('notes')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();

                $table->index(['sap_object_type', 'sap_doc_entry']);
                $table->index(['status', 'current_level']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_approvals');
    }
};
