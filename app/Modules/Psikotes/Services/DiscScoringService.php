<?php

namespace App\Modules\Psikotes\Services;

use App\Models\DiscProfile;
use App\Models\DiscQuestion;
use Illuminate\Support\Facades\File;

class DiscScoringService
{
    protected ?array $lookupTables = null;

    public function __construct()
    {
        $this->loadLookupTables();
    }

    protected function loadLookupTables(): void
    {
        $jsonPath = database_path('data/disc_reference_data.json');
        if (File::exists($jsonPath)) {
            $data = json_decode(File::get($jsonPath), true);
            $this->lookupTables = $data['lookup_tables'] ?? [];
        }
    }

    /**
     * Compute raw counts (P, K, NET) from candidate answers.
     * $answers is an array of items: [['question_number' => 1, 'p_choice' => 2, 'k_choice' => 3], ...]
     * Returns associative array with 'p_counts', 'k_counts', 'net_counts'.
     */
    public function computeCounts(array $answers): array
    {
        // Load questions with their scoring keys
        $questions = DiscQuestion::all()->keyBy('question_number');

        $pCounts = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0, '*' => 0, 'total' => 0];
        $kCounts = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0, '*' => 0, 'total' => 0];

        foreach ($answers as $ans) {
            $qNum = (int) ($ans['question_number'] ?? 0);
            $pChoice = (int) ($ans['p_choice'] ?? 0);
            $kChoice = (int) ($ans['k_choice'] ?? 0);

            $question = $questions->get($qNum);
            if (!$question) {
                continue;
            }

            // Map P choice (1..4) to key
            if ($pChoice >= 1 && $pChoice <= 4) {
                $pKey = $question->{"p_key_{$pChoice}"} ?? '*';
                if (!isset($pCounts[$pKey])) {
                    $pKey = '*';
                }
                $pCounts[$pKey]++;
                $pCounts['total']++;
            }

            // Map K choice (1..4) to key
            if ($kChoice >= 1 && $kChoice <= 4) {
                $kKey = $question->{"k_key_{$kChoice}"} ?? '*';
                if (!isset($kCounts[$kKey])) {
                    $kKey = '*';
                }
                $kCounts[$kKey]++;
                $kCounts['total']++;
            }
        }

        $netCounts = [
            'D' => $pCounts['D'] - $kCounts['D'],
            'I' => $pCounts['I'] - $kCounts['I'],
            'S' => $pCounts['S'] - $kCounts['S'],
            'C' => $pCounts['C'] - $kCounts['C'],
        ];

        return [
            'p_counts'   => $pCounts,
            'k_counts'   => $kCounts,
            'net_counts' => $netCounts,
        ];
    }

    /**
     * Map raw counts to chart scores across Line 1 (P), Line 2 (K), and Line 3 (NET).
     */
    public function computeChartScores(array $pCounts, array $kCounts, array $netCounts): array
    {
        $line1 = [
            'D' => $this->lookupLine1('D', $pCounts['D'] ?? 0),
            'I' => $this->lookupLine1('I', $pCounts['I'] ?? 0),
            'S' => $this->lookupLine1('S', $pCounts['S'] ?? 0),
            'C' => $this->lookupLine1('C', $pCounts['C'] ?? 0),
        ];

        $line2 = [
            'D' => $this->lookupLine2('D', $kCounts['D'] ?? 0),
            'I' => $this->lookupLine2('I', $kCounts['I'] ?? 0),
            'S' => $this->lookupLine2('S', $kCounts['S'] ?? 0),
            'C' => $this->lookupLine2('C', $kCounts['C'] ?? 0),
        ];

        $line3 = [
            'D' => $this->lookupLine3('D', $netCounts['D'] ?? 0),
            'I' => $this->lookupLine3('I', $netCounts['I'] ?? 0),
            'S' => $this->lookupLine3('S', $netCounts['S'] ?? 0),
            'C' => $this->lookupLine3('C', $netCounts['C'] ?? 0),
        ];

        return [
            'line1' => $line1,
            'line2' => $line2,
            'line3' => $line3,
        ];
    }

    protected function lookupLine1(string $dimension, int $count): float
    {
        $table = $this->lookupTables['line1'][$dimension] ?? [];
        $maxIndex = count($table) - 1;
        if ($maxIndex < 0) {
            return 0.0;
        }
        $index = max(0, min($count, $maxIndex));
        return (float) ($table[$index] ?? 0.0);
    }

    protected function lookupLine2(string $dimension, int $count): float
    {
        $table = $this->lookupTables['line2'][$dimension] ?? [];
        $maxIndex = count($table) - 1;
        if ($maxIndex < 0) {
            return 0.0;
        }
        $index = max(0, min($count, $maxIndex));
        return (float) ($table[$index] ?? 0.0);
    }

    protected function lookupLine3(string $dimension, int $net): float
    {
        $table = $this->lookupTables['line3'][$dimension] ?? [];
        $netClamped = max(-22, min(22, $net));
        $key = (string) $netClamped;
        return (float) ($table[$key] ?? 0.0);
    }

    /**
     * Evaluate the 40 DISC classification rules for a given set of scores (D, I, S, C).
     * Returns matching rule index (1..40) or null if no rule matches.
     */
    public function evaluateRules(array $scores): ?int
    {
        $d = (float) ($scores['D'] ?? 0.0);
        $i = (float) ($scores['I'] ?? 0.0);
        $s = (float) ($scores['S'] ?? 0.0);
        $c = (float) ($scores['C'] ?? 0.0);

        // Rule 1: BB<=0, BC<=0, BD<=0, BE>0 (Only C > 0)
        if ($d <= 0 && $i <= 0 && $s <= 0 && $c > 0) return 1;

        // Rule 2: BB>0, BC<=0, BD<=0, BE<=0 (Only D > 0)
        if ($d > 0 && $i <= 0 && $s <= 0 && $c <= 0) return 2;

        // Rule 3: BB>0, BC<=0, BD<=0, BE>0, BE>=BB (C and D > 0, C >= D)
        if ($d > 0 && $i <= 0 && $s <= 0 && $c > 0 && $c >= $d) return 3;

        // Rule 4: BB>0, BC>0, BD<=0, BE<=0, BC>=BB (I and D > 0, I >= D)
        if ($d > 0 && $i > 0 && $s <= 0 && $c <= 0 && $i >= $d) return 4;

        // Rule 5: BB>0, BC>0, BD<=0, BE>0, BC>=BB>=BE (I, D, C > 0, I >= D >= C)
        if ($d > 0 && $i > 0 && $s <= 0 && $c > 0 && $i >= $d && $d >= $c) return 5;

        // Rule 6: BB>0, BC>0, BD>0, BE<=0, BC>=BB>=BD (I, D, S > 0, I >= D >= S)
        if ($d > 0 && $i > 0 && $s > 0 && $c <= 0 && $i >= $d && $d >= $s) return 6;

        // Rule 7: BB>0, BC>0, BD>0, BE<=0, BC>=BD>=BB (I, D, S > 0, I >= S >= D)
        if ($d > 0 && $i > 0 && $s > 0 && $c <= 0 && $i >= $s && $s >= $d) return 7;

        // Rule 8: BB>0, BC<=0, BD>0, BE>0, BD>=BB, BB>=BE (S, D, C > 0, S >= D >= C)
        if ($d > 0 && $i <= 0 && $s > 0 && $c > 0 && $s >= $d && $d >= $c) return 8;

        // Rule 9: BB>0, BC>0, BD<=0, BE<=0, BB>=BC (D and I > 0, D >= I)
        if ($d > 0 && $i > 0 && $s <= 0 && $c <= 0 && $d >= $i) return 9;

        // Rule 10: BB>0, BC>0, BD>0, BE<=0, BB>=BC>=BD (D, I, S > 0, D >= I >= S)
        if ($d > 0 && $i > 0 && $s > 0 && $c <= 0 && $d >= $i && $i >= $s) return 10;

        // Rule 11: BB>0, BC<=0, BD>0, BE<=0, BB>=BD (D and S > 0, D >= S)
        if ($d > 0 && $i <= 0 && $s > 0 && $c <= 0 && $d >= $s) return 11;

        // Rule 12: BB<=0, BC>0, BD>0, BE>0, BE>=BC>=BD (C, I, S > 0, C >= I >= S)
        if ($d <= 0 && $i > 0 && $s > 0 && $c > 0 && $c >= $i && $i >= $s) return 12;

        // Rule 13: BB<=0, BC>0, BD>0, BE>0, BE>=BD>=BC (C, I, S > 0, C >= S >= I)
        if ($d <= 0 && $i > 0 && $s > 0 && $c > 0 && $c >= $s && $s >= $i) return 13;

        // Rule 14: BB<=0, BC>0, BD>0, BE>0, BC>=BD, BC>=BE (I, S, C > 0, I >= S && I >= C)
        if ($d <= 0 && $i > 0 && $s > 0 && $c > 0 && $i >= $s && $i >= $c) return 14;

        // Rule 15: BB<=0, BC<=0, BD>0, BE<=0 (Only S > 0)
        if ($d <= 0 && $i <= 0 && $s > 0 && $c <= 0) return 15;

        // Rule 16: BB<=0, BC<=0, BD>0, BE>0, BE>=BD (S and C > 0, C >= S)
        if ($d <= 0 && $i <= 0 && $s > 0 && $c > 0 && $c >= $s) return 16;

        // Rule 17: BB<=0, BC<=0, BD>0, BE>0, BD>=BE (S and C > 0, S >= C)
        if ($d <= 0 && $i <= 0 && $s > 0 && $c > 0 && $s >= $c) return 17;

        // Rule 18: BC<=0, BD<=0, BB>0, BE>0, BB>=BE (D and C > 0, D >= C)
        if ($d > 0 && $i <= 0 && $s <= 0 && $c > 0 && $d >= $c) return 18;

        // Rule 19: BB>0, BC>0, BE>0, BD<=0, BB>=BC>=BE (D, I, C > 0, D >= I >= C)
        if ($d > 0 && $i > 0 && $s <= 0 && $c > 0 && $d >= $i && $i >= $c) return 19;

        // Rule 20: BB>0, BD>0, BC>0, BE<=0, BB>=BD>=BC (D, S, I > 0, D >= S >= I)
        if ($d > 0 && $i > 0 && $s > 0 && $c <= 0 && $d >= $s && $s >= $i) return 20;

        // Rule 21: BB>0, BD>0, BE>0, BC<=0, BB>=BD, BD>=BE (D, S, C > 0, D >= S >= C)
        if ($d > 0 && $i <= 0 && $s > 0 && $c > 0 && $d >= $s && $s >= $c) return 21;

        // Rule 22: BB>0, BC>0, BE>0, BD<=0, BB>=BE, BE>=BC (D, C, I > 0, D >= C >= I)
        if ($d > 0 && $i > 0 && $s <= 0 && $c > 0 && $d >= $c && $c >= $i) return 22;

        // Rule 23: BB>0, BD>0, BE>0, BC<=0, BB>=BE, BE>=BD (D, C, S > 0, D >= C >= S)
        if ($d > 0 && $i <= 0 && $s > 0 && $c > 0 && $d >= $c && $c >= $s) return 23;

        // Rule 24: BB<=0, BD<=0, BE<=0, BC>0 (Only I > 0)
        if ($d <= 0 && $i > 0 && $s <= 0 && $c <= 0) return 24;

        // Rule 25: BC>0, BD>0, BB<=0, BE<=0, BC>=BD (I and S > 0, I >= S)
        if ($d <= 0 && $i > 0 && $s > 0 && $c <= 0 && $i >= $s) return 25;

        // Rule 26: BC>0, BE>0, BB<=0, BD<=0, BC>=BE (I and C > 0, I >= C)
        if ($d <= 0 && $i > 0 && $s <= 0 && $c > 0 && $i >= $c) return 26;

        // Rule 27: BB>0, BC>0, BE>0, BD<=0, BC>=BE, BE>=BB (I, C, D > 0, I >= C >= D)
        if ($d > 0 && $i > 0 && $s <= 0 && $c > 0 && $i >= $c && $c >= $d) return 27;

        // Rule 28: BB<=0, BC>0, BD>0, BE>0, BC>=BE, BE>=BD (I, C, S > 0, I >= C >= S)
        if ($d <= 0 && $i > 0 && $s > 0 && $c > 0 && $i >= $c && $c >= $s) return 28;

        // Rule 29: BB>0, BC<=0, BD>0, BE<=0, BD>=BB (S and D > 0, S >= D)
        if ($d > 0 && $i <= 0 && $s > 0 && $c <= 0 && $s >= $d) return 29;

        // Rule 30: BC>0, BD>0, BB<=0, BE<=0, BD>=BC (S and I > 0, S >= I)
        if ($d <= 0 && $i > 0 && $s > 0 && $c <= 0 && $s >= $i) return 30;

        // Rule 31: BB>0, BC>0, BD>0, BE<=0, BD>=BB, BB>=BC (S, D, I > 0, S >= D >= I)
        if ($d > 0 && $i > 0 && $s > 0 && $c <= 0 && $s >= $d && $d >= $i) return 31;

        // Rule 32: BB>0, BC>0, BD>0, BE<=0, BD>=BC, BC>=BB (S, I, D > 0, S >= I >= D)
        if ($d > 0 && $i > 0 && $s > 0 && $c <= 0 && $s >= $i && $i >= $d) return 32;

        // Rule 33: BC>0, BD>0, BE>0, BB<=0, BD>=BC, BC>=BE (S, I, C > 0, S >= I >= C)
        if ($d <= 0 && $i > 0 && $s > 0 && $c > 0 && $s >= $i && $i >= $c) return 33;

        // Rule 34: BB>0, BC<=0, BD>0, BE>0, BD>=BE, BE>=BB (S, C, D > 0, S >= C >= D)
        if ($d > 0 && $i <= 0 && $s > 0 && $c > 0 && $s >= $c && $c >= $d) return 34;

        // Rule 35: BC>0, BD>0, BE>0, BB<=0, BD>=BE, BE>=BC (S, C, I > 0, S >= C >= I)
        if ($d <= 0 && $i > 0 && $s > 0 && $c > 0 && $s >= $c && $c >= $i) return 35;

        // Rule 36: BC>0, BE>0, BB<=0, BD<=0, BE>=BC (C and I > 0, C >= I)
        if ($d <= 0 && $i > 0 && $s <= 0 && $c > 0 && $c >= $i) return 36;

        // Rule 37: BB>0, BC>0, BE>0, BD<=0, BE>=BB, BB>=BC (C, D, I > 0, C >= D >= I)
        if ($d > 0 && $i > 0 && $s <= 0 && $c > 0 && $c >= $d && $d >= $i) return 37;

        // Rule 38: BB>0, BD>0, BE>0, BC<=0, BE>=BB, BB>=BD (C, D, S > 0, C >= D >= S)
        if ($d > 0 && $i <= 0 && $s > 0 && $c > 0 && $c >= $d && $d >= $s) return 38;

        // Rule 39: BB>0, BC>0, BE>0, BD<=0, BE>=BC, BC>=BB (C, I, D > 0, C >= I >= D)
        if ($d > 0 && $i > 0 && $s <= 0 && $c > 0 && $c >= $i && $i >= $d) return 39;

        // Rule 40: BB>0, BD>0, BE>0, BC<=0, BE>=BD, BD>=BB (C, S, D > 0, C >= S >= D)
        if ($d > 0 && $i <= 0 && $s > 0 && $c > 0 && $c >= $s && $s >= $d) return 40;

        return null; // Ambiguous or undefined pattern
    }

    /**
     * Complete calculation and profile determination.
     */
    public function scoreAssessment(array $answers): array
    {
        $counts = $this->computeCounts($answers);
        $chartScores = $this->computeChartScores(
            $counts['p_counts'],
            $counts['k_counts'],
            $counts['net_counts']
        );

        // Primary profile matches Line 3 (Mirror/Perceived self)
        $matchedRuleIndex = $this->evaluateRules($chartScores['line3']);
        
        // Secondary profile matches Line 1 (Mask/Public self) and Line 2 (Core/Private self)
        $line1Rule = $this->evaluateRules($chartScores['line1']);
        $line2Rule = $this->evaluateRules($chartScores['line2']);

        $profile = null;
        if ($matchedRuleIndex !== null) {
            $profile = DiscProfile::where('profile_number', $matchedRuleIndex)->first();
        }

        $status = ($profile !== null) ? 'valid' : 'review_required';

        return [
            'p_counts'           => $counts['p_counts'],
            'k_counts'           => $counts['k_counts'],
            'net_counts'         => $counts['net_counts'],
            'chart_scores'       => $chartScores,
            'matched_rule_index' => $matchedRuleIndex,
            'line1_rule_index'   => $line1Rule,
            'line2_rule_index'   => $line2Rule,
            'profile_id'         => $profile?->id,
            'profile_number'     => $profile?->profile_number,
            'profile_title'      => $profile?->title,
            'dimension_code'     => $profile?->dimension_code,
            'profile_details'    => $profile?->characteristics,
            'status'             => $status,
        ];
    }
}

