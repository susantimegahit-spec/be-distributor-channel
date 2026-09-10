<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentTypeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed some DocumentTypes
        DocumentType::create([
            'code' => 'ORDR',
            'name' => 'Sales Order',
            'object_type' => '17',
            'is_active' => true,
        ]);

        DocumentType::create([
            'code' => 'OPDN',
            'name' => 'Goods Receipt PO',
            'object_type' => '20',
            'is_active' => true,
        ]);

        DocumentType::create([
            'code' => 'OINV',
            'name' => 'AR Invoice',
            'object_type' => '13',
            'is_active' => false,
        ]);
    }

    public function test_can_retrieve_all_document_types(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Test both registered route endpoints
        foreach (['/api/distributor-channel/v1/document-types', '/api/distributor-channel/v1/purchasing-request/document-types'] as $endpoint) {
            $response = $this->getJson($endpoint);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Daftar document type berhasil diambil.',
                ])
                ->assertJsonCount(3, 'data')
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => ['code', 'name', 'object_type']
                    ]
                ]);
        }
    }

    public function test_can_search_document_types_by_code(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/distributor-channel/v1/document-types?search=ORDR');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'ORDR')
            ->assertJsonPath('data.0.name', 'Sales Order')
            ->assertJsonPath('data.0.object_type', '17');
    }

    public function test_can_search_document_types_by_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/distributor-channel/v1/document-types?search=Receipt');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'OPDN')
            ->assertJsonPath('data.0.name', 'Goods Receipt PO');
    }
}
