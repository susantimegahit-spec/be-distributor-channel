<?php

namespace App\Modules\TaskManagement\Services;

use App\Models\TmTaskComment;
use App\Modules\TaskManagement\Repositories\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CommentService
{
    protected TaskRepositoryInterface $taskRepo;

    public function __construct(TaskRepositoryInterface $taskRepo)
    {
        $this->taskRepo = $taskRepo;
    }

    public function addComment(int $taskId, int $authorEmployeeId, string $commentText, ?int $parentCommentId = null, bool $isInternalOnly = false): TmTaskComment
    {
        $comment = TmTaskComment::create([
            'task_id'            => $taskId,
            'parent_comment_id'  => $parentCommentId,
            'author_employee_id' => $authorEmployeeId,
            'comment_text'       => $commentText,
            'is_internal_only'   => $isInternalOnly,
        ]);

        $this->taskRepo->logActivity(
            $taskId,
            $authorEmployeeId,
            'COMMENT_ADDED',
            'comment_text',
            null,
            mb_substr($commentText, 0, 100)
        );

        return $comment->load('author:id,nik,full_name,avatar_url');
    }

    public function getTaskComments(int $taskId): Collection
    {
        return TmTaskComment::where('task_id', $taskId)
            ->whereNull('parent_comment_id')
            ->with(['author:id,nik,full_name,avatar_url', 'replies.author:id,nik,full_name,avatar_url'])
            ->latest()
            ->get();
    }
}
