<?php

namespace Database\Seeders;

use App\Models\DocumentApproval;
use App\Models\DocumentApprovalHistory;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DocumentApprovalDummySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'administrator')->first() ?? User::first();
        $sales = User::where('username', 'adminsales')->first() ?? $admin;

        $poType = DocumentType::where('code', 'PO')->first();
        $prType = DocumentType::where('code', 'PR')->first();
        $grpoType = DocumentType::where('code', 'GRPO')->first();

        // -------------------------------------------------------------
        // DUMMY 1: Purchase Order #PO-50001 (Status: PENDING at Level 2, ada History Level 1)
        // -------------------------------------------------------------
        if ($poType) {
            $poApproval = DocumentApproval::updateOrCreate(
                [
                    'document_type_id' => $poType->id,
                    'sap_doc_entry' => 5001,
                ],
                [
                    'sap_object_type' => 22,
                    'sap_doc_num' => 'PO-50001',
                    'requester_id' => $sales?->id,
                    'requester_name' => 'Purchasing Officer Balaraja',
                    'status' => 'PENDING',
                    'current_level' => 2,
                    'max_level' => 3,
                    'doc_date' => now()->subDays(2)->toDateString(),
                    'doc_due_date' => now()->addDays(12)->toDateString(),
                    'total_amount' => 138750000,
                    'currency' => 'IDR',
                    'notes' => 'Pengadaan Bahan Baku Garam Kasar Import Q3',
                    'submitted_at' => now()->subDays(2)->setHour(9)->setMinute(15),
                ]
            );

            // Seed Histories
            DocumentApprovalHistory::where('document_approval_id', $poApproval->id)->delete();

            DocumentApprovalHistory::create([
                'document_approval_id' => $poApproval->id,
                'user_id' => $sales?->id,
                'user_name' => 'Purchasing Officer Balaraja',
                'user_role' => 'Purchasing Staff',
                'level' => 1,
                'stage_name' => 'Submission',
                'action' => 'SUBMIT',
                'notes' => 'Pengajuan PO untuk supplier PT Aneka Kimia Raya Sejahtera',
                'action_at' => now()->subDays(2)->setHour(9)->setMinute(15),
            ]);

            DocumentApprovalHistory::create([
                'document_approval_id' => $poApproval->id,
                'user_id' => $admin?->id,
                'user_name' => 'Supervisor Purchasing',
                'user_role' => 'Spv Purchasing',
                'level' => 1,
                'stage_name' => 'Review Spv Purchasing',
                'action' => 'APPROVE',
                'notes' => 'Spesifikasi teknis & kuota supplier sudah sesuai kuota Q3',
                'action_at' => now()->subDays(1)->setHour(14)->setMinute(30),
            ]);
        }

        // -------------------------------------------------------------
        // DUMMY 2: Purchase Request #PR-40001 (Status: APPROVED, full histories)
        // -------------------------------------------------------------
        if ($prType) {
            $prApproval = DocumentApproval::updateOrCreate(
                [
                    'document_type_id' => $prType->id,
                    'sap_doc_entry' => 4001,
                ],
                [
                    'sap_object_type' => 1470000113,
                    'sap_doc_num' => 'PR-40001',
                    'requester_id' => $sales?->id,
                    'requester_name' => 'Budi Santoso (Plant Manager)',
                    'status' => 'APPROVED',
                    'current_level' => 2,
                    'max_level' => 2,
                    'doc_date' => now()->subDays(4)->toDateString(),
                    'doc_due_date' => now()->addDays(3)->toDateString(),
                    'total_amount' => 75000000,
                    'currency' => 'IDR',
                    'notes' => 'Permintaan Pembelian Bahan Baku Produksi Garam Industri 500 SAK',
                    'submitted_at' => now()->subDays(4)->setHour(8)->setMinute(0),
                    'approved_at' => now()->subDays(3)->setHour(16)->setMinute(45),
                ]
            );

            DocumentApprovalHistory::where('document_approval_id', $prApproval->id)->delete();

            DocumentApprovalHistory::create([
                'document_approval_id' => $prApproval->id,
                'user_id' => $sales?->id,
                'user_name' => 'Budi Santoso',
                'user_role' => 'Plant Manager',
                'level' => 1,
                'stage_name' => 'Submission',
                'action' => 'SUBMIT',
                'notes' => 'Permintaan penambahan stok bahan baku kritis',
                'action_at' => now()->subDays(4)->setHour(8)->setMinute(0),
            ]);

            DocumentApprovalHistory::create([
                'document_approval_id' => $prApproval->id,
                'user_id' => $admin?->id,
                'user_name' => 'Head of Supply Chain',
                'user_role' => 'Manager Supply Chain',
                'level' => 1,
                'stage_name' => 'Review Manager SC',
                'action' => 'APPROVE',
                'notes' => 'Disetujui untuk diproses ke PO',
                'action_at' => now()->subDays(3)->setHour(11)->setMinute(20),
            ]);

            DocumentApprovalHistory::create([
                'document_approval_id' => $prApproval->id,
                'user_id' => $admin?->id,
                'user_name' => 'Direktur Operasional',
                'user_role' => 'Direktur',
                'level' => 2,
                'stage_name' => 'Final Approval Direksi',
                'action' => 'APPROVE',
                'notes' => 'Budget approved, lanjutkan eksekusi',
                'action_at' => now()->subDays(3)->setHour(16)->setMinute(45),
            ]);
        }

        // -------------------------------------------------------------
        // DUMMY 3: Goods Receipt PO #GRPO-20001 (Status: REVISED / Perlu revisi)
        // -------------------------------------------------------------
        if ($grpoType) {
            $grpoApproval = DocumentApproval::updateOrCreate(
                [
                    'document_type_id' => $grpoType->id,
                    'sap_doc_entry' => 2001,
                ],
                [
                    'sap_object_type' => 20,
                    'sap_doc_num' => 'GRPO-20001',
                    'requester_id' => $sales?->id,
                    'requester_name' => 'Warehouse Staff Balaraja',
                    'status' => 'REVISED',
                    'current_level' => 1,
                    'max_level' => 2,
                    'doc_date' => now()->subDay()->toDateString(),
                    'doc_due_date' => now()->toDateString(),
                    'total_amount' => 69375000,
                    'currency' => 'IDR',
                    'notes' => 'Penerimaan Parsial 50 Ton Garam Kasar',
                    'submitted_at' => now()->subDay()->setHour(10)->setMinute(0),
                ]
            );

            DocumentApprovalHistory::where('document_approval_id', $grpoApproval->id)->delete();

            DocumentApprovalHistory::create([
                'document_approval_id' => $grpoApproval->id,
                'user_id' => $sales?->id,
                'user_name' => 'Warehouse Staff Balaraja',
                'user_role' => 'Staff Gudang',
                'level' => 1,
                'stage_name' => 'Submission',
                'action' => 'SUBMIT',
                'notes' => 'Input data penerimaan fisik dari armada ekspedisi',
                'action_at' => now()->subDay()->setHour(10)->setMinute(0),
            ]);

            DocumentApprovalHistory::create([
                'document_approval_id' => $grpoApproval->id,
                'user_id' => $admin?->id,
                'user_name' => 'Quality Control Lead',
                'user_role' => 'QC Lead',
                'level' => 1,
                'stage_name' => 'Review QC & Warehouse',
                'action' => 'REVISE',
                'notes' => 'Lampiran Certificate of Analysis (COA) belum diunggah dan nomor batch surat jalan tidak sinkron.',
                'action_at' => now()->subHours(5),
            ]);
        }
    }
}
