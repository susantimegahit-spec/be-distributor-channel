<?php

namespace Tests\Feature;

use App\Models\ProductionChangeProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductionChangeProductTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'      => 'Production User',
            'username'  => 'produser',
            'email'     => 'produser@test.com',
            'password'  => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test_token')->plainTextToken;

        ProductionChangeProduct::truncate();
    }

    /**
     * Test creating a Change Product draft.
     */
    public function test_create_change_product_draft_successfully(): void
    {
        $payload = [
            'docDate'    => '2026-09-01T00:00:00',
            'docDueDate' => '2026-09-01T00:00:00',
            'comments'   => 'Proses Change Product Multiple Items',
            'shift'      => '1',
            'unit'       => 'UNIT-A',
            'addonId'    => 'ADDON-CP-001',
            'userId'     => '105',
            'lines'      => [
                [
                    'oldItemCode' => 'ITEM-OLD-001',
                    'newItemCode' => 'ITEM-NEW-001',
                    'quantity'    => 10.0,
                    'fromWhsCode' => 'WRH-RAW',
                    'toWhsCode'   => 'WRH-FG',
                    'ocrCode'     => 'CC-PROD',
                    'ocrCode2'    => 'DEPT-MFG',
                    'ocrCode3'    => 'LINE-01',
                ],
                [
                    'oldItemCode' => 'ITEM-OLD-002',
                    'newItemCode' => 'ITEM-NEW-002',
                    'quantity'    => 5.0,
                    'fromWhsCode' => 'WRH-RAW',
                    'toWhsCode'   => 'WRH-FG',
                    'ocrCode'     => 'CC-PROD',
                    'ocrCode2'    => 'DEPT-MFG',
                    'ocrCode3'    => 'LINE-01',
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/distributor-channel/v1/production/change-products', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'DRAFT');
        $response->assertJsonPath('data.sap_status', 'PENDING');
        $this->assertCount(2, $response->json('data.items'));
    }

    /**
     * Test updating a Change Product draft.
     */
    public function test_update_change_product_draft_successfully(): void
    {
        $cp = ProductionChangeProduct::create([
            'cp_no'      => 'CP-20260902-0001',
            'doc_date'   => now(),
            'comments'   => 'Old comments',
            'status'     => 'DRAFT',
            'created_by' => $this->user->id,
        ]);

        $cp->items()->create([
            'line_num'      => 0,
            'old_item_code' => 'ITEM-OLD-001',
            'new_item_code' => 'ITEM-NEW-001',
            'quantity'      => 5.0,
            'from_whs_code' => 'WRH-RAW',
            'to_whs_code'   => 'WRH-FG',
        ]);

        $updatePayload = [
            'comments' => 'Updated comments for Change Product',
            'lines'    => [
                [
                    'oldItemCode' => 'ITEM-OLD-001-EDIT',
                    'newItemCode' => 'ITEM-NEW-001-EDIT',
                    'quantity'    => 12.5,
                    'fromWhsCode' => 'WRH-RAW',
                    'toWhsCode'   => 'WRH-FG',
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/distributor-channel/v1/production/change-products/{$cp->id}", $updatePayload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.comments', 'Updated comments for Change Product');
        $response->assertJsonPath('data.items.0.old_item_code', 'ITEM-OLD-001-EDIT');
        $response->assertJsonPath('data.items.0.quantity', 12.5);
    }

    /**
     * Test posting Change Product to SAP B1 extracts GI & GR and completes transaction.
     */
    public function test_post_change_product_to_sap_updates_gi_gr_and_completes(): void
    {
        $sapUrl = config('services.sap.url') ?: 'http://103.18.133.187:3100';
        config(['services.sap.url' => $sapUrl]);

        Http::fake([
            "{$sapUrl}/api/AddCP" => Http::response([
                'errorCode' => 0,
                'message'   => 'Success - [ChangeProduct]. Issue Entry: 1254, Receipt Entry: 1255',
            ], 200),
        ]);

        $cp = ProductionChangeProduct::create([
            'cp_no'      => 'CP-20260902-0002',
            'doc_date'   => now(),
            'comments'   => 'Ready to post',
            'status'     => 'DRAFT',
            'created_by' => $this->user->id,
        ]);

        $cp->items()->create([
            'line_num'      => 0,
            'old_item_code' => 'ITEM-OLD-001',
            'new_item_code' => 'ITEM-NEW-001',
            'quantity'      => 10.0,
            'from_whs_code' => 'WRH-RAW',
            'to_whs_code'   => 'WRH-FG',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/distributor-channel/v1/production/change-products/{$cp->id}/post");

        $response->assertStatus(200);
        $response->assertJsonPath('data.gi_entry', 1254);
        $response->assertJsonPath('data.gr_entry', 1255);
        $response->assertJsonPath('data.change_product.status', 'COMPLETE');
        $response->assertJsonPath('data.change_product.sap_status', 'SYNCED');

        $this->assertDatabaseHas('production_change_products', [
            'id'         => $cp->id,
            'gi_entry'   => 1254,
            'gr_entry'   => 1255,
            'status'     => 'COMPLETE',
            'sap_status' => 'SYNCED',
        ], 'pgsql_production');
    }

    /**
     * Test cannot update or delete completed Change Product.
     */
    public function test_cannot_update_or_delete_completed_change_product(): void
    {
        $cp = ProductionChangeProduct::create([
            'cp_no'      => 'CP-20260902-0003',
            'doc_date'   => now(),
            'status'     => 'COMPLETE',
            'created_by' => $this->user->id,
        ]);

        // Attempt update
        $updateResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/distributor-channel/v1/production/change-products/{$cp->id}", [
                'comments' => 'Try to hack completed transaction',
            ]);
        $updateResponse->assertStatus(400);

        // Attempt delete
        $deleteResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/distributor-channel/v1/production/change-products/{$cp->id}");
        $deleteResponse->assertStatus(400);
    }
}
