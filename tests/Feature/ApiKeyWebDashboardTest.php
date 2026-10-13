<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\DistributorApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyWebDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->distributor = Distributor::create([
            'code_customer' => 'CUST-WEB-001',
            'name' => 'Distributor Web Test',
        ]);
    }

    public function test_can_access_api_keys_dashboard_with_session_auth(): void
    {
        $response = $this->withSession(['pulse_authenticated' => true])
            ->get('/monitoringsm/api-keys');

        $response->assertStatus(200)
            ->assertSee('B2B API Key Monitoring')
            ->assertSee('Distributor Web Test');
    }

    public function test_can_generate_new_api_key_via_web_dashboard(): void
    {
        $response = $this->withSession(['pulse_authenticated' => true])
            ->post('/monitoringsm/api-keys/generate', [
                'distributor_ids' => [$this->distributor->id],
                'name' => 'ERP Surabaya System',
                'allowed_ips' => '203.0.113.195, 198.51.100.22',
            ]);

        $response->assertRedirect('/monitoringsm/api-keys');
        $response->assertSessionHas('generated_key');

        $this->assertDatabaseHas('distributor_api_keys', [
            'name' => 'ERP Surabaya System',
            'is_active' => true,
        ]);
    }

    public function test_can_toggle_api_key_active_status(): void
    {
        $apiKey = DistributorApiKey::create([
            'distributor_id' => $this->distributor->id,
            'name' => 'Test Key',
            'key_prefix' => 'susanti_sec_123',
            'api_key_hash' => hash('sha256', 'susanti_sec_12345'),
            'is_active' => true,
        ]);

        $response = $this->withSession(['pulse_authenticated' => true])
            ->post("/monitoringsm/api-keys/{$apiKey->id}/toggle");

        $response->assertRedirect();
        $this->assertDatabaseHas('distributor_api_keys', [
            'id' => $apiKey->id,
            'is_active' => false,
        ]);
    }

    public function test_can_delete_api_key(): void
    {
        $apiKey = DistributorApiKey::create([
            'distributor_id' => $this->distributor->id,
            'name' => 'Test Key Delete',
            'key_prefix' => 'susanti_sec_999',
            'api_key_hash' => hash('sha256', 'susanti_sec_99999'),
            'is_active' => true,
        ]);

        $response = $this->withSession(['pulse_authenticated' => true])
            ->post("/monitoringsm/api-keys/{$apiKey->id}/delete");

        $response->assertRedirect();
        $this->assertDatabaseMissing('distributor_api_keys', [
            'id' => $apiKey->id,
        ]);
    }
}
