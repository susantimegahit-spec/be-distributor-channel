<?php

namespace Tests\Feature;

use App\Models\DiscAssessment;
use App\Models\DiscCandidate;
use App\Models\DiscProfile;
use App\Models\DiscQuestion;
use App\Models\User;
use Database\Seeders\DiscReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiscPsikotesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->seed(DiscReferenceSeeder::class);
    }

    public function test_admin_can_retrieve_all_disc_questions_with_scoring_keys()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/hr/psikotes/disc/questions');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertCount(24, $response->json('data'));
        $first = $response->json('data.0');
        $this->assertEquals(1, $first['question_number']);
        $this->assertArrayHasKey('statement_1', $first);
        $this->assertArrayHasKey('p_key_1', $first);
        $this->assertArrayHasKey('k_key_1', $first);
    }

    public function test_admin_can_update_single_disc_question()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'statement_1' => 'Pernyataan satu terupdate',
            'p_key_1'     => 'D',
            'k_key_1'     => 'C',
        ];

        $response = $this->putJson('/api/v1/hr/psikotes/disc/questions/1', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'question_number' => 1,
                    'statement_1'     => 'Pernyataan satu terupdate',
                    'p_key_1'         => 'D',
                    'k_key_1'         => 'C',
                ],
            ]);

        $this->assertDatabaseHas('disc_questions', [
            'question_number' => 1,
            'statement_1'     => 'Pernyataan satu terupdate',
            'p_key_1'         => 'D',
            'k_key_1'         => 'C',
        ]);
    }

    public function test_admin_can_bulk_update_disc_questions()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'questions' => [
                [
                    'question_number' => 1,
                    'statement_1'     => 'Soal satu bulk A',
                ],
                [
                    'question_number' => 2,
                    'statement_1'     => 'Soal dua bulk B',
                ],
            ],
        ];

        $response = $this->putJson('/api/v1/hr/psikotes/disc/questions/bulk', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('disc_questions', [
            'question_number' => 1,
            'statement_1'     => 'Soal satu bulk A',
        ]);
        $this->assertDatabaseHas('disc_questions', [
            'question_number' => 2,
            'statement_1'     => 'Soal dua bulk B',
        ]);
    }

    public function test_admin_can_seed_default_questions()
    {
        Sanctum::actingAs($this->user);

        // Modify question 1 first
        DiscQuestion::where('question_number', 1)->update(['statement_1' => 'Terganti']);

        $response = $this->postJson('/api/v1/hr/psikotes/disc/questions/seed-default');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $q1 = DiscQuestion::where('question_number', 1)->first();
        $this->assertNotEquals('Terganti', $q1->statement_1);
    }

    public function test_admin_can_create_candidate_assessment_invitation()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'full_name'        => 'Budi Santoso',
            'email'            => 'budi.santoso@example.com',
            'phone'            => '081234567890',
            'gender'           => 'L',
            'position_applied' => 'Sales Executive',
            'expiry_days'      => 14,
        ];

        $response = $this->postJson('/api/v1/hr/psikotes/disc/assessments', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'candidate' => [
                        'full_name'        => 'Budi Santoso',
                        'email'            => 'budi.santoso@example.com',
                        'position_applied' => 'Sales Executive',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('disc_candidates', [
            'email' => 'budi.santoso@example.com',
        ]);

        $this->assertDatabaseHas('disc_assessments', [
            'status' => 'in_progress',
        ]);
    }

    public function test_candidate_can_fetch_test_questions_without_scoring_keys()
    {
        $candidate = DiscCandidate::create([
            'full_name' => 'Siti Rahma',
            'email'     => 'siti@example.com',
        ]);

        $assessment = DiscAssessment::create([
            'candidate_id' => $candidate->id,
            'token'        => 'valid-candidate-token-1234567890',
            'status'       => 'in_progress',
        ]);

        $response = $this->getJson("/api/v1/public/psikotes/disc/tests/{$assessment->token}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total_questions' => 24,
                    'completed'       => false,
                ],
            ]);

        $questions = $response->json('data.questions');
        $this->assertCount(24, $questions);
        $firstQuestion = $questions[0];

        // Ensure sensitive scoring keys are NOT exposed to candidate
        $this->assertArrayNotHasKey('p_key_1', $firstQuestion);
        $this->assertArrayNotHasKey('k_key_1', $firstQuestion);
        $this->assertArrayHasKey('statements', $firstQuestion);
        $this->assertCount(4, $firstQuestion['statements']);
    }

    public function test_candidate_can_autosave_answer()
    {
        $candidate = DiscCandidate::create([
            'full_name' => 'Joko Susanto',
            'email'     => 'joko@example.com',
        ]);

        $assessment = DiscAssessment::create([
            'candidate_id' => $candidate->id,
            'token'        => 'candidate-token-autosave-999',
            'status'       => 'in_progress',
        ]);

        $payload = [
            'question_number' => 1,
            'p_choice'        => 1,
            'k_choice'        => 4,
        ];

        $response = $this->postJson("/api/v1/public/psikotes/disc/tests/{$assessment->token}/answers", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'question_number' => 1,
                    'p_choice'        => 1,
                    'k_choice'        => 4,
                ],
            ]);

        $this->assertDatabaseHas('disc_answers', [
            'assessment_id'   => $assessment->id,
            'question_number' => 1,
            'p_choice'        => 1,
            'k_choice'        => 4,
        ]);
    }

    public function test_candidate_cannot_choose_same_option_for_p_and_k()
    {
        $candidate = DiscCandidate::create([
            'full_name' => 'Ahmad',
            'email'     => 'ahmad@example.com',
        ]);

        $assessment = DiscAssessment::create([
            'candidate_id' => $candidate->id,
            'token'        => 'candidate-token-same-choice',
            'status'       => 'in_progress',
        ]);

        $payload = [
            'question_number' => 2,
            'p_choice'        => 3,
            'k_choice'        => 3, // P and K are identical
        ];

        $response = $this->postJson("/api/v1/public/psikotes/disc/tests/{$assessment->token}/answers", $payload);

        $response->assertJson([
            'success'     => false,
            'status_code' => 422,
        ]);
        $this->assertStringContainsString('tidak boleh sama', $response->json('message'));
    }

    public function test_candidate_cannot_submit_incomplete_assessment()
    {
        $candidate = DiscCandidate::create([
            'full_name' => 'Rina',
            'email'     => 'rina@example.com',
        ]);

        $assessment = DiscAssessment::create([
            'candidate_id' => $candidate->id,
            'token'        => 'candidate-token-incomplete',
            'status'       => 'in_progress',
        ]);

        // Only answer question 1
        $this->postJson("/api/v1/public/psikotes/disc/tests/{$assessment->token}/answers", [
            'question_number' => 1,
            'p_choice'        => 1,
            'k_choice'        => 2,
        ]);

        $response = $this->postJson("/api/v1/public/psikotes/disc/tests/{$assessment->token}/submit");

        $response->assertJson([
            'success'     => false,
            'status_code' => 422,
        ]);
        $this->assertStringContainsString('lengkapi seluruh 24 soal', $response->json('message'));
    }

    public function test_candidate_can_submit_completed_assessment_and_receive_valid_score()
    {
        $candidate = DiscCandidate::create([
            'full_name' => 'Dewi Lestari',
            'email'     => 'dewi@example.com',
        ]);

        $assessment = DiscAssessment::create([
            'candidate_id' => $candidate->id,
            'token'        => 'candidate-token-complete-test',
            'status'       => 'in_progress',
        ]);

        // Answer all 24 questions (P=1, K=2)
        for ($q = 1; $q <= 24; $q++) {
            $this->postJson("/api/v1/public/psikotes/disc/tests/{$assessment->token}/answers", [
                'question_number' => $q,
                'p_choice'        => 1,
                'k_choice'        => 2,
            ]);
        }

        $response = $this->postJson("/api/v1/public/psikotes/disc/tests/{$assessment->token}/submit");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => 'scored',
                ],
            ]);

        $this->assertDatabaseHas('disc_assessments', [
            'id'     => $assessment->id,
            'status' => 'scored',
        ]);

        $this->assertDatabaseHas('disc_assessment_results', [
            'assessment_id' => $assessment->id,
            'status'        => 'valid',
        ]);
    }

    public function test_admin_can_view_assessment_result_detail()
    {
        Sanctum::actingAs($this->user);

        $candidate = DiscCandidate::create([
            'full_name' => 'Hendra Setiawan',
            'email'     => 'hendra@example.com',
        ]);

        $assessment = DiscAssessment::create([
            'candidate_id' => $candidate->id,
            'token'        => 'token-view-detail-123',
            'status'       => 'in_progress',
        ]);

        for ($q = 1; $q <= 24; $q++) {
            $this->postJson("/api/v1/public/psikotes/disc/tests/{$assessment->token}/answers", [
                'question_number' => $q,
                'p_choice'        => 1,
                'k_choice'        => 2,
            ]);
        }

        $this->postJson("/api/v1/public/psikotes/disc/tests/{$assessment->token}/submit");

        $response = $this->getJson("/api/v1/hr/psikotes/disc/assessments/{$assessment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id'     => $assessment->id,
                    'status' => 'scored',
                    'candidate' => [
                        'full_name' => 'Hendra Setiawan',
                        'email'     => 'hendra@example.com',
                    ],
                ],
            ]);

        $this->assertNotNull($response->json('data.result.profile_title'));
        $this->assertNotNull($response->json('data.result.chart_scores.line1'));
        $this->assertNotNull($response->json('data.result.chart_scores.line2'));
        $this->assertNotNull($response->json('data.result.chart_scores.line3'));
    }

    public function test_admin_can_export_assessments_to_csv()
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/api/v1/hr/psikotes/disc/assessments-export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
}
