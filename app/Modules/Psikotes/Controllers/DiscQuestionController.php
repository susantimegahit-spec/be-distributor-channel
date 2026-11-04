<?php

namespace App\Modules\Psikotes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Psikotes\Requests\BulkUpdateDiscQuestionRequest;
use App\Modules\Psikotes\Requests\UpdateDiscQuestionRequest;
use App\Modules\Psikotes\Services\DiscQuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscQuestionController extends Controller
{
    public function __construct(
        protected DiscQuestionService $questionService
    ) {}

    /**
     * Get all 24 questions for Admin HR.
     */
    public function index(): JsonResponse
    {
        $questions = $this->questionService->getAllForAdmin();

        return response()->json([
            'status'  => 'success',
            'message' => 'Questions retrieved successfully',
            'data'    => $questions,
        ]);
    }

    /**
     * Get single question by question number.
     */
    public function show(int $number): JsonResponse
    {
        $question = $this->questionService->findByNumber($number);

        if (!$question) {
            return response()->json([
                'status'  => 'error',
                'message' => "Question number {$number} not found",
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Question retrieved successfully',
            'data'    => $question,
        ]);
    }

    /**
     * Update single question.
     */
    public function update(UpdateDiscQuestionRequest $request, int $number): JsonResponse
    {
        $userId = $request->user()?->id;
        $question = $this->questionService->updateQuestion($number, $request->validated(), $userId);

        return response()->json([
            'status'  => 'success',
            'message' => "Question number {$number} updated successfully",
            'data'    => $question,
        ]);
    }

    /**
     * Bulk update questions.
     */
    public function bulkUpdate(BulkUpdateDiscQuestionRequest $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $updated = $this->questionService->bulkUpdate($request->validated()['questions'], $userId);

        return response()->json([
            'status'  => 'success',
            'message' => count($updated) . ' questions updated successfully',
            'data'    => $updated,
        ]);
    }

    /**
     * Reset questions bank to canonical default.
     */
    public function seedDefault(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $count = $this->questionService->resetToDefault($userId);

        return response()->json([
            'status'  => 'success',
            'message' => "Successfully reset {$count} questions to default reference",
        ]);
    }
}

