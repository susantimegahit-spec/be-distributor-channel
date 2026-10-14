<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Item;
use App\Models\Picklist;
use App\Models\PicklistItem;
use App\Models\PicklistItemLabelPhoto;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogisticPicklistWeighingWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Distributor $distributor;
    protected Item $item1;
    protected Item $item2;
    protected SalesOrder $order;
    protected SalesOrderDetail $detail1;
    protected SalesOrderDetail $detail2;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        Http::fake([
            '*/api/addIT' => Http::response([
                'ErrorCode' => 0,
                'Message'   => 'Success DocNum: 7788 DocEntry: 5566',
                'Result'    => [
                    'DocEntry' => 5566,
                    'DocNum'   => 7788,
                ],
            ], 200),
            '*/api/AddDO' => Http::response([
                'ErrorCode' => 0,
                'Message'   => 'Delivery Order Created DocNum: 9001 DocEntry: 8001',
                'Result'    => [
                    'DocEntry' => 8001,
                    'DocNum'   => 9001,
                ],
            ], 200),
        ]);

        $this->distributor = Distributor::firstOrCreate(
            ['code_customer' => 'CUST-TEST-WGH'],
            [
                'name' => 'PT Distribusi Logistik',
                'depo' => 'SURABAYA',
            ]
        );

        $this->user = User::firstOrCreate(
            ['username' => 'tester_logistic'],
            [
                'name'          => 'Petugas Logistik',
                'email'         => 'tester_logistic@example.com',
                'password'      => bcrypt('password123'),
                'role'          => 'logistic',
                'code_customer' => 'CUST-TEST-WGH',
                'is_active'     => true,
            ]
        );

        $this->item1 = Item::firstOrCreate(
            ['item_code' => 'SKU-WGH-01'],
            [
                'item_name'    => 'Garam Kasar 50kg',
                'sal_unit_msr' => 'SAK',
                'per_kg'       => 50.0,
            ]
        );

        $this->item2 = Item::firstOrCreate(
            ['item_code' => 'SKU-WGH-02'],
            [
                'item_name'    => 'Garam Halus 25kg',
                'sal_unit_msr' => 'SAK',
                'per_kg'       => 25.0,
            ]
        );

        $this->order = SalesOrder::create([
            'order_no'        => 'SO-TEST-WGH-001',
            'sap_doc_entry'   => 8812,
            'sap_doc_num'     => '88001234',
            'docentry'        => 8812,
            'distributor_id'  => $this->distributor->id,
            'card_code'       => 'CUST-TEST-WGH',
            'customer_name'   => 'PT Distribusi Logistik',
            'doc_date'        => '2026-10-14',
            'req_due_date'    => '2026-10-16',
            'doc_due_date'    => '2026-10-16',
            'eta_date'        => '2026-10-17',
            'status'          => 'ORDER_APPROVED',
            'logistic_status' => 'APPROVED',
            'total_amount'    => 5000000,
            'created_by'      => $this->user->id,
        ]);

        $this->detail1 = SalesOrderDetail::create([
            'sales_order_id' => $this->order->id,
            'item_code'      => $this->item1->item_code,
            'item_name'      => $this->item1->item_name,
            'quantity'       => 100, // 100 * 50kg = 5000kg
            'unit_price'     => 25000,
            'line_total'     => 2500000,
            'baseline'       => 0,
            'line_num'       => 0,
        ]);

        $this->detail2 = SalesOrderDetail::create([
            'sales_order_id' => $this->order->id,
            'item_code'      => $this->item2->item_code,
            'item_name'      => $this->item2->item_name,
            'quantity'       => 200, // 200 * 25kg = 5000kg
            'unit_price'     => 12500,
            'line_total'     => 2500000,
            'baseline'       => 1,
            'line_num'       => 1,
        ]);
    }

    public function test_complete_weighing_and_checker_workflow(): void
    {
        // 1. Create Picklist
        $createRes = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/logistic/picklists', [
            'shipping_type'      => 'internal',
            'posting_date'       => '2026-10-14',
            'due_date'           => '2026-10-16',
            'license_plate'      => 'L 1234 WGH',
            'driver_name'        => 'Sopir Agus',
            'total_weight_limit' => 12000.0,
            'items' => [
                [
                    'sales_order_id'        => $this->order->id,
                    'sales_order_detail_id' => $this->detail1->id,
                    'item_code'             => $this->item1->item_code,
                    'pick_qty'              => 100,
                ],
                [
                    'sales_order_id'        => $this->order->id,
                    'sales_order_detail_id' => $this->detail2->id,
                    'item_code'             => $this->item2->item_code,
                    'pick_qty'              => 200,
                ],
            ],
        ]);

        $createRes->assertStatus(201);
        $picklistId = $createRes->json('data.id');
        $this->assertNotNull($picklistId);

        $picklist = Picklist::with('items')->find($picklistId);
        $this->assertEquals('open', strtolower($picklist->status));
        $this->assertCount(2, $picklist->items);
        $this->assertEquals(10000.0, (float) $picklist->total_weight);

        // 2. Submit Weigh-in (Timbang Kosong)
        $weighInRes = $this->actingAs($this->user)->postJson("/api/distributor-channel/v1/logistic/picklists/{$picklistId}/weigh-in", [
            'weight'        => 8500.0,
            'ticket_no'     => 'TMB-IN-001',
            'operator_name' => 'Petugas Timbangan',
            'notes'         => 'Kondisi truk kosong',
        ]);

        $weighInRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'weigh_in')
            ->assertJsonPath('data.weigh_in_weight', 8500);

        $picklist->refresh();
        $this->assertEquals('weigh_in', $picklist->status);
        $this->assertEquals(8500.0, (float) $picklist->weigh_in_weight);

        $item1Model = $picklist->items->firstWhere('item_code', $this->item1->item_code);
        $item2Model = $picklist->items->firstWhere('item_code', $this->item2->item_code);

        // Sample Base64 image
        $sampleBase64Photo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        // 3. Multi-checker test: Checker 1 verifies item 1 only -> Picklist stays inprogress
        $checker1Res = $this->actingAs($this->user)->postJson("/api/distributor-channel/v1/logistic/picklists/{$picklistId}/add-do", [
            'checker_name' => 'Checker Nanang',
            'items' => [
                [
                    'picklist_item_id' => $item1Model->id,
                    'checked_qty'      => 100,
                    'is_checked'       => true,
                    'start_time'       => '2026-10-14 09:00:00',
                    'end_time'         => '2026-10-14 09:30:00',
                    'production_date'  => '2026-10-10',
                    'label_photos'     => [$sampleBase64Photo],
                ],
            ],
            'signatures' => [
                'checker' => $sampleBase64Photo,
            ],
        ]);

        $checker1Res->assertStatus(200)
            ->assertJsonPath('data.status', 'inprogress')
            ->assertJsonPath('data.is_fully_checked', false);

        $picklist->refresh();
        $this->assertEquals('inprogress', $picklist->status);

        $item1Model->refresh();
        $this->assertTrue((bool) $item1Model->is_checked);
        $this->assertEquals('2026-10-10', $item1Model->production_date->format('Y-m-d'));
        $this->assertNotNull($item1Model->start_time);
        $this->assertNotNull($item1Model->end_time);
        $this->assertCount(1, $item1Model->labelPhotos);

        // 4. Multi-checker test: Checker 2 verifies item 2 -> All items checked -> Status becomes weigh_out (DO not created yet!)
        $checker2Res = $this->actingAs($this->user)->postJson("/api/distributor-channel/v1/logistic/picklists/{$picklistId}/add-do", [
            'checker_name' => 'Checker Rochim',
            'items' => [
                [
                    'picklist_item_id' => $item2Model->id,
                    'checked_qty'      => 200,
                    'is_checked'       => true,
                    'start_time'       => '2026-10-14 09:35:00',
                    'end_time'         => '2026-10-14 10:15:00',
                    'production_date'  => '2026-10-11',
                    'label_photos'     => [$sampleBase64Photo],
                ],
            ],
        ]);

        $checker2Res->assertStatus(200)
            ->assertJsonPath('data.status', 'weigh_out')
            ->assertJsonPath('data.is_fully_checked', true);

        $picklist->refresh();
        $this->assertEquals('weigh_out', $picklist->status);
        $this->assertNull($picklist->delivery_order_no); // DO is NOT created yet!

        // 5. Test preview label photo
        $labelPhoto = $item1Model->labelPhotos->first();
        $this->assertNotNull($labelPhoto);

        $previewRes = $this->get("/api/distributor-channel/v1/logistic/picklists/{$picklistId}/items/{$item1Model->id}/label-photos/{$labelPhoto->id}/preview");
        $previewRes->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png');

        // 6. Test Weigh-out Overload: Limit is 12000 kg, Tare is 8500 kg. If Gross is 22000 kg -> Net is 13500 kg (> 12000 kg!)
        $overloadRes = $this->actingAs($this->user)->postJson("/api/distributor-channel/v1/logistic/picklists/{$picklistId}/weigh-out", [
            'weight'        => 22000.0,
            'ticket_no'     => 'TMB-OUT-OVERLOAD',
            'operator_name' => 'Petugas Timbangan',
        ]);

        $overloadRes->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.status', 'weigh_out')
            ->assertJsonPath('data.weight_summary.weight_check_status', 'overload');

        $picklist->refresh();
        $this->assertEquals('weigh_out', $picklist->status);
        $this->assertNull($picklist->delivery_order_no);

        // 7. Test Weigh-out Valid (Muatan Sesuai): Tare 8500 kg + Net 10000 kg = Gross 18500 kg
        $validWeighOutRes = $this->actingAs($this->user)->postJson("/api/distributor-channel/v1/logistic/picklists/{$picklistId}/weigh-out", [
            'weight'        => 18500.0,
            'ticket_no'     => 'TMB-OUT-001',
            'operator_name' => 'Petugas Timbangan',
            'notes'         => 'Muatan sesuai, segel SL-123 terpasang',
        ]);

        $validWeighOutRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'complete')
            ->assertJsonPath('data.weight_summary.weight_check_status', 'sesuai')
            ->assertJsonPath('data.delivery_order.do_doc_num', '9001');

        $picklist->refresh();
        $this->assertEquals('complete', $picklist->status);
        $this->assertEquals('9001', $picklist->do_doc_num);
        $this->assertEquals('SUCCESS', $picklist->do_status);
        $this->assertEquals(10000.0, (float) $picklist->weigh_out_net_weight);
    }
}
