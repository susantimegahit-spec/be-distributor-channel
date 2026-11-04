<?php

namespace App\Modules\Psikotes\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DiscCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CandidateManagementController extends Controller
{
    /**
     * Get paginated candidates list with filtering and recruitment status summary stats.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DiscCandidate::query()->with([
            'assessments' => function ($q) {
                $q->latest('id')->with('result');
            },
        ]);

        // Filter by recruitment status
        if ($request->filled('status')) {
            $query->where('status', trim($request->query('status')));
        }

        // Filter by position applied
        if ($request->filled('position_applied')) {
            $pos = strtolower(trim($request->query('position_applied')));
            $query->whereRaw('LOWER(position_applied) LIKE ?', ["%{$pos}%"]);
        }

        // Search by name, email, or phone
        if ($request->filled('search')) {
            $search = strtolower(trim($request->query('search')));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Sorting
        $allowedSorts = ['id', 'full_name', 'email', 'status', 'created_at', 'updated_at'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true)
            ? $request->query('sort_by')
            : 'created_at';
        $order = strtolower($request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $order);

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginator = $query->paginate($perPage);

        // Stats summary per recruitment status
        $stats = [
            'total'          => DiscCandidate::count(),
            'prescreen'      => DiscCandidate::where('status', DiscCandidate::STATUS_PRESCREEN)->count(),
            'shortlist'      => DiscCandidate::where('status', DiscCandidate::STATUS_SHORTLIST)->count(),
            'reject'         => DiscCandidate::where('status', DiscCandidate::STATUS_REJECT)->count(),
            'hr_interview'   => DiscCandidate::where('status', DiscCandidate::STATUS_HR_INTERVIEW)->count(),
            'user_interview' => DiscCandidate::where('status', DiscCandidate::STATUS_USER_INTERVIEW)->count(),
            'psikotes'       => DiscCandidate::where('status', DiscCandidate::STATUS_PSIKOTES)->count(),
            'final_interview'=> DiscCandidate::where('status', DiscCandidate::STATUS_FINAL_INTERVIEW)->count(),
            'offering'       => DiscCandidate::where('status', DiscCandidate::STATUS_OFFERING)->count(),
            'join'           => DiscCandidate::where('status', DiscCandidate::STATUS_JOIN)->count(),
        ];

        $candidates = collect($paginator->items())->map(function (DiscCandidate $candidate) {
            $latestAssessment = $candidate->assessments->first();
            $assessmentSummary = null;

            if ($latestAssessment) {
                $result = $latestAssessment->result;
                $assessmentSummary = [
                    'id'             => $latestAssessment->id,
                    'token'          => $latestAssessment->token,
                    'status'         => $latestAssessment->status,
                    'started_at'     => $latestAssessment->started_at?->toIso8601String(),
                    'completed_at'   => $latestAssessment->completed_at?->toIso8601String(),
                    'profile_number' => $result?->profile_number,
                    'pattern_name'   => $result?->pattern_name,
                ];
            }

            return [
                'id'                    => $candidate->id,
                'full_name'             => $candidate->full_name,
                'email'                 => $candidate->email,
                'phone'                 => $candidate->phone,
                'gender'                => $candidate->gender,
                'birth_date'            => $candidate->birth_date?->format('Y-m-d'),
                'position_applied'      => $candidate->position_applied,
                'status'                => $candidate->status ?? DiscCandidate::STATUS_PRESCREEN,
                'avatar'                => $candidate->avatar,
                'biodata_completed'     => !empty($candidate->biodata),
                'can_access_assessment' => $candidate->canAccessAssessmentsAndBiodata(),
                'latest_assessment'     => $assessmentSummary,
                'created_at'            => $candidate->created_at?->toIso8601String(),
                'updated_at'            => $candidate->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status'       => 'success',
            'message'      => 'Daftar kandidat berhasil diambil.',
            'data'         => $candidates,
            'stats'        => $stats,
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ]);
    }

    /**
     * Get candidate detail including full biodata and assessment history.
     */
    public function show(int $id): JsonResponse
    {
        $candidate = DiscCandidate::with([
            'assessments' => function ($q) {
                $q->latest('id')->with(['result', 'answers']);
            },
        ])->find($id);

        if (!$candidate) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data kandidat tidak ditemukan.',
                'data'    => null,
            ], 404);
        }

        $assessments = $candidate->assessments->map(function ($assessment) {
            return [
                'id'           => $assessment->id,
                'token'        => $assessment->token,
                'status'       => $assessment->status,
                'started_at'   => $assessment->started_at?->toIso8601String(),
                'completed_at' => $assessment->completed_at?->toIso8601String(),
                'expires_at'   => $assessment->expires_at?->toIso8601String(),
                'result'       => $assessment->result ? [
                    'line_1_m'        => $assessment->result->line_1_m,
                    'line_2_l'        => $assessment->result->line_2_l,
                    'line_3_diff'     => $assessment->result->line_3_diff,
                    'chart_scores'    => $assessment->result->chart_scores,
                    'profile_number'  => $assessment->result->profile_number,
                    'pattern_name'    => $assessment->result->pattern_name,
                    'job_behavior'    => $assessment->result->job_behavior,
                    'characteristics' => $assessment->result->characteristics,
                    'description'     => $assessment->result->description,
                ] : null,
                'total_answers' => $assessment->answers->count(),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail kandidat berhasil diambil.',
            'data'    => [
                'id'                    => $candidate->id,
                'full_name'             => $candidate->full_name,
                'email'                 => $candidate->email,
                'phone'                 => $candidate->phone,
                'gender'                => $candidate->gender,
                'birth_date'            => $candidate->birth_date?->format('Y-m-d'),
                'position_applied'      => $candidate->position_applied,
                'status'                => $candidate->status ?? DiscCandidate::STATUS_PRESCREEN,
                'avatar'                => $candidate->avatar,
                'biodata'               => $candidate->biodata,
                'biodata_completed'     => !empty($candidate->biodata),
                'can_access_assessment' => $candidate->canAccessAssessmentsAndBiodata(),
                'assessments'           => $assessments,
                'created_at'            => $candidate->created_at?->toIso8601String(),
                'updated_at'            => $candidate->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update candidate recruitment pipeline status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $candidate = DiscCandidate::find($id);

        if (!$candidate) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data kandidat tidak ditemukan.',
                'data'    => null,
            ], 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(DiscCandidate::ALL_STATUSES)],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $oldStatus = $candidate->status;
        $candidate->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "Status kandidat berhasil diubah dari '{$oldStatus}' menjadi '{$candidate->status}'.",
            'data'    => [
                'id'                    => $candidate->id,
                'full_name'             => $candidate->full_name,
                'email'                 => $candidate->email,
                'status'                => $candidate->status,
                'can_access_assessment' => $candidate->canAccessAssessmentsAndBiodata(),
                'updated_at'            => $candidate->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Change candidate status to shortlist.
     */
    public function shortlist(Request $request, int $id): JsonResponse
    {
        $candidate = DiscCandidate::find($id);

        if (!$candidate) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data kandidat tidak ditemukan.',
                'data'    => null,
            ], 404);
        }

        $candidate->update([
            'status' => DiscCandidate::STATUS_SHORTLIST,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Status kandidat berhasil diubah menjadi shortlist. Kandidat sekarang dapat mengisi biodata dan mengerjakan asesmen.',
            'data'    => [
                'id'                    => $candidate->id,
                'full_name'             => $candidate->full_name,
                'email'                 => $candidate->email,
                'status'                => $candidate->status,
                'can_access_assessment' => true,
                'updated_at'            => $candidate->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Change candidate status to reject.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $candidate = DiscCandidate::find($id);

        if (!$candidate) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data kandidat tidak ditemukan.',
                'data'    => null,
            ], 404);
        }

        $candidate->update([
            'status' => DiscCandidate::STATUS_REJECT,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Status kandidat berhasil diubah menjadi reject.',
            'data'    => [
                'id'                    => $candidate->id,
                'full_name'             => $candidate->full_name,
                'email'                 => $candidate->email,
                'status'                => $candidate->status,
                'can_access_assessment' => false,
                'updated_at'            => $candidate->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
