<?php

namespace App\Modules\Psikotes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Psikotes\Requests\SaveDiscAnswerRequest;
use App\Modules\Psikotes\Services\DiscAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscPublicTestController extends Controller
{
    public function __construct(
        protected DiscAssessmentService $assessmentService
    ) {}

    /**
     * Get candidate test questions and currently saved answers.
     */
    public function getTest(string $token): JsonResponse
    {
        $data = $this->assessmentService->getAssessmentByToken($token);

        return response()->json([
            'status'  => 'success',
            'message' => 'Assessment session retrieved successfully',
            'data'    => $data,
        ]);
    }

    /**
     * Autosave candidate answer for a single question.
     */
    public function saveAnswer(SaveDiscAnswerRequest $request, string $token): JsonResponse
    {
        $validated = $request->validated();
        $answer = $this->assessmentService->saveAnswer(
            $token,
            (int) $validated['question_number'],
            isset($validated['p_choice']) ? (int) $validated['p_choice'] : null,
            isset($validated['k_choice']) ? (int) $validated['k_choice'] : null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Answer saved successfully',
            'data'    => [
                'question_number' => $answer->question_number,
                'p_choice'        => $answer->p_choice,
                'k_choice'        => $answer->k_choice,
            ],
        ]);
    }

    /**
     * Submit completed assessment test.
     */
    public function submit(Request $request, string $token): JsonResponse
    {
        $result = $this->assessmentService->submitAssessment($token);

        return response()->json([
            'status'  => 'success',
            'message' => $result['message'],
            'data'    => [
                'status'       => $result['status'],
                'submitted_at' => $result['submitted_at'],
            ],
        ]);
    }
}

