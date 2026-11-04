<?php

namespace App\Modules\Psikotes\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DiscAssessment;
use App\Models\DiscCandidate;
use App\Modules\Psikotes\Requests\LoginCandidateRequest;
use App\Modules\Psikotes\Requests\RegisterCandidateRequest;
use App\Modules\Psikotes\Requests\UpdateCandidateBiodataRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CandidateAuthController extends Controller
{
    /**
     * Register a new candidate account.
     */
    public function register(RegisterCandidateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $candidate = DiscCandidate::create([
            'full_name'        => trim($validated['full_name']),
            'email'            => strtolower(trim($validated['email'])),
            'phone'            => trim($validated['phone']),
            'password'         => Hash::make($validated['password']),
            'position_applied' => $validated['position_applied'] ?? null,
            'status'           => DiscCandidate::STATUS_PRESCREEN,
            'biodata'          => null,
        ]);

        // Auto-initialize DISC assessment
        DiscAssessment::create([
            'candidate_id' => $candidate->id,
            'token'        => Str::random(40),
            'status'       => 'in_progress',
            'started_at'   => Carbon::now(),
            'expires_at'   => null,
        ]);

        $token = $candidate->createToken('candidate_auth')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Registrasi kandidat berhasil.',
            'data'    => [
                'candidate'  => $this->formatCandidate($candidate),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Login candidate with email and password.
     */
    public function login(LoginCandidateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower(trim($validated['email']));

        $candidate = DiscCandidate::where('email', $email)->first();

        if (!$candidate || !Hash::check($validated['password'], $candidate->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Email atau kata sandi tidak sesuai.',
                'data'    => null,
            ], 401);
        }

        $token = $candidate->createToken('candidate_auth')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Login berhasil.',
            'data'    => [
                'candidate'  => $this->formatCandidate($candidate),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Get authenticated candidate profile and recruitment assessment status.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var DiscCandidate $candidate */
        $candidate = $request->user();

        return response()->json([
            'status'  => 'success',
            'message' => 'Data profil kandidat berhasil diambil.',
            'data'    => [
                'candidate' => $this->formatCandidate($candidate),
            ],
        ]);
    }

    /**
     * Update candidate profile and 5-step detailed biodata.
     */
    public function updateBiodata(UpdateCandidateBiodataRequest $request): JsonResponse
    {
        /** @var DiscCandidate $candidate */
        $candidate = $request->user();

        if (!$candidate->canAccessAssessmentsAndBiodata()) {
            return response()->json([
                'status'  => 'error',
                'message' => $candidate->getAssessmentAccessDeniedMessage(),
                'data'    => null,
            ], 403);
        }

        $validated = $request->validated();

        $updateData = [];

        if (array_key_exists('full_name', $validated) && $validated['full_name'] !== null) {
            $updateData['full_name'] = trim($validated['full_name']);
        }
        if (array_key_exists('phone', $validated) && $validated['phone'] !== null) {
            $updateData['phone'] = trim($validated['phone']);
        }
        if (array_key_exists('birth_date', $validated)) {
            $updateData['birth_date'] = $validated['birth_date'];
        }
        if (array_key_exists('gender', $validated)) {
            $updateData['gender'] = $validated['gender'];
        }
        if (array_key_exists('position_applied', $validated)) {
            $updateData['position_applied'] = $validated['position_applied'];
        }
        if (array_key_exists('avatar', $validated)) {
            $updateData['avatar'] = $validated['avatar'];
        }
        if (array_key_exists('biodata', $validated) && is_array($validated['biodata'])) {
            $existingBiodata = is_array($candidate->biodata) ? $candidate->biodata : [];
            $updateData['biodata'] = array_merge($existingBiodata, $validated['biodata']);
        }

        if (!empty($updateData)) {
            $candidate->update($updateData);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Biodata kandidat berhasil disimpan.',
            'data'    => [
                'candidate' => $this->formatCandidate($candidate->fresh()),
            ],
        ]);
    }

    /**
     * Logout and invalidate current candidate access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && method_exists($user, 'currentAccessToken')) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Logout berhasil.',
            'data'    => null,
        ]);
    }

    /**
     * Canonical candidate data formatter.
     */
    protected function formatCandidate(DiscCandidate $candidate): array
    {
        $latestAssessment = DiscAssessment::where('candidate_id', $candidate->id)
            ->latest('id')
            ->first();

        $status = 'not_started';
        if ($latestAssessment) {
            $status = $latestAssessment->status;
        }

        return [
            'id'                    => $candidate->id,
            'full_name'             => $candidate->full_name,
            'email'                 => $candidate->email,
            'phone'                 => $candidate->phone,
            'birth_date'            => $candidate->birth_date?->format('Y-m-d'),
            'gender'                => $candidate->gender,
            'position_applied'      => $candidate->position_applied,
            'status'                => $candidate->status ?? DiscCandidate::STATUS_PRESCREEN,
            'can_access_assessment' => $candidate->canAccessAssessmentsAndBiodata(),
            'avatar'                => $candidate->avatar,
            'biodata'               => $candidate->biodata,
            'assessment_status'     => $status,
            'created_at'            => $candidate->created_at?->toIso8601String(),
            'updated_at'            => $candidate->updated_at?->toIso8601String(),
        ];
    }
}

