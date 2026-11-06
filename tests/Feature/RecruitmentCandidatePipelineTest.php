<?php

namespace Tests\Feature;

use App\Models\DiscAssessment;
use App\Models\DiscCandidate;
use App\Models\User;
use Database\Seeders\DiscReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecruitmentCandidatePipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hrUser = User::factory()->create();
        $this->seed(DiscReferenceSeeder::class);
    }

    public function test_candidate_registers_with_default_status_prescreen()
    {
        $payload = [
            'name'             => 'Andi Wijaya',
            'email'            => 'andi.wijaya@example.com',
            'phone'            => '081234567899',
            'password'         => 'secret1234',
            'position_applied' => 'Sales Supervisor',
        ];

        $response = $this->postJson('/api/v1/recruitment/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Registrasi kandidat berhasil.',
                'data'    => [
                    'candidate' => [
                        'full_name'             => 'Andi Wijaya',
                        'email'                 => 'andi.wijaya@example.com',
                        'status'                => 'prescreen',
                        'can_access_assessment' => false,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('disc_candidates', [
            'email'  => 'andi.wijaya@example.com',
            'status' => 'prescreen',
        ]);
    }

    public function test_prescreen_candidate_is_blocked_from_filling_biodata_and_disc_test()
    {
        $candidate = DiscCandidate::create([
            'full_name'        => 'Bambang Sudirman',
            'email'            => 'bambang@example.com',
            'phone'            => '081299990001',
            'password'         => Hash::make('password123'),
            'position_applied' => 'Operator Gudang',
            'status'           => DiscCandidate::STATUS_PRESCREEN,
        ]);

        DiscAssessment::create([
            'candidate_id' => $candidate->id,
            'token'        => 'test-token-123',
            'status'       => 'in_progress',
            'started_at'   => now(),
        ]);

        Sanctum::actingAs($candidate);

        // 1. Attempt to update biodata while prescreen -> 403 Forbidden
        $biodataResponse = $this->putJson('/api/v1/recruitment/candidate/biodata', [
            'position_applied' => 'Staff Gudang',
            'gender'           => 'Laki-laki',
            'biodata'          => ['pribadi' => ['kota' => 'Gresik']],
        ]);

        $biodataResponse->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'message' => "Status akun Anda masih 'prescreen'. Pengisian biodata dan pengerjaan asesmen hanya dapat diakses setelah akun Anda berstatus 'shortlist' oleh tim HR.",
            ]);

        // 2. Attempt to get test questions -> 403 Forbidden
        $testResponse = $this->getJson('/api/v1/recruitment/psikotes/disc/test');
        $testResponse->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'message' => "Status akun Anda masih 'prescreen'. Pengisian biodata dan pengerjaan asesmen hanya dapat diakses setelah akun Anda berstatus 'shortlist' oleh tim HR.",
            ]);

        // 3. Attempt to save answer -> 403 Forbidden
        $answerResponse = $this->postJson('/api/v1/recruitment/psikotes/disc/answers', [
            'question_number' => 1,
            'p_choice'        => 1,
            'k_choice'        => 2,
        ]);
        $answerResponse->assertStatus(403);

        // 4. Attempt to submit -> 403 Forbidden
        $submitResponse = $this->postJson('/api/v1/recruitment/psikotes/disc/submit');
        $submitResponse->assertStatus(403);
    }

    public function test_hr_admin_can_list_candidates_with_filtering_and_stats()
    {
        Sanctum::actingAs($this->hrUser);

        // Create candidates with various statuses
        DiscCandidate::create([
            'full_name'        => 'Prescreen Candidate 1',
            'email'            => 'prescreen1@example.com',
            'phone'            => '0811111111',
            'status'           => DiscCandidate::STATUS_PRESCREEN,
            'position_applied' => 'Accountant',
            'password'         => Hash::make('password123'),
        ]);

        DiscCandidate::create([
            'full_name'        => 'Shortlisted Candidate 2',
            'email'            => 'shortlist2@example.com',
            'phone'            => '0822222222',
            'status'           => DiscCandidate::STATUS_SHORTLIST,
            'position_applied' => 'Backend Developer',
            'password'         => Hash::make('password123'),
        ]);

        DiscCandidate::create([
            'full_name'        => 'Rejected Candidate 3',
            'email'            => 'reject3@example.com',
            'phone'            => '0833333333',
            'status'           => DiscCandidate::STATUS_REJECT,
            'position_applied' => 'HR Staff',
            'password'         => Hash::make('password123'),
        ]);

        // 1. List all candidates
        $response = $this->getJson('/api/v1/hr/recruitment/candidates');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Daftar kandidat berhasil diambil.',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'stats' => [
                    'total',
                    'prescreen',
                    'shortlist',
                    'reject',
                    'hr_interview',
                    'user_interview',
                    'psikotes',
                    'final_interview',
                    'offering',
                    'join',
                ],
                'current_page',
                'total',
            ]);

        $this->assertEquals(3, $response->json('stats.total'));
        $this->assertEquals(1, $response->json('stats.prescreen'));
        $this->assertEquals(1, $response->json('stats.shortlist'));
        $this->assertEquals(1, $response->json('stats.reject'));

        // 2. Filter by status shortlist
        $shortlistOnly = $this->getJson('/api/v1/hr/recruitment/candidates?status=shortlist');
        $shortlistOnly->assertStatus(200);
        $this->assertCount(1, $shortlistOnly->json('data'));
        $this->assertEquals('Shortlisted Candidate 2', $shortlistOnly->json('data.0.full_name'));

        // 3. Search by name
        $searchResponse = $this->getJson('/api/v1/hr/recruitment/candidates?search=reject');
        $searchResponse->assertStatus(200);
        $this->assertCount(1, $searchResponse->json('data'));
        $this->assertEquals('Rejected Candidate 3', $searchResponse->json('data.0.full_name'));
    }

    public function test_hr_admin_can_view_candidate_detail()
    {
        Sanctum::actingAs($this->hrUser);

        $candidate = DiscCandidate::create([
            'full_name'        => 'Citra Kirana',
            'email'            => 'citra@example.com',
            'phone'            => '081233344455',
            'password'         => Hash::make('password123'),
            'position_applied' => 'Marketing',
            'status'           => DiscCandidate::STATUS_SHORTLIST,
            'biodata'          => [
                'pribadi' => ['kota' => 'Surabaya', 'hobi' => 'Design'],
            ],
        ]);

        $response = $this->getJson("/api/v1/hr/recruitment/candidates/{$candidate->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'id'                    => $candidate->id,
                    'full_name'             => 'Citra Kirana',
                    'email'                 => 'citra@example.com',
                    'status'                => 'shortlist',
                    'biodata_completed'     => true,
                    'can_access_assessment' => true,
                ],
            ]);
    }

    public function test_hr_admin_can_update_candidate_status()
    {
        Sanctum::actingAs($this->hrUser);

        $candidate = DiscCandidate::create([
            'full_name'        => 'Dodi Hermawan',
            'email'            => 'dodi@example.com',
            'phone'            => '08155556666',
            'password'         => Hash::make('password123'),
            'status'           => DiscCandidate::STATUS_PRESCREEN,
        ]);

        // Update status to shortlist
        $updateResponse = $this->putJson("/api/v1/hr/recruitment/candidates/{$candidate->id}/status", [
            'status' => 'shortlist',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'status'                => 'shortlist',
                    'can_access_assessment' => true,
                ],
            ]);

        $this->assertEquals('shortlist', $candidate->fresh()->status);

        // Update status to hr_interview
        $interviewResponse = $this->putJson("/api/v1/hr/recruitment/candidates/{$candidate->id}/status", [
            'status' => 'hr_interview',
        ]);
        $interviewResponse->assertStatus(200);
        $this->assertEquals('hr_interview', $candidate->fresh()->status);
    }

    public function test_hr_admin_shortlist_and_reject_actions()
    {
        Sanctum::actingAs($this->hrUser);

        $candidate = DiscCandidate::create([
            'full_name' => 'Eko Prasetyo',
            'email'     => 'eko@example.com',
            'phone'     => '08177778888',
            'password'  => Hash::make('password123'),
            'status'    => DiscCandidate::STATUS_PRESCREEN,
        ]);

        // 1. Call POST /shortlist
        $shortlistResponse = $this->postJson("/api/v1/hr/recruitment/candidates/{$candidate->id}/shortlist");
        $shortlistResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'status'                => 'shortlist',
                    'can_access_assessment' => true,
                ],
            ]);
        $this->assertEquals('shortlist', $candidate->fresh()->status);

        // Candidate can now fill biodata and take test
        Sanctum::actingAs($candidate->fresh());
        $biodataRes = $this->putJson('/api/v1/recruitment/candidate/biodata', [
            'gender' => 'Laki-laki',
        ]);
        $biodataRes->assertStatus(200);

        // 2. HR rejects candidate via POST /reject
        Sanctum::actingAs($this->hrUser);
        $rejectResponse = $this->postJson("/api/v1/hr/recruitment/candidates/{$candidate->id}/reject");
        $rejectResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'status'                => 'reject',
                    'can_access_assessment' => false,
                ],
            ]);
        $this->assertEquals('reject', $candidate->fresh()->status);

        // Candidate is now blocked again
        Sanctum::actingAs($candidate->fresh());
        $blockedRes = $this->putJson('/api/v1/recruitment/candidate/biodata', [
            'gender' => 'Laki-laki',
        ]);
        $blockedRes->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'message' => "Status akun Anda adalah 'reject'. Anda tidak dapat mengisi biodata atau mengerjakan asesmen.",
            ]);
    }

    public function test_updating_status_with_invalid_value_fails_validation()
    {
        Sanctum::actingAs($this->hrUser);

        $candidate = DiscCandidate::create([
            'full_name' => 'Fajar Sidik',
            'email'     => 'fajar@example.com',
            'phone'     => '08199990000',
            'password'  => Hash::make('password123'),
            'status'    => DiscCandidate::STATUS_PRESCREEN,
        ]);

        $response = $this->putJson("/api/v1/hr/recruitment/candidates/{$candidate->id}/status", [
            'status' => 'invalid_status_xyz',
        ]);

        $response->assertJson([
            'success'     => false,
            'status_code' => 422,
        ]);
    }

    public function test_distributor_channel_prefix_is_supported_for_hr_endpoints()
    {
        Sanctum::actingAs($this->hrUser);

        $response = $this->getJson('/api/distributor-channel/v1/hr/recruitment/candidates');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }
}
