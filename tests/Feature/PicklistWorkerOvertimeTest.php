<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Item;
use App\Models\LoadingRate;
use App\Models\LoadingRateType;
use App\Models\Picklist;
use App\Models\PicklistItem;
use App\Models\PicklistWorker;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PicklistWorkerOvertimeTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Distributor $distributor;
    protected Item $item1;
    protected Item $item2;
    protected SalesOrder $order;
    protected SalesOrderDetail $detail1;
    protected SalesOrderDetail $detail2;
    protected LoadingRateType $otType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrCreate(
            ['username' => 'worker_tester'],
            [
                'name'      => 'Worker Tester',
                'email'     => 'worker_tester@example.com',
                'password'  => bcrypt('secret123'),
                'role'      => 'logistic',
                'is_active' => true,
            ]
        );

        $this->distributor = Distributor::firstOrCreate(
            ['code_customer' => 'CUST-WRK-001'],
            [
                'name' => 'Distributor Worker Test',
                'depo' => 'SURABAYA',
            ]
        );

        $this->item1 = Item::firstOrCreate(
            ['item_code' => 'SKU-WRK-01'],
            [
                'item_name'    => 'Garam Kasar 50kg',
                'sal_unit_msr' => 'SAK',
                'per_kg'       => 50.0,
            ]
        );

        $this->item2 = Item::firstOrCreate(
            ['item_code' => 'SKU-WRK-02'],
            [
                'item_name'    => 'Garam Halus 25kg',
                'sal_unit_msr' => 'SAK',
                'per_kg'       => 25.0,
            ]
        );

        $this->otType = LoadingRateType::firstOrCreate(
            ['code' => 'OVERTIME'],
            [
                'name'       => 'Overtime / Lembur',
                'start_time' => '16:30:00',
                'end_time'   => '02:00:00',
                'is_active'  => true,
            ]
        );

        // Setup loading rate for SKU-WRK-01
        LoadingRate::updateOrCreate(
            ['item_code' => 'SKU-WRK-01', 'rate_type' => 'OVERTIME'],
            [
                'item_name'    => 'Garam Kasar 50kg',
                'rate_type_id' => $this->otType->id,
                'rate'         => 1500,
                'uom'          => 'SAK',
                'status'       => 'ACTIVE',
            ]
        );

        $this->order = SalesOrder::create([
            'order_no'        => 'SO-WRK-' . uniqid(),
            'sap_doc_num'     => '88001',
            'sap_doc_entry'   => 88001,
            'docentry'        => 88001,
            'distributor_id'  => $this->distributor->id,
            'card_code'       => 'CUST-WRK-001',
            'customer_name'   => 'Customer Worker Test',
            'doc_date'        => '2026-10-22',
            'req_due_date'    => '2026-10-22',
            'doc_due_date'    => '2026-10-22',
            'status'          => 'ORDER_APPROVED',
            'logistic_status' => 'APPROVED',
            'grand_total'     => 1000000,
        ]);

        $this->detail1 = SalesOrderDetail::create([
            'sales_order_id' => $this->order->id,
            'item_code'      => $this->item1->item_code,
            'line_num'       => 0,
            'baseline'       => 0,
            'whs_code'       => 'WHS-SBY',
            'quantity'       => 20,
            'unit_msr'       => 'SAK',
            'unit_price'     => 50000,
            'line_total'     => 1000000,
        ]);

        $this->detail2 = SalesOrderDetail::create([
            'sales_order_id' => $this->order->id,
            'item_code'      => $this->item2->item_code,
            'line_num'       => 1,
            'baseline'       => 1,
            'whs_code'       => 'WHS-SBY',
            'quantity'       => 10,
            'unit_msr'       => 'SAK',
            'unit_price'     => 50000,
            'line_total'     => 500000,
        ]);
    }

    public function test_add_do_saves_workers_and_calculates_overtime_in_response(): void
    {
        $picklist = Picklist::create([
            'picklist_no'     => 'PKL-WRK-001',
            'shipping_type'   => 'internal',
            'status'          => Picklist::STATUS_WEIGH_IN,
            'posting_date'    => '2026-10-22',
            'due_date'        => '2026-10-22',
            'license_plate'   => 'L 1234 WRK',
            'driver_name'     => 'Pak Supir',
            'checker_name'    => 'Checker Budi',
            'weigh_in_weight' => 5000.0,
            'created_by'      => $this->user->id,
        ]);

        $pItem1 = PicklistItem::create([
            'picklist_id'           => $picklist->id,
            'sales_order_id'        => $this->order->id,
            'sales_order_detail_id' => $this->detail1->id,
            'item_code'             => $this->item1->item_code,
            'item_name'             => $this->item1->item_name,
            'whs_code'              => 'WHS-SBY',
            'unit_msr'              => 'SAK',
            'ordered_qty'           => 20,
            'pick_qty'              => 20,
            'is_checked'            => false,
        ]);

        $samplePngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        $payload = [
            'is_draft'     => true,
            'checker_name' => 'Checker Budi',
            'signatures'   => [
                [
                    'signer_type' => 'checker',
                    'signer_name' => 'Checker Budi',
                    'signature'   => $samplePngBase64,
                ],
            ],
            'workers'      => [
                [
                    'item_code'   => 'SKU-WRK-01',
                    'worker_name' => 'Slamet',
                    'qty'         => 300,
                ],
                [
                    'item_code'   => 'SKU-WRK-02',
                    'worker_name' => 'Agus',
                    'qty'         => 200,
                    'rate'        => 2500, // Custom rate override
                ],
                [
                    'item_code'   => 'SKU-WRK-01',
                    'worker_name' => 'Budi',
                    'qty'         => 150,
                ],
            ],
            'Lines'        => [
                [
                    'picklist_item_id' => $pItem1->id,
                    'is_checked'       => true,
                    'checked_qty'      => 20,
                ],
            ],
        ];

        $res = $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}/add-do", $payload);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.picklist_id', $picklist->id)
            ->assertJsonCount(1, 'data.signatures')
            ->assertJsonCount(3, 'data.workers');

        $data = $res->json('data');

        // Check workers array content and calculations
        // Worker 0: Slamet, SKU-WRK-01, rate from DB (1500), lembur_fee = 300 * 1500 = 450,000
        $w0 = $data['workers'][0];
        $this->assertEquals('SKU-WRK-01', $w0['item_code']);
        $this->assertEquals('Slamet', $w0['worker_name']);
        $this->assertEquals(300.0, (float) $w0['qty']);
        $this->assertEquals(1500.0, (float) $w0['rate']);
        $this->assertEquals(450000.0, (float) $w0['lembur_fee']);
        $this->assertEquals('SAK', $w0['uom']);

        // Worker 1: Agus, SKU-WRK-02, custom rate (2500), lembur_fee = 200 * 2500 = 500,000
        $w1 = $data['workers'][1];
        $this->assertEquals('SKU-WRK-02', $w1['item_code']);
        $this->assertEquals('Agus', $w1['worker_name']);
        $this->assertEquals(200.0, (float) $w1['qty']);
        $this->assertEquals(2500.0, (float) $w1['rate']);
        $this->assertEquals(500000.0, (float) $w1['lembur_fee']);

        // Worker 2: Budi, SKU-WRK-01, rate 1500, lembur_fee = 150 * 1500 = 225,000
        $w2 = $data['workers'][2];
        $this->assertEquals('Budi', $w2['worker_name']);
        $this->assertEquals(150.0, (float) $w2['qty']);
        $this->assertEquals(1500.0, (float) $w2['rate']);
        $this->assertEquals(225000.0, (float) $w2['lembur_fee']);

        // Total lembur = 450000 + 500000 + 225000 = 1,175,000
        $this->assertEquals(1175000.0, (float) $data['total_lembur']);

        // Verify DB records
        $dbWorkers = PicklistWorker::where('picklist_id', $picklist->id)->get();
        $this->assertCount(3, $dbWorkers);

        // Verify GET picklist detail returns workers and total_lembur
        $detailRes = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}");

        $detailRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.workers')
            ->assertJsonPath('data.total_lembur', 1175000);
    }

    public function test_add_do_with_sap_completion_also_returns_workers_and_total_lembur(): void
    {
        Http::fake([
            '*/api/AddDO' => Http::response([
                'ErrorCode' => 0,
                'Message'   => 'DO created successfully. DocNum: 9911 DocEntry: 8822',
                'Result'    => [
                    'DocNum'   => 9911,
                    'DocEntry' => 8822,
                ],
            ], 200),
        ]);

        $picklist = Picklist::create([
            'picklist_no'     => 'PKL-WRK-002',
            'shipping_type'   => 'internal',
            'status'          => Picklist::STATUS_WEIGH_IN,
            'posting_date'    => '2026-10-22',
            'due_date'        => '2026-10-22',
            'license_plate'   => 'L 9999 SAP',
            'driver_name'     => 'Driver Sap',
            'checker_name'    => 'Checker Sap',
            'weigh_in_weight' => 6000.0,
            'created_by'      => $this->user->id,
        ]);

        PicklistItem::create([
            'picklist_id'           => $picklist->id,
            'sales_order_id'        => $this->order->id,
            'sales_order_detail_id' => $this->detail1->id,
            'item_code'             => $this->item1->item_code,
            'item_name'             => $this->item1->item_name,
            'whs_code'              => 'WHS-SBY',
            'unit_msr'              => 'SAK',
            'ordered_qty'           => 20,
            'pick_qty'              => 20,
            'is_checked'            => false,
        ]);

        $payload = [
            'force_sap_do' => true,
            'checker_name' => 'Checker Sap',
            'workers'      => [
                [
                    'item_code'   => 'SKU-WRK-01',
                    'worker_name' => 'Mandor Bambang',
                    'qty'         => 400,
                ],
            ],
            'Lines'        => [
                [
                    'sales_order_id' => $this->order->id,
                    'ItemCode'       => $this->item1->item_code,
                    'Quantity'       => 20,
                ],
            ],
        ];

        $res = $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}/add-do", $payload);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.doc_num', '9911')
            ->assertJsonCount(1, 'data.workers')
            ->assertJsonPath('data.workers.0.worker_name', 'Mandor Bambang')
            ->assertJsonPath('data.workers.0.lembur_fee', 600000) // 400 * 1500
            ->assertJsonPath('data.total_lembur', 600000);
    }
}
