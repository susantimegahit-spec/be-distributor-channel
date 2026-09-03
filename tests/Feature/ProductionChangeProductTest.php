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
     * Test creating a Change Product draft (4 old items -> 2 new items).
     */
    public function test_create_change_product_draft_successfully(): void
    {
        $payload = [
            'docDate'    => '2026-09-01T00:00:00',
            'docDueDate' => '2026-09-01T00:00:00',
            'comments'   => 'Change product 4 item lama menjadi 2 item baru',
            'shift'      => '1',
            'unit'       => 'UNIT-A',
            'addonId'    => 'ADDON-CP-001',
            'userId'     => '105',
            'oldLines'   => [
                [
                    'itemCode'    => 'OLD-01',
                    'quantity'    => 10.0,
                    'fromWhsCode' => 'PRD01-01',
                    'ocrCode'     => 'CC-PROD',
                    'ocrCode2'    => 'DEPT-MFG',
                    'ocrCode3'    => 'LINE-01',
                ],
                [
                    'itemCode'    => 'OLD-02',
                    'quantity'    => 5.0,
                    'fromWhsCode' => 'PRD01-01',
                    'ocrCode'     => 'CC-PROD',
                    'ocrCode2'    => 'DEPT-MFG',
                    'ocrCode3'    => 'LINE-01',
                ],
                [
                    'itemCode'    => 'OLD-03',
                    'quantity'    => 2.0,
                    'fromWhsCode' => 'PRD01-01',
                    'ocrCode'     => 'CC-PROD',
                    'ocrCode2'    => 'DEPT-MFG',
                    'ocrCode3'    => 'LINE-01',
                ],
                [
                    'itemCode'    => 'OLD-04',
                    'quantity'    => 8.0,
                    'fromWhsCode' => 'PRD01-01',
                    'ocrCode'     => 'CC-PROD',
                    'ocrCode2'    => 'DEPT-MFG',
                    'ocrCode3'    => 'LINE-01',
                ],
            ],
            'newLines'   => [
                [
                    'itemCode'               => 'NEW-01',
                    'quantity'               => 12.0,
                    'toWhsCode'              => 'FG01',
                    'ocrCode'                => 'CC-PROD',
                    'ocrCode2'               => 'DEPT-MFG',
                    'ocrCode3'               => 'LINE-01',
                    'valueAllocationPercent' => 0,
                ],
                [
                    'itemCode'               => 'NEW-02',
                    'quantity'               => 4.0,
                    'toWhsCode'              => 'FG01',
                    'ocrCode'                => 'CC-PROD',
                    'ocrCode2'               => 'DEPT-MFG',
                    'ocrCode3'               => 'LINE-01',
                    'valueAllocationPercent' => 0,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/distributor-channel/v1/production/change-products', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'DRAFT');
        $response->assertJsonPath('data.sap_status', 'PENDING');
        $this->assertCount(4, $response->json('data.old_lines'));
        $this->assertCount(2, $response->json('data.new_lines'));
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

        $cp->oldLines()->create([
            'line_num'      => 0,
            'item_code'     => 'OLD-01',
            'quantity'      => 5.0,
            'from_whs_code' => 'WRH-RAW',
        ]);

        $cp->newLines()->create([
            'line_num'      => 0,
            'item_code'     => 'NEW-01',
            'quantity'      => 5.0,
            'to_whs_code'   => 'WRH-FG',
        ]);

        $updatePayload = [
            'comments' => 'Updated comments for Change Product',
            'oldLines' => [
                [
                    'itemCode'    => 'OLD-EDIT-01',
                    'quantity'    => 12.5,
                    'fromWhsCode' => 'WRH-RAW',
                ],
            ],
            'newLines' => [
                [
                    'itemCode'    => 'NEW-EDIT-01',
                    'quantity'    => 10.0,
                    'toWhsCode'   => 'WRH-FG',
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/distributor-channel/v1/production/change-products/{$cp->id}", $updatePayload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.comments', 'Updated comments for Change Product');
        $response->assertJsonPath('data.old_lines.0.item_code', 'OLD-EDIT-01');
        $response->assertJsonPath('data.old_lines.0.quantity', 12.5);
        $response->assertJsonPath('data.new_lines.0.item_code', 'NEW-EDIT-01');
        $response->assertJsonPath('data.new_lines.0.quantity', 10);
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
            'shift'      => 'All',
            'status'     => 'DRAFT',
            'created_by' => $this->user->id,
        ]);

        $cp->oldLines()->create([
            'line_num'      => 0,
            'item_code'     => 'OLD-01',
            'quantity'      => 10.0,
            'from_whs_code' => 'WRH-RAW',
        ]);

        $cp->newLines()->create([
            'line_num'      => 0,
            'item_code'     => 'NEW-01',
            'quantity'      => 10.0,
            'to_whs_code'   => 'WRH-FG',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/distributor-channel/v1/production/change-products/{$cp->id}/post");

        $response->assertStatus(200);
        $response->assertJsonPath('data.gi_entry', 1254);
        $response->assertJsonPath('data.gr_entry', 1255);
        $response->assertJsonPath('data.change_product.status', 'COMPLETE');
        $response->assertJsonPath('data.change_product.sap_status', 'SYNCED');

        // Verify SAP payload mapped 'All' to 'A' (valid SAP value for Shift 1 / All fallback)
        Http::assertSent(function ($request) {
            return $request['shift'] === 'A';
        });

        // Verify unit mapping for all variations
        $service = app(\App\Modules\Production\Services\ProductionService::class);
        $this->assertEquals('A', $service->mapChangeProductShift('All'));
        $this->assertEquals('A', $service->mapChangeProductShift('all'));
        $this->assertEquals('A', $service->mapChangeProductShift('Shift 1'));
        $this->assertEquals('A', $service->mapChangeProductShift('1'));
        $this->assertEquals('B', $service->mapChangeProductShift('Shift 2'));
        $this->assertEquals('B', $service->mapChangeProductShift('2'));
        $this->assertEquals('C', $service->mapChangeProductShift('Shift 3'));
        $this->assertEquals('C', $service->mapChangeProductShift('3'));

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
