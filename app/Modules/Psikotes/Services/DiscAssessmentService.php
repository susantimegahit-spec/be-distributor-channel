<?php

namespace App\Modules\Psikotes\Services;

use App\Models\DiscAnswer;
use App\Models\DiscAssessment;
use App\Models\DiscAssessmentResult;
use App\Models\DiscCandidate;
use App\Models\DiscQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DiscAssessmentService
{
    public function __construct(
        protected DiscScoringService $scoringService,
        protected DiscQuestionService $questionService
    ) {}

    /**
     * Create or find a candidate and create a new DISC assessment invitation.
     */
    public function createAssessment(array $candidateData, ?int $expiryDays = 7): DiscAssessment
    {
        return DB::transaction(function () use ($candidateData, $expiryDays) {
            $candidate = DiscCandidate::firstOrCreate(
                ['email' => strtolower(trim($candidateData['email']))],
                [
                    'full_name'        => trim($candidateData['full_name']),
                    'phone'            => $candidateData['phone'] ?? null,
                    'birth_date'       => $candidateData['birth_date'] ?? null,
                    'gender'           => $candidateData['gender'] ?? null,
                    'position_applied' => $candidateData['position_applied'] ?? null,
                ]
            );

            // Update candidate details if provided
            $candidate->update(array_filter([
                'full_name'        => trim($candidateData['full_name']),
                'phone'            => $candidateData['phone'] ?? null,
                'birth_date'       => $candidateData['birth_date'] ?? null,
                'gender'           => $candidateData['gender'] ?? null,
                'position_applied' => $candidateData['position_applied'] ?? null,
            ]));

            $token = Str::random(40);
            $expiresAt = $expiryDays ? Carbon::now()->addDays($expiryDays) : null;

            return DiscAssessment::create([
                'candidate_id' => $candidate->id,
                'token'        => $token,
                'status'       => 'in_progress',
                'expires_at'   => $expiresAt,
            ]);
        });
    }

    /**
     * Get assessment test session by token for candidate (includes safe questions + current answers).
     */
    public function getAssessmentByToken(string $token): array
    {
        $assessment = DiscAssessment::with(['candidate', 'answers'])
            ->where('token', $token)
            ->firstOrFail();

        // Check expiry
        if ($assessment->expires_at && Carbon::now()->greaterThan($assessment->expires_at)) {
            throw ValidationException::withMessages([
                'token' => ['Assessment token has expired.'],
            ]);
        }

        return $this->formatAssessmentTest($assessment);
    }

    /**
     * Get or create assessment test session for authenticated candidate (no token required).
     */
    public function getAssessmentForCandidate(DiscCandidate $candidate): array
    {
        $assessment = DiscAssessment::with(['candidate', 'answers'])
            ->where('candidate_id', $candidate->id)
            ->latest('id')
            ->first();

        if (!$assessment) {
            $assessment = DiscAssessment::create([
                'candidate_id' => $candidate->id,
                'token'        => Str::random(40),
                'status'       => 'in_progress',
                'started_at'   => Carbon::now(),
                'expires_at'   => null,
            ]);
            $assessment->load(['candidate', 'answers']);
        }

        return $this->formatAssessmentTest($assessment);
    }

    /**
     * Common helper to format assessment session data for candidate.
     */
    protected function formatAssessmentTest(DiscAssessment $assessment): array
    {
        // Check if already completed
        if (in_array($assessment->status, ['completed', 'scored', 'review_required'])) {
            return [
                'assessment_id' => $assessment->id,
                'candidate'     => [
                    'id'               => $assessment->candidate->id,
                    'full_name'        => $assessment->candidate->full_name,
                    'email'            => $assessment->candidate->email,
                    'position_applied' => $assessment->candidate->position_applied,
                ],
                'status'        => $assessment->status,
                'completed'     => true,
                'submitted_at'  => $assessment->submitted_at?->toIso8601String(),
                'message'       => 'Assessment has already been completed.',
            ];
        }

        // Mark started_at if not set
        if (!$assessment->started_at) {
            $assessment->update(['started_at' => Carbon::now()]);
        }

        $questions = $this->questionService->getAllForCandidate();

        // Format answers keyed by question_number
        $savedAnswers = $assessment->answers->keyBy('question_number')->map(function ($ans) {
            return [
                'p_choice' => $ans->p_choice,
                'k_choice' => $ans->k_choice,
            ];
        })->all();

        return [
            'assessment_id'   => $assessment->id,
            'candidate'       => [
                'id'               => $assessment->candidate->id,
                'full_name'        => $assessment->candidate->full_name,
                'email'            => $assessment->candidate->email,
                'position_applied' => $assessment->candidate->position_applied,
            ],
            'status'          => $assessment->status,
            'completed'       => false,
            'started_at'      => $assessment->started_at?->toIso8601String(),
            'expires_at'      => $assessment->expires_at?->toIso8601String(),
            'total_questions' => count($questions),
            'questions'       => $questions,
            'answers'         => $savedAnswers,
        ];
    }

    /**
     * Autosave an answer for a single question by token.
     */
    public function saveAnswer(string $token, int $questionNumber, ?int $pChoice, ?int $kChoice): DiscAnswer
    {
        $assessment = DiscAssessment::where('token', $token)->firstOrFail();

        return $this->executeSaveAnswer($assessment, $questionNumber, $pChoice, $kChoice);
    }

    /**
     * Autosave an answer for authenticated candidate (no token required).
     */
    public function saveCandidateAnswer(DiscCandidate $candidate, int $questionNumber, ?int $pChoice, ?int $kChoice): DiscAnswer
    {
        $assessment = DiscAssessment::where('candidate_id', $candidate->id)
            ->latest('id')
            ->first();

        if (!$assessment || in_array($assessment->status, ['completed', 'scored', 'review_required'])) {
            $assessment = DiscAssessment::create([
                'candidate_id' => $candidate->id,
                'token'        => Str::random(40),
                'status'       => 'in_progress',
                'started_at'   => Carbon::now(),
            ]);
        }

        return $this->executeSaveAnswer($assessment, $questionNumber, $pChoice, $kChoice);
    }

    /**
     * Internal execution of saving answer.
     */
    protected function executeSaveAnswer(DiscAssessment $assessment, int $questionNumber, ?int $pChoice, ?int $kChoice): DiscAnswer
    {
        if (in_array($assessment->status, ['completed', 'scored', 'review_required'])) {
            throw ValidationException::withMessages([
                'assessment' => ['Assessment has already been submitted.'],
            ]);
        }

        if ($questionNumber < 1 || $questionNumber > 24) {
            throw ValidationException::withMessages([
                'question_number' => ['Question number must be between 1 and 24.'],
            ]);
        }

        // Strict rule: P and K cannot be the same choice
        if ($pChoice !== null && $kChoice !== null && $pChoice === $kChoice) {
            throw ValidationException::withMessages([
                'k_choice' => ['Pilihan Paling (P) dan Paling Tidak (K) tidak boleh sama pada soal ini.'],
            ]);
        }

        return DiscAnswer::updateOrCreate(
            [
                'assessment_id'   => $assessment->id,
                'question_number' => $questionNumber,
            ],
            [
                'p_choice' => $pChoice,
                'k_choice' => $kChoice,
            ]
        );
    }

    /**
     * Submit assessment and run scoring engine by token.
     */
    public function submitAssessment(string $token): array
    {
        $assessment = DiscAssessment::with(['candidate', 'answers'])
            ->where('token', $token)
            ->firstOrFail();

        return $this->executeSubmit($assessment);
    }

    /**
     * Submit assessment and run scoring engine for authenticated candidate.
     */
    public function submitCandidateAssessment(DiscCandidate $candidate): array
    {
        $assessment = DiscAssessment::with(['candidate', 'answers'])
            ->where('candidate_id', $candidate->id)
            ->whereIn('status', ['in_progress', 'pending'])
            ->latest('id')
            ->first();

        if (!$assessment) {
            $completed = DiscAssessment::where('candidate_id', $candidate->id)
                ->whereIn('status', ['completed', 'scored', 'review_required'])
                ->first();

            if ($completed) {
                throw ValidationException::withMessages([
                    'assessment' => ['Assessment has already been submitted.'],
                ]);
            }

            throw ValidationException::withMessages([
                'assessment' => ['No active assessment found.'],
            ]);
        }

        return $this->executeSubmit($assessment);
    }

    /**
     * Internal execution of submitting and scoring assessment.
     */
    protected function executeSubmit(DiscAssessment $assessment): array
    {
        if (in_array($assessment->status, ['completed', 'scored', 'review_required'])) {
            throw ValidationException::withMessages([
                'assessment' => ['Assessment has already been submitted.'],
            ]);
        }

        $answers = $assessment->answers->keyBy('question_number');

        // Verify all 24 questions are answered
        $missing = [];
        $invalid = [];

        for ($q = 1; $q <= 24; $q++) {
            $ans = $answers->get($q);
            if (!$ans || $ans->p_choice === null || $ans->k_choice === null) {
                $missing[] = $q;
            } elseif ($ans->p_choice === $ans->k_choice) {
                $invalid[] = $q;
            }
        }

        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'answers' => ['Mohon lengkapi seluruh 24 soal. Soal belum diisi: ' . implode(', ', $missing)],
            ]);
        }

        if (!empty($invalid)) {
            throw ValidationException::withMessages([
                'answers' => ['Pilihan P dan K tidak boleh sama pada soal: ' . implode(', ', $invalid)],
            ]);
        }

        return DB::transaction(function () use ($assessment, $answers) {
            // Run scoring
            $answersArray = $answers->values()->toArray();
            $scoringResult = $this->scoringService->scoreAssessment($answersArray);

            // Save result
            $result = DiscAssessmentResult::updateOrCreate(
                ['assessment_id' => $assessment->id],
                [
                    'p_counts'           => $scoringResult['p_counts'],
                    'k_counts'           => $scoringResult['k_counts'],
                    'net_counts'         => $scoringResult['net_counts'],
                    'chart_scores'       => $scoringResult['chart_scores'],
                    'profile_id'         => $scoringResult['profile_id'],
                    'profile_number'     => $scoringResult['profile_number'],
                    'profile_title'      => $scoringResult['profile_title'],
                    'dimension_code'     => $scoringResult['dimension_code'],
                    'matched_rule_index' => $scoringResult['matched_rule_index'],
                    'status'             => $scoringResult['status'],
                ]
            );

            // Update assessment status
            $finalStatus = ($scoringResult['status'] === 'valid') ? 'scored' : 'review_required';
            $assessment->update([
                'status'       => $finalStatus,
                'submitted_at' => Carbon::now(),
            ]);

            return [
                'message'      => 'Assessment submitted successfully.',
                'submitted_at' => $assessment->submitted_at->toIso8601String(),
                'status'       => $finalStatus,
            ];
        });
    }

    /**
     * Get candidate's latest assessment result.
     */
    public function getCandidateResult(DiscCandidate $candidate): array
    {
        $assessment = DiscAssessment::with(['result.profile', 'answers'])
            ->where('candidate_id', $candidate->id)
            ->latest('id')
            ->first();

        if (!$assessment) {
            throw ValidationException::withMessages([
                'assessment' => ['Belum ada asesmen DISC untuk kandidat ini.'],
            ]);
        }

        return [
            'id'           => $assessment->id,
            'status'       => $assessment->status,
            'started_at'   => $assessment->started_at?->toIso8601String(),
            'submitted_at' => $assessment->submitted_at?->toIso8601String(),
            'candidate'    => [
                'id'               => $candidate->id,
                'full_name'        => $candidate->full_name,
                'email'            => $candidate->email,
                'phone'            => $candidate->phone,
                'position_applied' => $candidate->position_applied,
            ],
            'result'       => $assessment->result ? [
                'status'             => $assessment->result->status,
                'profile_number'     => $assessment->result->profile_number,
                'profile_title'      => $assessment->result->profile_title,
                'dimension_code'     => $assessment->result->dimension_code,
                'matched_rule_index' => $assessment->result->matched_rule_index,
                'p_counts'           => $assessment->result->p_counts,
                'k_counts'           => $assessment->result->k_counts,
                'net_counts'         => $assessment->result->net_counts,
                'chart_scores'       => $assessment->result->chart_scores,
                'profile_details'    => $assessment->result->profile?->characteristics,
            ] : null,
            'answers_count'=> $assessment->answers->count(),
        ];
    }

    /**
     * List assessments for Admin HR with pagination and filters.
     */
    public function listAssessments(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DiscAssessment::with(['candidate', 'result'])
            ->orderBy('id', 'desc');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->whereHas('candidate', function (Builder $q) use ($search) {
                $q->where('full_name', 'ilike', $search)
                  ->orWhere('email', 'ilike', $search)
                  ->orWhere('position_applied', 'ilike', $search);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get single assessment result details for Admin HR.
     */
    public function getAssessmentDetail(int $assessmentId): array
    {
        $assessment = DiscAssessment::with(['candidate', 'result.profile', 'answers'])
            ->findOrFail($assessmentId);

        return [
            'id'           => $assessment->id,
            'token'        => $assessment->token,
            'status'       => $assessment->status,
            'started_at'   => $assessment->started_at?->toIso8601String(),
            'submitted_at' => $assessment->submitted_at?->toIso8601String(),
            'expires_at'   => $assessment->expires_at?->toIso8601String(),
            'candidate'    => [
                'id'               => $assessment->candidate->id,
                'full_name'        => $assessment->candidate->full_name,
                'email'            => $assessment->candidate->email,
                'phone'            => $assessment->candidate->phone,
                'birth_date'       => $assessment->candidate->birth_date?->format('Y-m-d'),
                'gender'           => $assessment->candidate->gender,
                'position_applied' => $assessment->candidate->position_applied,
            ],
            'result'       => $assessment->result ? [
                'status'             => $assessment->result->status,
                'profile_number'     => $assessment->result->profile_number,
                'profile_title'      => $assessment->result->profile_title,
                'dimension_code'     => $assessment->result->dimension_code,
                'matched_rule_index' => $assessment->result->matched_rule_index,
                'p_counts'           => $assessment->result->p_counts,
                'k_counts'           => $assessment->result->k_counts,
                'net_counts'         => $assessment->result->net_counts,
                'chart_scores'       => $assessment->result->chart_scores,
                'profile_details'    => $assessment->result->profile?->characteristics,
            ] : null,
            'answers_count'=> $assessment->answers->count(),
        ];
    }
}
