<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\DistributorApiKey;
use App\Models\Item;
use App\Models\DistributorItemPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ItemApiTest extends TestCase
{
    use RefreshDatabase;

    protected Distributor $distributor;
    protected string $rawApiKey;
    protected DistributorApiKey $apiKeyRecord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->distributor = Distributor::create([
            'code_customer' => 'CUST-TEST-001',
            'name' => 'Distributor Test PT',
        ]);

        $this->rawApiKey = 'susanti_sec_' . Str::random(40);
        $hashedKey = DistributorApiKey::hashKey($this->rawApiKey);

        $this->apiKeyRecord = DistributorApiKey::create([
            'distributor_id' => $this->distributor->id,
            'name' => 'ERP System Test',
            'key_prefix' => substr($this->rawApiKey, 0, 15),
            'api_key_hash' => $hashedKey,
            'is_active' => true,
        ]);
        $this->apiKeyRecord->distributors()->attach($this->distributor->id);

        Item::create([
            'item_code' => 'SKU-TEST-001',
            'item_name' => 'Barang Test 1',
            'price' => 50000,
            'sales_uom' => 'CTN',
        ]);

        DistributorItemPrice::create([
            'code_customer' => 'CUST-TEST-001',
            'item_code' => 'SKU-TEST-001',
            'price' => 50000,
            'status' => 1,
        ]);
    }

    public function test_existing_items_endpoint_works_normally_via_sanctum(): void
    {
        $user = \App\Models\User::factory()->create(['code_customer' => null]);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $response = $this->getJson('/api/distributor-channel/v1/items?code_customer=CUST-TEST-001');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_external_distributor_gets_items_successfully_with_mapped_customer_code(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rawApiKey)
            ->getJson('/api/distributor-channel/v1/external/customer-monthly-orders/items?card_code=CUST-TEST-001');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Daftar barang berhasil diambil.',
            ])
            ->assertJsonFragment([
                'item_code' => 'SKU-TEST-001',
                'item_name' => 'Barang Test 1',
            ]);
    }

    public function test_external_distributor_gets_blocked_when_querying_unmapped_customer_code(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rawApiKey)
            ->getJson('/api/distributor-channel/v1/external/customer-monthly-orders/items?card_code=UNMAPPED-CUST-999');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => "Akses ditolak. card_code 'UNMAPPED-CUST-999' tidak terdaftar untuk API Key ini. Distributor yang diizinkan: [CUST-TEST-001]",
            ]);
    }
}
