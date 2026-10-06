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

    public function test_items_filtering_by_status(): void
    {
        $user = \App\Models\User::factory()->create(['code_customer' => null]);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        Item::create([
            'item_code'    => 'ITEM-SELL-ONLY',
            'item_name'    => 'Barang Jual Saja',
            'sell_item'    => 'Y',
            'prchse_item'  => 'N',
            'invnt_item'   => 'Y',
            'itms_grp_cod' => '101',
        ]);

        Item::create([
            'item_code'    => 'ITEM-PURCHASE-ONLY',
            'item_name'    => 'Barang Beli Saja',
            'sell_item'    => 'N',
            'prchse_item'  => 'Y',
            'invnt_item'   => 'Y',
            'itms_grp_cod' => '102',
        ]);

        // Filter sales_item_status=Y
        $resSell = $this->getJson('/api/distributor-channel/v1/items?sales_item_status=Y');
        $resSell->assertStatus(200);
        $dataSell = $resSell->json('data');
        $codesSell = collect($dataSell)->pluck('item_code')->all();
        $this->assertContains('ITEM-SELL-ONLY', $codesSell);
        $this->assertNotContains('ITEM-PURCHASE-ONLY', $codesSell);

        // Filter purchase_item_status=Y
        $resPur = $this->getJson('/api/distributor-channel/v1/items?purchase_item_status=Y');
        $resPur->assertStatus(200);
        $dataPur = $resPur->json('data');
        $codesPur = collect($dataPur)->pluck('item_code')->all();
        $this->assertContains('ITEM-PURCHASE-ONLY', $codesPur);
        $this->assertNotContains('ITEM-SELL-ONLY', $codesPur);

        // Filter inventory_item_status=Y
        $resInv = $this->getJson('/api/distributor-channel/v1/items?inventory_item_status=Y');
        $resInv->assertStatus(200);
        $dataInv = $resInv->json('data');
        $codesInv = collect($dataInv)->pluck('item_code')->all();
        $this->assertContains('ITEM-SELL-ONLY', $codesInv);
        $this->assertContains('ITEM-PURCHASE-ONLY', $codesInv);
    }

    public function test_items_sync_from_sap_list_item_prod(): void
    {
        $user = \App\Models\User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($user);

        \Illuminate\Support\Facades\Http::fake([
            '*/api/ListItemProd' => \Illuminate\Support\Facades\Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'ItemCode' => 'ISP001015',
                        'ItemName' => 'MCB Gv 2 L10.6,3A',
                        'IUoMEntry' => '-1',
                        'InvntryUom' => 'Pcs',
                        'PUoMEntry' => '0',
                        'PurPackMsr' => '',
                        'PrchseItem' => 'Y',
                        'SellItem' => 'N',
                        'InvntItem' => 'Y',
                        'ItmsGrpCod' => '105',
                    ],
                ],
            ], 200),
            '*/api/ListItem' => \Illuminate\Support\Facades\Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [],
            ], 200),
        ]);

        $response = $this->postJson('/api/distributor-channel/v1/items/sync');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data item berhasil disinkronisasi dari SAP.',
            ]);

        $this->assertDatabaseHas('items', [
            'item_code'   => 'ISP001015',
            'item_name'   => 'MCB Gv 2 L10.6,3A',
            'iuom_entry'  => -1,
            'invntry_uom' => 'Pcs',
            'puom_entry'  => 0,
            'prchse_item' => 'Y',
            'sell_item'   => 'N',
            'invnt_item'  => 'Y',
            'itms_grp_cod' => '105',
        ]);
    }
}
