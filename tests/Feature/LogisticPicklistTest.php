<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Item;
use App\Models\Picklist;
use App\Models\PicklistItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LogisticPicklistTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Distributor $distributor;
    protected Item $item1;
    protected Item $item2;
    protected SalesOrder $order1;
    protected SalesOrder $order2;
    protected SalesOrderDetail $detail1;
    protected SalesOrderDetail $detail2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->distributor = Distributor::create([
            'code_customer' => 'CUST-PL-001',
            'name'          => 'PT Mitra Logistik Jaya',
            'depo'          => 'SURABAYA',
        ]);

        $this->user = User::create([
            'name'          => 'Logistik Checker',
            'username'      => 'pkl_checker',
            'email'         => 'pkl_checker@example.com',
            'password'      => bcrypt('password123'),
            'role'          => 'logistic',
            'code_customer' => 'CUST-PL-001',
            'is_active'     => true,
        ]);

        $this->item1 = Item::firstOrCreate(
            ['item_code' => 'SKU-SALT-250'],
            [
                'item_name'    => 'Garam Meja Halus 250gr',
                'sal_unit_msr' => 'CTN',
                'per_kg'       => 0.25,
            ]
        );

        $this->item2 = Item::firstOrCreate(
            ['item_code' => 'SKU-SALT-1000'],
            [
                'item_name'    => 'Garam Dapur Kasar 1kg',
                'sal_unit_msr' => 'CTN',
                'per_kg'       => 1.00,
            ]
        );

        $this->order1 = SalesOrder::create([
            'order_no'        => 'SO-PKL-001',
            'distributor_id'  => $this->distributor->id,
            'card_code'       => 'CUST-PL-001',
            'customer_name'   => 'PT Mitra Logistik Jaya',
            'doc_date'        => '2026-09-18',
            'req_due_date'    => '2026-09-20',
            'doc_due_date'    => '2026-09-20',
            'eta_date'        => '2026-09-22',
            'status'          => 'ORDER_APPROVED',
            'logistic_status' => 'APPROVED',
        ]);

        $this->detail1 = SalesOrderDetail::create([
            'sales_order_id' => $this->order1->id,
            'item_code'      => $this->item1->item_code,
            'quantity'       => 100,
            'unit_msr'       => 'CTN',
            'whs_code'       => 'WHS-SBY',
            'unit_price'     => 50000,
            'line_total'     => 5000000,
        ]);

        $this->order2 = SalesOrder::create([
            'order_no'        => 'SO-PKL-002',
            'distributor_id'  => $this->distributor->id,
            'card_code'       => 'CUST-PL-001',
            'customer_name'   => 'PT Mitra Logistik Jaya',
            'doc_date'        => '2026-09-18',
            'req_due_date'    => '2026-09-21',
            'doc_due_date'    => '2026-09-21',
            'eta_date'        => '2026-09-23',
            'status'          => 'ORDER_APPROVED',
            'logistic_status' => 'RESCHEDULE_APPROVED',
        ]);

        $this->detail2 = SalesOrderDetail::create([
            'sales_order_id' => $this->order2->id,
            'item_code'      => $this->item2->item_code,
            'quantity'       => 50,
            'unit_msr'       => 'CTN',
            'whs_code'       => 'WHS-SBY',
            'unit_price'     => 120000,
            'line_total'     => 6000000,
        ]);
    }

    public function test_can_get_available_orders_for_picklist(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/distributor-channel/v1/logistic/picklists/available-orders');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'order_no',
                        'customer_name',
                        'logistic_status',
                        'total_weight',
                        'items' => [
                            '*' => [
                                'sales_order_id',
                                'sales_order_detail_id',
                                'item_code',
                                'ordered_qty',
                                'already_picked_qty',
                                'remaining_pick_qty',
                                'unit_weight',
                                'total_weight',
                            ],
                        ],
                    ],
                ],
            ]);

        $orders = collect($response->json('data'));
        $this->assertTrue($orders->pluck('id')->contains($this->order1->id));
        $this->assertTrue($orders->pluck('id')->contains($this->order2->id));
    }

    public function test_can_create_internal_picklist_with_multiple_orders(): void
    {
        $payload = [
            'shipping_type'       => 'internal',
            'license_plate'       => 'L 1234 AB',
            'driver_name'         => 'Budi Santoso',
            'checker_name'        => 'Agus Setiawan',
            'posting_date'        => '2026-09-18',
            'due_date'            => '2026-09-20',
            'total_weight_limit'  => 1000,
            'comments'            => 'Muatan gabungan SO-001 dan SO-002',
            'items' => [
                [
                    'sales_order_id'        => $this->order1->id,
                    'sales_order_detail_id' => $this->detail1->id,
                    'item_code'             => $this->item1->item_code,
                    'pick_qty'              => 40,
                ],
                [
                    'sales_order_id'        => $this->order2->id,
                    'sales_order_detail_id' => $this->detail2->id,
                    'item_code'             => $this->item2->item_code,
                    'pick_qty'              => 30,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/logistic/picklists', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.shipping_type', 'internal')
            ->assertJsonPath('data.license_plate', 'L 1234 AB')
            ->assertJsonPath('data.driver_name', 'Budi Santoso')
            ->assertJsonPath('data.checker_name', 'Agus Setiawan')
            ->assertJsonPath('data.status', 'OPEN');

        // Total weight calculation:
        // Item 1: 40 * 0.25 = 10 kg
        // Item 2: 30 * 1.00 = 30 kg
        // Expected total = 40 kg
        $picklistId = $response->json('data.id');
        $picklist = Picklist::with('items')->find($picklistId);
        $this->assertNotNull($picklist);
        $this->assertEquals(40.0, (float) $picklist->total_weight);
        $this->assertCount(2, $picklist->items);
        $this->assertStringStartsWith('PKL-', $picklist->picklist_no);
    }

    public function test_internal_picklist_requires_license_plate(): void
    {
        $payload = [
            'shipping_type' => 'internal',
            'license_plate' => '',
            'posting_date'  => '2026-09-18',
            'due_date'      => '2026-09-20',
            'items' => [
                [
                    'sales_order_id'        => $this->order1->id,
                    'sales_order_detail_id' => $this->detail1->id,
                    'item_code'             => $this->item1->item_code,
                    'pick_qty'              => 10,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/logistic/picklists', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_can_create_external_picklist_with_single_order(): void
    {
        $payload = [
            'shipping_type'      => 'external',
            'posting_date'       => '2026-09-18',
            'due_date'           => '2026-09-20',
            'expedition_name'    => 'Nusantara Cargo',
            'service_type'       => 'Regular',
            'estimated_cost'     => 450000,
            'comments'           => 'Kirim via ekspedisi',
            'items' => [
                [
                    'sales_order_id'        => $this->order1->id,
                    'sales_order_detail_id' => $this->detail1->id,
                    'item_code'             => $this->item1->item_code,
                    'pick_qty'              => 20,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/logistic/picklists', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.shipping_type', 'external')
            ->assertJsonPath('data.expedition_name', 'Nusantara Cargo');

        $this->assertNull($response->json('data.license_plate'));
        $this->assertNull($response->json('data.driver_name'));
    }

    public function test_external_picklist_rejects_multiple_orders(): void
    {
        $payload = [
            'shipping_type' => 'external',
            'posting_date'  => '2026-09-18',
            'due_date'      => '2026-09-20',
            'items' => [
                [
                    'sales_order_id'        => $this->order1->id,
                    'sales_order_detail_id' => $this->detail1->id,
                    'item_code'             => $this->item1->item_code,
                    'pick_qty'              => 10,
                ],
                [
                    'sales_order_id'        => $this->order2->id,
                    'sales_order_detail_id' => $this->detail2->id,
                    'item_code'             => $this->item2->item_code,
                    'pick_qty'              => 10,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/logistic/picklists', $payload);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'External and Pickup shipping allow only one Sales Order. Please select items from a single Sales Order.');
    }

    public function test_pickup_picklist_rejects_multiple_orders(): void
    {
        $payload = [
            'shipping_type' => 'pickup',
            'posting_date'  => '2026-09-18',
            'due_date'      => '2026-09-20',
            'items' => [
                [
                    'sales_order_id'        => $this->order1->id,
                    'sales_order_detail_id' => $this->detail1->id,
                    'item_code'             => $this->item1->item_code,
                    'pick_qty'              => 10,
                ],
                [
                    'sales_order_id'        => $this->order2->id,
                    'sales_order_detail_id' => $this->detail2->id,
                    'item_code'             => $this->item2->item_code,
                    'pick_qty'              => 10,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/logistic/picklists', $payload);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'External and Pickup shipping allow only one Sales Order. Please select items from a single Sales Order.');
    }

    public function test_cannot_pick_quantity_exceeding_remaining_order_qty(): void
    {
        // Detail 1 quantity is 100
        $payload = [
            'shipping_type' => 'pickup',
            'posting_date'  => '2026-09-18',
            'due_date'      => '2026-09-20',
            'items' => [
                [
                    'sales_order_id'        => $this->order1->id,
                    'sales_order_detail_id' => $this->detail1->id,
                    'item_code'             => $this->item1->item_code,
                    'pick_qty'              => 150, // exceeds 100!
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/logistic/picklists', $payload);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_exceed_total_weight_limit(): void
    {
        // Item 2 per_kg is 1.00. 50 qty * 1.00 = 50 kg.
        // If weight limit is set to 20 kg, it should fail.
        $payload = [
            'shipping_type'      => 'pickup',
            'posting_date'       => '2026-09-18',
            'due_date'           => '2026-09-20',
            'total_weight_limit' => 20.0,
            'items' => [
                [
                    'sales_order_id'        => $this->order2->id,
                    'sales_order_detail_id' => $this->detail2->id,
                    'item_code'             => $this->item2->item_code,
                    'pick_qty'              => 50,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/logistic/picklists', $payload);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_can_view_picklist_detail_and_update_status(): void
    {
        $payload = [
            'shipping_type' => 'pickup',
            'posting_date'  => '2026-09-18',
            'due_date'      => '2026-09-20',
            'items' => [
                [
                    'sales_order_id'        => $this->order1->id,
                    'sales_order_detail_id' => $this->detail1->id,
                    'item_code'             => $this->item1->item_code,
                    'pick_qty'              => 10,
                ],
            ],
        ];

        $createRes = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/logistic/picklists', $payload);
        $picklistId = $createRes->json('data.id');

        // View detail
        $detailRes = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/logistic/picklists/{$picklistId}");
        $detailRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $picklistId);

        // Update status to COMPLETED
        $statusRes = $this->actingAs($this->user)
            ->patchJson("/api/distributor-channel/v1/logistic/picklists/{$picklistId}/status", [
                'status' => 'COMPLETED',
            ]);
        $statusRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'COMPLETED');

        // Updating a COMPLETED picklist should fail
        $failRes = $this->actingAs($this->user)
            ->patchJson("/api/distributor-channel/v1/logistic/picklists/{$picklistId}/status", [
                'status' => 'CANCELLED',
            ]);
        $failRes->assertStatus(400)
            ->assertJsonPath('success', false);
    }
}
