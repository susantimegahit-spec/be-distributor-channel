<?php

namespace App\Modules\Psikotes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Psikotes\Requests\CreateDiscCandidateRequest;
use App\Modules\Psikotes\Services\DiscAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscAssessmentController extends Controller
{
    public function __construct(
        protected DiscAssessmentService $assessmentService
    ) {}

    /**
     * List assessments for Admin HR.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];
        $perPage = (int) $request->query('per_page', 15);

        $paginator = $this->assessmentService->listAssessments($filters, $perPage);

        return response()->json([
            'status'       => 'success',
            'message'      => 'Assessments retrieved successfully',
            'data'         => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ]);
    }

    /**
     * Create candidate and generate assessment invitation link.
     */
    public function store(CreateDiscCandidateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $expiryDays = isset($validated['expiry_days']) ? (int) $validated['expiry_days'] : 7;

        $assessment = $this->assessmentService->createAssessment($validated, $expiryDays);
        $publicUrl = url("/psikotes/disc/test/{$assessment->token}");

        return response()->json([
            'status'  => 'success',
            'message' => 'Assessment invitation created successfully',
            'data'    => [
                'assessment_id' => $assessment->id,
                'token'         => $assessment->token,
                'test_url'      => $publicUrl,
                'expires_at'    => $assessment->expires_at?->toIso8601String(),
                'candidate'     => [
                    'id'               => $assessment->candidate->id,
                    'full_name'        => $assessment->candidate->full_name,
                    'email'            => $assessment->candidate->email,
                    'position_applied' => $assessment->candidate->position_applied,
                ],
            ],
        ], 201);
    }

    /**
     * Get assessment and scoring result detail for Admin HR.
     */
    public function show(int $id): JsonResponse
    {
        $detail = $this->assessmentService->getAssessmentDetail($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Assessment detail retrieved successfully',
            'data'    => $detail,
        ]);
    }

    /**
     * Export assessments to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = [
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];

        $paginator = $this->assessmentService->listAssessments($filters, 1000);

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="disc_assessments_' . date('Ymd_His') . '.csv"',
        ];

        return response()->stream(function () use ($paginator) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'ID',
                'Candidate Name',
                'Email',
                'Position Applied',
                'Status',
                'Profile Number',
                'Profile Title',
                'Dimension Code',
                'Matched Rule Index',
                'D (Net)',
                'I (Net)',
                'S (Net)',
                'C (Net)',
                'Line 3 D Score',
                'Line 3 I Score',
                'Line 3 S Score',
                'Line 3 C Score',
                'Submitted At',
            ]);

            foreach ($paginator->items() as $item) {
                $res = $item->result;
                fputcsv($handle, [
                    $item->id,
                    $item->candidate?->full_name,
                    $item->candidate?->email,
                    $item->candidate?->position_applied,
                    $item->status,
                    $res?->profile_number,
                    $res?->profile_title,
                    $res?->dimension_code,
                    $res?->matched_rule_index,
                    $res?->net_counts['D'] ?? '',
                    $res?->net_counts['I'] ?? '',
                    $res?->net_counts['S'] ?? '',
                    $res?->net_counts['C'] ?? '',
                    $res?->chart_scores['line3']['D'] ?? '',
                    $res?->chart_scores['line3']['I'] ?? '',
                    $res?->chart_scores['line3']['S'] ?? '',
                    $res?->chart_scores['line3']['C'] ?? '',
                    $item->submitted_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}

