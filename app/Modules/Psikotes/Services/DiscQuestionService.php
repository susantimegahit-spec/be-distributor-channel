<?php

namespace App\Modules\Psikotes\Services;

use App\Models\DiscQuestion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DiscQuestionService
{
    /**
     * Get all 24 questions for Admin HR (includes scoring keys).
     */
    public function getAllForAdmin(): Collection
    {
        return DiscQuestion::orderBy('question_number')->get();
    }

    /**
     * Get all 24 questions for Candidates (scoring keys stripped).
     */
    public function getAllForCandidate(): array
    {
        return DiscQuestion::orderBy('question_number')
            ->get()
            ->map(fn (DiscQuestion $q) => $q->toCandidateSafeArray())
            ->values()
            ->all();
    }

    /**
     * Find single question by question number.
     */
    public function findByNumber(int $questionNumber): ?DiscQuestion
    {
        return DiscQuestion::where('question_number', $questionNumber)->first();
    }

    /**
     * Update a single question.
     */
    public function updateQuestion(int $questionNumber, array $data, ?int $userId = null): DiscQuestion
    {
        $question = DiscQuestion::where('question_number', $questionNumber)->firstOrFail();

        $updateData = [];
        for ($i = 1; $i <= 4; $i++) {
            if (isset($data["statement_{$i}"])) {
                $updateData["statement_{$i}"] = $data["statement_{$i}"];
            }
            if (isset($data["p_key_{$i}"])) {
                $updateData["p_key_{$i}"] = strtoupper($data["p_key_{$i}"]);
            }
            if (isset($data["k_key_{$i}"])) {
                $updateData["k_key_{$i}"] = strtoupper($data["k_key_{$i}"]);
            }
        }

        if ($userId) {
            $updateData['updated_by'] = $userId;
        }

        $question->update($updateData);

        return $question->fresh();
    }

    /**
     * Bulk update multiple questions at once.
     */
    public function bulkUpdate(array $questionsData, ?int $userId = null): array
    {
        return DB::transaction(function () use ($questionsData, $userId) {
            $updated = [];
            foreach ($questionsData as $item) {
                $qNum = (int) ($item['question_number'] ?? 0);
                if ($qNum >= 1 && $qNum <= 24) {
                    $updated[] = $this->updateQuestion($qNum, $item, $userId);
                }
            }
            return $updated;
        });
    }

    /**
     * Reset questions to the canonical default from reference JSON.
     */
    public function resetToDefault(?int $userId = null): int
    {
        $jsonPath = database_path('data/disc_reference_data.json');
        if (!File::exists($jsonPath)) {
            $jsonPath = 'C:/Users/susan/AppData/Local/Packages/5319275A.WhatsAppDesktop_cv1g1gvanyjgm/LocalState/sessions/22E1CD11B9DE24BB945988E0814208F7A39A7A38/transfers/2026-44/disc_reference_data.json';
        }

        if (!File::exists($jsonPath)) {
            throw new \RuntimeException('Reference data file not found.');
        }

        $data = json_decode(File::get($jsonPath), true);
        $questions = $data['questions'] ?? [];

        return DB::transaction(function () use ($questions, $userId) {
            $count = 0;
            foreach ($questions as $q) {
                DiscQuestion::updateOrCreate(
                    ['question_number' => $q['number']],
                    [
                        'statement_1' => $q['statements'][0] ?? '',
                        'statement_2' => $q['statements'][1] ?? '',
                        'statement_3' => $q['statements'][2] ?? '',
                        'statement_4' => $q['statements'][3] ?? '',
                        'p_key_1'     => $q['P']['1'] ?? '',
                        'p_key_2'     => $q['P']['2'] ?? '',
                        'p_key_3'     => $q['P']['3'] ?? '',
                        'p_key_4'     => $q['P']['4'] ?? '',
                        'k_key_1'     => $q['K']['1'] ?? '',
                        'k_key_2'     => $q['K']['2'] ?? '',
                        'k_key_3'     => $q['K']['3'] ?? '',
                        'k_key_4'     => $q['K']['4'] ?? '',
                        'updated_by'  => $userId,
                    ]
                );
                $count++;
            }
            return $count;
        });
    }
}

