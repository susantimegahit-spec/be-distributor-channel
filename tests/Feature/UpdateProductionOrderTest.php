<?php

namespace Tests\Feature;

use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateProductionOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Production Admin',
            'username' => 'prodadmin',
            'email' => 'prodadmin@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    public function test_update_production_order_saves_start_date_from_payload(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create initial production order
        $order = ProductionOrder::create([
            'prod_order_no' => 'PDO-TEST-001',
            'doc_num' => 'PDO-TEST-001',
            'doc_entry' => 5640,
            'series' => 5640,
            'series_name' => 'SBY26-10',
            'status' => 'PLANNED',
            'type' => 'Standard',
            'item_code' => 'HC56C',
            'planned_qty' => 500,
            'warehouse' => 'PRD01-01',
            'post_date' => '2026-10-01',
            'start_date' => '2026-10-01',
            'due_date' => '2026-10-05',
            'u_shift' => 'All',
            'u_unit' => 'SBY-UNIT1',
            'comments' => 'Initial comment',
            'sap_status' => 'PENDING',
        ]);

        $payload = [
            'ItemCode' => 'HC56C',
            'Series' => 5640,
            'series_name' => 'SBY26-10',
            'Status' => 'PLANNED',
            'ProductionOrderStatus' => 'PLANNED',
            'PlannedQty' => 1000,
            'PostingDate' => '2026-10-11T00:00:00',
            'StartDate' => '2026-10-09T00:00:00',
            'DueDate' => '2026-10-11T00:00:00',
            'WhsCode' => 'PRD01-01',
            'Remarks' => 'SBY updated',
            'Shift' => 'All',
            'Unit' => '',
            'u_unit' => 'SBY-UNIT1',
            'Bomid' => '57',
            'UserId' => '1',
            'AddonId' => '',
            'Lines' => [
                [
                    'ItemType' => 'I',
                    'ItemCode' => 'R12L',
                    'BaseQty' => 1,
                    'WhsCode' => 'PRD01-01',
                    'IssueMethod' => 'M',
                ]
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/distributor-channel/v1/production/orders/{$order->id}", $payload);

        $response->assertStatus(200);

        // Assert database record was updated with the new start_date
        $order->refresh();
        $this->assertEquals('2026-10-09', $order->start_date->format('Y-m-d'));
        $this->assertEquals('2026-10-11', $order->post_date->format('Y-m-d'));
        $this->assertEquals('2026-10-11', $order->due_date->format('Y-m-d'));
        $this->assertEquals(1000, $order->planned_qty);
        $this->assertEquals('SBY updated', $order->comments);
        $this->assertEquals('SBY-UNIT1', $order->u_unit);

        // Also assert response json contains updated start_date
        $this->assertStringStartsWith('2026-10-09', $response->json('data.start_date'));
    }

    public function test_update_production_order_to_released_forwards_start_date_to_sap(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $order = ProductionOrder::create([
            'prod_order_no' => 'PDO-LOCAL-002',
            'doc_num' => null,
            'doc_entry' => null,
            'series' => 5640,
            'series_name' => 'SBY26-10',
            'status' => 'PLANNED',
            'type' => 'Standard',
            'item_code' => 'HC56C',
            'planned_qty' => 500,
            'warehouse' => 'PRD01-01',
            'post_date' => '2026-10-01',
            'start_date' => '2026-10-01',
            'due_date' => '2026-10-05',
            'u_shift' => 'All',
            'u_unit' => 'SBY-UNIT1',
            'comments' => 'Initial comment',
            'sap_status' => 'PENDING',
        ]);

        Http::fake([
            '*/api/addpdo' => function ($request) {
                $payload = $request->data();
                $this->assertEquals('2026-10-09T00:00:00', $payload['StartDate']);
                $this->assertEquals('2026-10-11T00:00:00', $payload['PostingDate']);
                $this->assertEquals('2026-10-11T00:00:00', $payload['DueDate']);

                return Http::response([
                    'ErrorCode' => 0,
                    'Message' => 'Success - DocNum: 260910013 - 6717',
                ], 200);
            }
        ]);

        $payload = [
            'ItemCode' => 'HC56C',
            'Series' => 5640,
            'Status' => 'RELEASED',
            'PlannedQty' => 1000,
            'PostingDate' => '2026-10-11T00:00:00',
            'StartDate' => '2026-10-09T00:00:00',
            'DueDate' => '2026-10-11T00:00:00',
            'WhsCode' => 'PRD01-01',
            'Remarks' => 'Ready to release',
            'Shift' => 'All',
            'u_unit' => 'SBY-UNIT1',
            'Lines' => [
                [
                    'ItemType' => 'I',
                    'ItemCode' => 'R12L',
                    'BaseQty' => 1,
                    'WhsCode' => 'PRD01-01',
                    'IssueMethod' => 'M',
                ]
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/distributor-channel/v1/production/orders/{$order->id}", $payload);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals('2026-10-09', $order->start_date->format('Y-m-d'));
        $this->assertEquals('RELEASED', $order->status);
        $this->assertEquals('SYNCED', $order->sap_status);
        $this->assertEquals(6717, $order->doc_entry);
    }
}
