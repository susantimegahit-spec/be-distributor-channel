<?php

namespace App\Modules\Psikotes\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DiscCandidate;
use App\Modules\Psikotes\Requests\SaveDiscAnswerRequest;
use App\Modules\Psikotes\Services\DiscAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscCandidateTestController extends Controller
{
    public function __construct(
        protected DiscAssessmentService $assessmentService
    ) {}

    /**
     * Get candidate test questions and currently saved answers (no token required).
     */
    public function getTest(Request $request): JsonResponse
    {
        /** @var DiscCandidate $candidate */
        $candidate = $request->user();
        $data = $this->assessmentService->getAssessmentForCandidate($candidate);

        return response()->json([
            'status'  => 'success',
            'message' => 'Assessment session retrieved successfully',
            'data'    => $data,
        ]);
    }

    /**
     * Autosave candidate answer for a single question (no token required).
     */
    public function saveAnswer(SaveDiscAnswerRequest $request): JsonResponse
    {
        /** @var DiscCandidate $candidate */
        $candidate = $request->user();
        $validated = $request->validated();

        $answer = $this->assessmentService->saveCandidateAnswer(
            $candidate,
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
     * Submit completed assessment test (no token required).
     */
    public function submit(Request $request): JsonResponse
    {
        /** @var DiscCandidate $candidate */
        $candidate = $request->user();
        $result = $this->assessmentService->submitCandidateAssessment($candidate);

        return response()->json([
            'status'  => 'success',
            'message' => $result['message'],
            'data'    => [
                'status'       => $result['status'],
                'submitted_at' => $result['submitted_at'],
            ],
        ]);
    }

    /**
     * Get candidate assessment result (no token required).
     */
    public function result(Request $request): JsonResponse
    {
        /** @var DiscCandidate $candidate */
        $candidate = $request->user();
        $result = $this->assessmentService->getCandidateResult($candidate);

        return response()->json([
            'status'  => 'success',
            'message' => 'Hasil asesmen berhasil diambil.',
            'data'    => $result,
        ]);
    }
}

