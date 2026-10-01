<?php

namespace App\Modules\TaskManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Services\CommentService;
use App\Models\HrisEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    protected CommentService $commentService;

    public function __construct(CommentService $commentService)
    {
        $this->commentService = $commentService;
    }

    protected function getEmployeeId(Request $request): int
    {
        $user = $request->user();
        if ($user) {
            $employee = HrisEmployee::where('user_id', $user->id)->first();
            if ($employee) {
                return $employee->id;
            }
        }
        $first = HrisEmployee::first();
        return $first ? $first->id : 1;
    }

    public function index(int $taskId): JsonResponse
    {
        $comments = $this->commentService->getTaskComments($taskId);
        return response()->json([
            'status'  => 'success',
            'data'    => $comments,
        ]);
    }

    public function store(Request $request, int $taskId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'comment_text'      => 'required|string',
            'parent_comment_id' => 'nullable|integer',
            'is_internal_only'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $employeeId = $this->getEmployeeId($request);
        $comment = $this->commentService->addComment(
            $taskId,
            $employeeId,
            $request->input('comment_text'),
            $request->input('parent_comment_id'),
            (bool)$request->input('is_internal_only', false)
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Comment added successfully',
            'data'    => $comment,
        ], 201);
    }
}
