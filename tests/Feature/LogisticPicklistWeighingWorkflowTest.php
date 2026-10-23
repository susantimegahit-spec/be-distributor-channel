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

        $samplePhoto1 = $sampleBase64Photo;
        $samplePhoto2 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $samplePhoto3 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';

        // 3. Multi-checker test: Checker 1 verifies item 1 only with MULTIPLE label photos -> Picklist stays inprogress
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
                    'label_photos'     => [$samplePhoto1, $samplePhoto2], // MULTIPLE PHOTOS
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
        $this->assertCount(2, $item1Model->labelPhotos); // MULTIPLE VERIFIED (2 PHOTOS)

        // 4. Multi-checker test: Checker 2 verifies item 2 with 3 label photos -> All items checked -> Status becomes weigh_out
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
                    'label_photos'     => [$samplePhoto1, $samplePhoto2, $samplePhoto3], // MULTIPLE PHOTOS (3)
                ],
            ],
        ]);

        $checker2Res->assertStatus(200)
            ->assertJsonPath('data.status', 'weigh_out')
            ->assertJsonPath('data.is_fully_checked', true);

        $picklist->refresh();
        $this->assertEquals('weigh_out', $picklist->status);
        $this->assertNull($picklist->delivery_order_no); // DO is NOT created yet!

        $item2Model->refresh();
        $this->assertCount(3, $item2Model->labelPhotos); // MULTIPLE VERIFIED (3 PHOTOS)

        // 5. Test preview label photo for both photos of item 1
        $labelPhotos = $item1Model->labelPhotos;
        $this->assertCount(2, $labelPhotos);

        foreach ($labelPhotos as $photo) {
            $previewRes = $this->get("/api/distributor-channel/v1/logistic/picklists/{$picklistId}/items/{$item1Model->id}/label-photos/{$photo->id}/preview");
            $previewRes->assertStatus(200)
                ->assertHeader('Content-Type', 'image/png');
        }

        // 6. Test Weigh-out Overload: Limit is 12000 kg, Tare is 8500 kg. If Gross is 22000 kg -> Net is 13500 kg (> 12000 kg!)
        $overloadRes = $this->actingAs($this->user)->postJson("/api/distributor-channel/v1/logistic/picklists/{$picklistId}/weigh-out", [
            'weight'        => 22000.0,
            'ticket_no'     => 'TMB-OUT-OVERLOAD',
            'operator_name' => 'Petugas Timbangan',
        ]);

        $overloadRes->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.status', 'overload')
            ->assertJsonPath('data.weight_summary.weight_check_status', 'overload');

        $picklist->refresh();
        $this->assertEquals('overload', $picklist->status);
        $this->assertNull($picklist->delivery_order_no);
        $this->assertCount(1, $picklist->overloads);
        $this->assertEquals(1500.0, (float) $picklist->latestOverload->overload_weight);
        $this->assertEquals('PENDING_ADJUSTMENT', $picklist->latestOverload->status);

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

    /**
     * Test checker submission with nested labels array where each label has its own production_date and label_photos.
     */
    public function test_add_do_with_labels_array_containing_multiple_production_dates_and_photos(): void
    {
        $so = SalesOrder::create([
            'order_no'         => 'SO-TEST-LBL-001',
            'doc_date'         => now(),
            'distributor_id'   => $this->distributor->id,
            'card_code'        => $this->distributor->code_customer,
            'customer_name'    => 'PT Distribusi Logistik',
            'status'           => 'APPROVED',
            'sap_doc_num'      => 'SO-SAP-9901',
            'sap_doc_entry'    => 1088,
        ]);

        $detail = SalesOrderDetail::create([
            'sales_order_id' => $so->id,
            'item_code'      => $this->item1->item_code,
            'item_name'      => $this->item1->item_name,
            'quantity'       => 50,
            'unit_price'     => 10000,
            'line_total'     => 500000,
            'baseline'       => 0,
            'line_num'       => 0,
        ]);

        $picklist = Picklist::create([
            'picklist_no'   => 'PKL-TEST-LBL-001',
            'posting_date'  => '2026-10-23',
            'due_date'      => '2026-10-24',
            'status'        => Picklist::STATUS_IN_PROGRESS,
            'shipping_type' => 'pickup',
            'tare_weight'   => 5000,
            'created_by'    => $this->user->id,
        ]);

        $itemModel = PicklistItem::create([
            'picklist_id'            => $picklist->id,
            'sales_order_id'         => $so->id,
            'sales_order_detail_id'  => $detail->id,
            'item_code'              => $this->item1->item_code,
            'item_name'              => $this->item1->item_name,
            'ordered_qty'            => 50,
            'pick_qty'               => 50,
            'uom'                    => 'KG',
            'unit_weight'            => 1.0,
            'total_weight'           => 50.0,
        ]);

        $samplePhoto1 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $samplePhoto2 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFElEQVR42mNk+M9QzwAEjAw/GBgYAAyOAgdG5oVfAAAAAElFTkSuQmCC';
        $samplePhoto3 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $response = $this->actingAs($this->user)->postJson("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}/add-do", [
            'checker_name' => 'Checker Nanang',
            'Lines' => [
                [
                    'id'                 => $itemModel->id,
                    'BaseEntry'          => 1088,
                    'BaseLine'           => 0,
                    'Quantity'           => 50,
                    'checked_qty'        => 50,
                    'is_checked'         => true,
                    'start_time'         => '2026-10-15 08:00:00',
                    'end_time'           => '2026-10-15 08:30:00',
                    'labels'             => [
                        [
                            'production_date' => '2026-10-10',
                            'label_photos'    => [$samplePhoto1, $samplePhoto2],
                        ],
                        [
                            'production_date' => '2026-10-12',
                            'label_photos'    => [$samplePhoto3],
                        ],
                    ],
                ],
            ],
            'signatures' => [
                'checker' => $samplePhoto1,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $itemModel->refresh();
        $this->assertTrue((bool) $itemModel->is_checked);
        $this->assertEquals('2026-10-10', $itemModel->production_date->format('Y-m-d'));
        $this->assertCount(3, $itemModel->labelPhotos);

        // Verify each photo has the correct production_date
        $photos = $itemModel->labelPhotos->sortBy('id')->values();
        $this->assertEquals('2026-10-10', $photos[0]->production_date->format('Y-m-d'));
        $this->assertEquals('2026-10-10', $photos[1]->production_date->format('Y-m-d'));
        $this->assertEquals('2026-10-12', $photos[2]->production_date->format('Y-m-d'));

        // Test GET picklist detail endpoint returns items with labels array properly grouped
        $detailRes = $this->actingAs($this->user)->getJson("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}");
        $detailRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', $itemModel->id)
            ->assertJsonCount(2, 'data.items.0.labels')
            ->assertJsonPath('data.items.0.labels.0.production_date', '2026-10-10')
            ->assertJsonCount(2, 'data.items.0.labels.0.label_photos')
            ->assertJsonPath('data.items.0.labels.1.production_date', '2026-10-12')
            ->assertJsonCount(1, 'data.items.0.labels.1.label_photos');
    }

    /**
     * Test explicit reject/cancel weigh-out endpoint and retrieval from temporary overload table.
     */
    public function test_reject_weigh_out_overload_endpoint_and_get_overload_records(): void
    {
        $so = SalesOrder::create([
            'order_no'         => 'SO-LOG-OVERLOAD-REJECT',
            'doc_date'         => now(),
            'distributor_id'   => $this->distributor->id,
            'card_code'        => $this->distributor->code_customer,
            'customer_name'    => 'PT Distribusi Logistik',
            'status'           => 'APPROVED',
            'sap_doc_num'      => 'SO-SAP-9999',
            'sap_doc_entry'    => 1099,
        ]);

        $soDetail = SalesOrderDetail::create([
            'sales_order_id' => $so->id,
            'item_code'      => $this->item1->item_code,
            'item_name'      => $this->item1->item_name,
            'quantity'       => 200,
            'unit_price'     => 25000,
            'line_total'     => 5000000,
            'baseline'       => 0,
            'line_num'       => 0,
        ]);

        $picklist = Picklist::create([
            'picklist_no'        => 'PKL-REJECT-OL-001',
            'shipping_type'      => 'internal',
            'status'             => 'weigh_in',
            'posting_date'       => '2026-10-23',
            'due_date'           => '2026-10-24',
            'total_weight_limit' => 10000.0, // 10 ton limit
            'total_weight'       => 10000.0,
            'weigh_in_weight'    => 8000.0, // 8 ton tare
            'weigh_in_ticket_no' => 'TMB-IN-OL-01',
            'weigh_in_by'        => 'Petugas Timbangan Masuk',
            'weigh_in_at'        => now(),
        ]);

        $picklistItem = PicklistItem::create([
            'picklist_id'            => $picklist->id,
            'sales_order_id'         => $so->id,
            'sales_order_detail_id'  => $soDetail->id,
            'item_code'              => 'FG-OVERLOAD-01',
            'item_name'              => 'Garam Kasar Overload 50kg',
            'whs_code'               => 'VPGMN01',
            'unit_msr'               => 'SAK',
            'ordered_qty'            => 200,
            'pick_qty'               => 200,
            'unit_weight'            => 50.0,
            'total_weight'           => 10000.0,
            'is_checked'             => true,
        ]);

        // Gross weight 21000 kg -> Net weight = 21000 - 8000 = 13000 kg (> limit 10000 kg -> Overload 3000 kg!)
        $rejectRes = $this->actingAs($this->user)->postJson("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}/weigh-out/reject", [
            'weight'           => 21000.0,
            'ticket_no'        => 'TMB-OUT-REJECT-99',
            'operator_name'    => 'Operator Jembatan Timbang',
            'rejection_reason' => 'Kelebihan muatan armada truk sebesar 3,000 KG. Ditolak dan diarahkan bongkar muatan.',
        ]);

        $rejectRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'overload')
            ->assertJsonPath('data.is_overload', true)
            ->assertJsonPath('data.overload_record.ticket_no', 'TMB-OUT-REJECT-99');

        $this->assertEquals(21000.0, (float) $rejectRes->json('data.weight_summary.gross_weight'));
        $this->assertEquals(13000.0, (float) $rejectRes->json('data.weight_summary.net_weight'));
        $this->assertEquals(10000.0, (float) $rejectRes->json('data.weight_summary.weight_limit'));
        $this->assertEquals(3000.0, (float) $rejectRes->json('data.overload_record.overload_weight'));

        $picklist->refresh();
        $this->assertEquals('overload', $picklist->status);
        $this->assertEquals('overload', $picklist->weight_check_status);
        $this->assertNull($picklist->delivery_order_no);

        // Verify temporary overload table record
        $this->assertCount(1, $picklist->overloads);
        $overloadRecord = $picklist->latestOverload;
        $this->assertEquals(3000.0, (float) $overloadRecord->overload_weight);
        $this->assertEquals('PENDING_ADJUSTMENT', $overloadRecord->status);
        $this->assertNotEmpty($overloadRecord->items_snapshot);
        $this->assertEquals('FG-OVERLOAD-01', $overloadRecord->items_snapshot[0]['item_code']);

        // Test GET /v1/logistic/picklists/{id}/overload
        $getOverloadRes = $this->actingAs($this->user)->getJson("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}/overload");
        $getOverloadRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.picklist_id', $picklist->id)
            ->assertJsonPath('data.status', 'overload')
            ->assertJsonCount(1, 'data.history');

        $this->assertEquals(3000.0, (float) $getOverloadRes->json('data.latest_overload.overload_weight'));

        // Test GET /v1/logistic/picklists/overload (Get All Overloads without ID)
        $getAllOverloadsRes = $this->actingAs($this->user)->getJson("/api/distributor-channel/v1/logistic/picklists/overload");
        $getAllOverloadsRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Daftar data overload berhasil diambil.');

        $this->assertNotEmpty($getAllOverloadsRes->json('data'));
        $this->assertEquals($picklist->id, $getAllOverloadsRes->json('data.0.picklist_id'));
        $this->assertEquals('PKL-REJECT-OL-001', $getAllOverloadsRes->json('data.0.picklist_no'));
        $this->assertEquals(3000.0, (float) $getAllOverloadsRes->json('data.0.overload_weight'));
        $this->assertNotNull($getAllOverloadsRes->json('meta'));

        // Test GET /v1/logistic/picklists/overloads (Alias Route with search filter)
        $getAliasRes = $this->actingAs($this->user)->getJson("/api/distributor-channel/v1/logistic/picklists/overloads?search=REJECT-99");
        $getAliasRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ticket_no', 'TMB-OUT-REJECT-99');
    }
}


