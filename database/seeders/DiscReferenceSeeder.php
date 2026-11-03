<?php

namespace Database\Seeders;

use App\Models\DiscProfile;
use App\Models\DiscQuestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DiscReferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = database_path('data/disc_reference_data.json');
        if (!File::exists($jsonPath)) {
            $fallback = 'C:/Users/susan/AppData/Local/Packages/5319275A.WhatsAppDesktop_cv1g1gvanyjgm/LocalState/sessions/22E1CD11B9DE24BB945988E0814208F7A39A7A38/transfers/2026-44/disc_reference_data.json';
            if (File::exists($fallback)) {
                $jsonPath = $fallback;
            } else {
                throw new \RuntimeException("Reference data JSON not found at: {$jsonPath}");
            }
        }

        $data = json_decode(File::get($jsonPath), true);

        // 1. Seed 24 Questions
        if (!empty($data['questions'])) {
            foreach ($data['questions'] as $q) {
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
                    ]
                );
            }
        }

        // 2. Seed 40 Profiles
        if (!empty($data['definitions'])) {
            foreach ($data['definitions'] as $def) {
                DiscProfile::updateOrCreate(
                    ['profile_number' => (int) $def['number']],
                    [
                        'title'          => $def['title'] ?? 'UNKNOWN',
                        'dimension_code' => $def['rows']['3'] ?? null,
                        'characteristics'=> $def['rows'] ?? [],
                    ]
                );
            }
        }
    }
}

