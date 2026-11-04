<?php

namespace Tests\Feature;

use App\Models\DiscAssessment;
use App\Models\DiscCandidate;
use Database\Seeders\DiscReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiscCandidatePortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DiscReferenceSeeder::class);
    }

    public function test_candidate_can_register_and_receive_token()
    {
        $payload = [
            'name'             => 'Nadia Permata',
            'email'            => 'nadia.permata@example.com',
            'phone'            => '081234567890',
            'password'         => 'password123',
            'position_applied' => 'Backend Engineer',
        ];

        $response = $this->postJson('/api/v1/recruitment/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Registrasi kandidat berhasil.',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'candidate' => [
                        'id',
                        'full_name',
                        'email',
                        'phone',
                        'position_applied',
                        'assessment_status',
                    ],
                    'token',
                    'token_type',
                ],
            ]);

        $this->assertDatabaseHas('disc_candidates', [
            'email'     => 'nadia.permata@example.com',
            'full_name' => 'Nadia Permata',
        ]);

        $candidate = DiscCandidate::where('email', 'nadia.permata@example.com')->first();
        $this->assertTrue(Hash::check('password123', $candidate->password));

        // Ensure DISC assessment was auto-created
        $this->assertDatabaseHas('disc_assessments', [
            'candidate_id' => $candidate->id,
            'status'       => 'in_progress',
        ]);
    }

    public function test_candidate_can_login_with_valid_credentials()
    {
        $candidate = DiscCandidate::create([
            'full_name' => 'Budi Santoso',
            'email'     => 'budi@example.com',
            'phone'     => '081987654321',
            'password'  => Hash::make('secret123'),
        ]);

        // Login with correct password
        $response = $this->postJson('/api/v1/recruitment/auth/login', [
            'email'    => 'budi@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Login berhasil.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'candidate' => ['id', 'full_name', 'email'],
                    'token',
                    'token_type',
                ],
            ]);

        // Login with wrong password
        $failResponse = $this->postJson('/api/v1/recruitment/auth/login', [
            'email'    => 'budi@example.com',
            'password' => 'wrongpassword',
        ]);

        $failResponse->assertStatus(401)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Email atau kata sandi tidak sesuai.',
            ]);
    }

    public function test_candidate_can_get_me_profile()
    {
        $candidate = DiscCandidate::create([
            'full_name'        => 'Dewi Lestari',
            'email'            => 'dewi@example.com',
            'phone'            => '08111222333',
            'password'         => Hash::make('password123'),
            'position_applied' => 'HR Specialist',
        ]);

        Sanctum::actingAs($candidate);

        $response = $this->getJson('/api/v1/recruitment/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'candidate' => [
                        'id'               => $candidate->id,
                        'full_name'        => 'Dewi Lestari',
                        'email'            => 'dewi@example.com',
                        'position_applied' => 'HR Specialist',
                    ],
                ],
            ]);
    }

    public function test_candidate_can_update_biodata()
    {
        $candidate = DiscCandidate::create([
            'full_name' => 'Ahmad Fauzi',
            'email'     => 'ahmad@example.com',
            'phone'     => '082233445566',
            'password'  => Hash::make('password123'),
        ]);

        Sanctum::actingAs($candidate);

        $payload = [
            'position_applied' => 'Frontend Developer',
            'birth_date'       => '1998-05-15',
            'gender'           => 'Laki-laki',
            'biodata'          => [
                'pribadi' => [
                    'alamat_ktp' => 'Jl. Rungkut Industri No. 1',
                    'kota'       => 'Surabaya',
                    'hobi'       => 'Coding & membaca',
                ],
                'pendidikan' => [
                    ['tingkat' => 'S1', 'institusi' => 'ITS', 'jurusan' => 'Informatika'],
                ],
            ],
        ];

        $response = $this->putJson('/api/v1/recruitment/candidate/biodata', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Biodata kandidat berhasil disimpan.',
            ]);

        $candidate->refresh();
        $this->assertEquals('Frontend Developer', $candidate->position_applied);
        $this->assertEquals('Surabaya', $candidate->biodata['pribadi']['kota']);
        $this->assertEquals('ITS', $candidate->biodata['pendidikan'][0]['institusi']);
    }

    public function test_candidate_can_take_disc_test_without_token_in_url()
    {
        $candidate = DiscCandidate::create([
            'full_name' => 'Rina Kartika',
            'email'     => 'rina@example.com',
            'phone'     => '081334455667',
            'password'  => Hash::make('password123'),
        ]);

        Sanctum::actingAs($candidate);

        // 1. Get DISC test questions without token in URL
        $testResponse = $this->getJson('/api/v1/recruitment/psikotes/disc/test');

        $testResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $testData = $testResponse->json('data');
        $this->assertCount(24, $testData['questions']);
        $this->assertEquals(24, $testData['total_questions']);
        $this->assertFalse($testData['completed']);

        // Check scoring keys are hidden from candidate
        $firstQuestion = $testData['questions'][0];
        $this->assertArrayNotHasKey('p_key_1', $firstQuestion);
        $this->assertArrayNotHasKey('k_key_1', $firstQuestion);

        // 2. Autosave single answer without token in URL
        $saveResponse = $this->postJson('/api/v1/recruitment/psikotes/disc/answers', [
            'question_number' => 1,
            'p_choice'        => 2,
            'k_choice'        => 4,
        ]);

        $saveResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'question_number' => 1,
                    'p_choice'        => 2,
                    'k_choice'        => 4,
                ],
            ]);

        // 3. Validation rule: P cannot equal K
        $invalidResponse = $this->postJson('/api/v1/recruitment/psikotes/disc/answers', [
            'question_number' => 2,
            'p_choice'        => 3,
            'k_choice'        => 3,
        ]);

        $invalidResponse->assertStatus(200)
            ->assertJson([
                'success'     => false,
                'status_code' => 422,
            ]);
    }

    public function test_candidate_can_complete_and_submit_disc_assessment()
    {
        $candidate = DiscCandidate::create([
            'full_name' => 'Gita Gutawa',
            'email'     => 'gita@example.com',
            'phone'     => '081999888777',
            'password'  => Hash::make('password123'),
        ]);

        Sanctum::actingAs($candidate);

        // Fill all 24 questions with valid answers
        for ($i = 1; $i <= 24; $i++) {
            $this->postJson('/api/v1/recruitment/psikotes/disc/answers', [
                'question_number' => $i,
                'p_choice'        => 1,
                'k_choice'        => 4,
            ])->assertStatus(200);
        }

        // Submit test without token in URL
        $submitResponse = $this->postJson('/api/v1/recruitment/psikotes/disc/submit');

        $submitResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Assessment submitted successfully.',
            ]);

        // Verify result endpoint without token in URL
        $resultResponse = $this->getJson('/api/v1/recruitment/psikotes/disc/result');

        $resultResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $resultData = $resultResponse->json('data.result');
        $this->assertNotNull($resultData);
        $this->assertArrayHasKey('chart_scores', $resultData);
        $this->assertArrayHasKey('profile_number', $resultData);
    }

    public function test_candidate_can_logout()
    {
        $candidate = DiscCandidate::create([
            'full_name' => 'Ferry Salim',
            'email'     => 'ferry@example.com',
            'phone'     => '0812344321',
            'password'  => Hash::make('password123'),
        ]);

        $token = $candidate->createToken('candidate_auth')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/recruitment/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Logout berhasil.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_endpoints_accessible_via_distributor_channel_prefix()
    {
        $response = $this->postJson('/api/distributor-channel/v1/recruitment/auth/register', [
            'name'     => 'Prefix Test User',
            'email'    => 'prefix@example.com',
            'phone'    => '081299887766',
            'password' => 'secret123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ]);
    }
}

