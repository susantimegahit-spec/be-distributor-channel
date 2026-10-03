<?php

namespace App\Modules\DocumentApproval\Services;

use App\Models\DocumentApproval;
use App\Models\DocumentApprovalHistory;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class ApprovalActionService
{
    /**
     * Approve document to next level or final status.
     */
    public function approve(int $approvalId, ?User $user = null, ?string $notes = null): DocumentApproval
    {
        return DB::transaction(function () use ($approvalId, $user, $notes) {
            $approval = DocumentApproval::lockForUpdate()->findOrFail($approvalId);

            if ($approval->status !== 'PENDING') {
                throw new Exception("Cannot approve document with status '{$approval->status}'");
            }

            $currentLvl = $approval->current_level;
            $maxLvl = $approval->max_level;
            $isFinal = ($currentLvl >= $maxLvl);

            if ($isFinal) {
                $approval->status = 'APPROVED';
                $approval->approved_at = now();
            } else {
                $approval->current_level = $currentLvl + 1;
            }
            $approval->save();

            // Record History
            DocumentApprovalHistory::create([
                'document_approval_id' => $approval->id,
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Approver',
                'user_role' => $user?->role?->name ?? 'Manager',
                'level' => $currentLvl,
                'stage_name' => "Approval Level {$currentLvl}",
                'action' => 'APPROVE',
                'notes' => $notes ?? 'Approved',
                'action_at' => now(),
            ]);

            return $approval;
        });
    }

    /**
     * Reject document.
     */
    public function reject(int $approvalId, ?User $user = null, string $reason = ''): DocumentApproval
    {
        return DB::transaction(function () use ($approvalId, $user, $reason) {
            $approval = DocumentApproval::lockForUpdate()->findOrFail($approvalId);

            if ($approval->status !== 'PENDING') {
                throw new Exception("Cannot reject document with status '{$approval->status}'");
            }

            $approval->status = 'REJECTED';
            $approval->rejected_at = now();
            $approval->save();

            // Record History
            DocumentApprovalHistory::create([
                'document_approval_id' => $approval->id,
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Approver',
                'user_role' => $user?->role?->name ?? 'Manager',
                'level' => $approval->current_level,
                'stage_name' => "Approval Level {$approval->current_level}",
                'action' => 'REJECT',
                'notes' => $reason ?: 'Rejected',
                'action_at' => now(),
            ]);

            return $approval;
        });
    }

    /**
     * Revise / Return document to requester.
     */
    public function revise(int $approvalId, ?User $user = null, string $notes = ''): DocumentApproval
    {
        return DB::transaction(function () use ($approvalId, $user, $notes) {
            $approval = DocumentApproval::lockForUpdate()->findOrFail($approvalId);

            $approval->status = 'REVISED';
            $approval->current_level = 1;
            $approval->save();

            // Record History
            DocumentApprovalHistory::create([
                'document_approval_id' => $approval->id,
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Approver',
                'user_role' => $user?->role?->name ?? 'Manager',
                'level' => $approval->current_level,
                'stage_name' => "Returned for Revision",
                'action' => 'REVISE',
                'notes' => $notes ?: 'Revision requested',
                'action_at' => now(),
            ]);

            return $approval;
        });
    }
}
