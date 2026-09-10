<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PiSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PiSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user
        $this->user = User::create([
            'name' => 'Test Admin',
            'username' => 'admin_test',
            'email' => 'admin_test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test_token')->plainTextToken;
    }

    private function getAuthHeader(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }

    /**
     * Test getting the default PI setting.
     */
    public function test_get_default_pi_setting(): void
    {
        $response = $this->withHeaders($this->getAuthHeader())
            ->getJson('/api/distributor-channel/v1/pi-settings');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'signer_name' => 'Kushan Wijono',
            'signer_title' => 'Branch Manager',
            'user_id' => null,
            'document_tag' => null,
        ]);
    }

    /**
     * Test updating the default PI setting.
     */
    public function test_update_default_pi_setting(): void
    {
        $payload = [
            'signer_name' => 'John Doe',
            'signer_title' => 'Director',
        ];

        $response = $this->withHeaders($this->getAuthHeader())
            ->postJson('/api/distributor-channel/v1/pi-settings', $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'signer_name' => 'John Doe',
            'signer_title' => 'Director',
            'user_id' => null,
            'document_tag' => null,
        ]);

        $this->assertDatabaseHas('pi_settings', [
            'signer_name' => 'John Doe',
            'signer_title' => 'Director',
            'user_id' => null,
            'document_tag' => null,
        ]);
    }

    /**
     * Test creating and retrieving PI setting with user_id and document_tag.
     */
    public function test_pi_setting_with_user_and_tag(): void
    {
        // 1. Create signature setting for specific user and document tag
        $payload = [
            'user_id' => $this->user->id,
            'document_tag' => 'INVOICE',
            'signer_name' => 'Jane Smith',
            'signer_title' => 'Finance Lead',
        ];

        $response = $this->withHeaders($this->getAuthHeader())
            ->postJson('/api/distributor-channel/v1/pi-settings', $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'user_id' => $this->user->id,
            'document_tag' => 'INVOICE',
            'signer_name' => 'Jane Smith',
            'signer_title' => 'Finance Lead',
        ]);

        // Verify it was saved to the DB
        $this->assertDatabaseHas('pi_settings', [
            'user_id' => $this->user->id,
            'document_tag' => 'INVOICE',
            'signer_name' => 'Jane Smith',
            'signer_title' => 'Finance Lead',
        ]);

        // 2. Retrieve with filters
        $response = $this->withHeaders($this->getAuthHeader())
            ->getJson('/api/distributor-channel/v1/pi-settings?user_id=' . $this->user->id . '&document_tag=INVOICE');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'user_id' => $this->user->id,
            'document_tag' => 'INVOICE',
            'signer_name' => 'Jane Smith',
            'signer_title' => 'Finance Lead',
        ]);

        // 3. Retrieve with other filters -> should fallback to the global default setting
        // First, create the default setting
        PiSetting::create([
            'signer_name' => 'Default Signer',
            'signer_title' => 'Default Title',
            'user_id' => null,
            'document_tag' => null,
        ]);

        $response = $this->withHeaders($this->getAuthHeader())
            ->getJson('/api/distributor-channel/v1/pi-settings?user_id=9999&document_tag=DRAFT');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'user_id' => null,
            'document_tag' => null,
            'signer_name' => 'Default Signer',
            'signer_title' => 'Default Title',
        ]);
    }
}
