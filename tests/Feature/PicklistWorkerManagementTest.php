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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PicklistWorkerManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Distributor $distributor;
    protected Item $item1;
    protected Item $item2;
    protected SalesOrder $order;
    protected SalesOrderDetail $detail1;
    protected SalesOrderDetail $detail2;
    protected LoadingRateType $regType;
    protected LoadingRateType $otType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrCreate(
            ['username' => 'worker_mgmt_user'],
            [
                'name'      => 'Worker Mgmt User',
                'email'     => 'worker_mgmt@example.com',
                'password'  => bcrypt('secret123'),
                'role'      => 'logistic',
                'is_active' => true,
            ]
        );

        $this->distributor = Distributor::firstOrCreate(
            ['code_customer' => 'CUST-MGMT-001'],
            [
                'name' => 'Distributor Mgmt Test',
                'depo' => 'SURABAYA',
            ]
        );

        $this->item1 = Item::firstOrCreate(
            ['item_code' => 'SKU-MGMT-01'],
            [
                'item_name'    => 'Garam Kasar 50kg',
                'sal_unit_msr' => 'SAK',
                'per_kg'       => 50.0,
            ]
        );

        $this->item2 = Item::firstOrCreate(
            ['item_code' => 'SKU-MGMT-02'],
            [
                'item_name'    => 'Garam Halus 25kg',
                'sal_unit_msr' => 'SAK',
                'per_kg'       => 25.0,
            ]
        );

        $this->regType = LoadingRateType::firstOrCreate(
            ['code' => 'REGULAR'],
            [
                'name'       => 'Reguler',
                'start_time' => '08:00:00',
                'end_time'   => '16:30:00',
                'is_active'  => true,
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

        // Setup loading rates for SKU-MGMT-01
        LoadingRate::updateOrCreate(
            ['item_code' => 'SKU-MGMT-01', 'rate_type' => 'REGULAR'],
            [
                'item_name'    => 'Garam Kasar 50kg',
                'rate_type_id' => $this->regType->id,
                'rate'         => 1000,
                'uom'          => 'SAK',
                'status'       => 'ACTIVE',
            ]
        );

        LoadingRate::updateOrCreate(
            ['item_code' => 'SKU-MGMT-01', 'rate_type' => 'OVERTIME'],
            [
                'item_name'    => 'Garam Kasar 50kg',
                'rate_type_id' => $this->otType->id,
                'rate'         => 1500,
                'uom'          => 'SAK',
                'status'       => 'ACTIVE',
            ]
        );

        // Setup loading rates for SKU-MGMT-02
        LoadingRate::updateOrCreate(
            ['item_code' => 'SKU-MGMT-02', 'rate_type' => 'REGULAR'],
            [
                'item_name'    => 'Garam Halus 25kg',
                'rate_type_id' => $this->regType->id,
                'rate'         => 600,
                'uom'          => 'SAK',
                'status'       => 'ACTIVE',
            ]
        );

        LoadingRate::updateOrCreate(
            ['item_code' => 'SKU-MGMT-02', 'rate_type' => 'OVERTIME'],
            [
                'item_name'    => 'Garam Halus 25kg',
                'rate_type_id' => $this->otType->id,
                'rate'         => 900,
                'uom'          => 'SAK',
                'status'       => 'ACTIVE',
            ]
        );

        $this->order = SalesOrder::create([
            'order_no'        => 'SO-MGMT-' . uniqid(),
            'sap_doc_num'     => '77001',
            'sap_doc_entry'   => 77001,
            'docentry'        => 77001,
            'distributor_id'  => $this->distributor->id,
            'card_code'       => 'CUST-MGMT-001',
            'customer_name'   => 'Customer Mgmt Test',
            'doc_date'        => '2026-10-22',
            'req_due_date'    => '2026-10-22',
            'doc_due_date'    => '2026-10-22',
            'status'          => 'ORDER_APPROVED',
            'logistic_status' => 'APPROVED',
            'grand_total'     => 1500000,
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

    public function test_get_worker_management_picklists_returns_submitted_picklists_with_calculations(): void
    {
        // Picklist 1: Submitted by checker during overtime (e.g. 17:30:00), status weigh_out
        $picklist1 = Picklist::create([
            'picklist_no'       => 'PKL-MGMT-001',
            'shipping_type'     => 'internal',
            'status'            => Picklist::STATUS_WEIGH_OUT,
            'posting_date'      => '2026-10-22',
            'due_date'          => '2026-10-22',
            'delivery_order_no' => 'DO-9901',
            'license_plate'     => 'L 1111 MGMT',
            'driver_name'       => 'Driver Overtime',
            'checker_name'      => 'Checker Rochim',
            'weigh_in_weight'   => 5000.0,
            'weigh_out_weight'  => 8000.0,
            'weigh_out_at'      => Carbon::parse('2026-10-22 17:45:00'),
            'created_by'        => $this->user->id,
        ]);

        PicklistItem::create([
            'picklist_id'           => $picklist1->id,
            'sales_order_id'        => $this->order->id,
            'sales_order_detail_id' => $this->detail1->id,
            'item_code'             => $this->item1->item_code,
            'item_name'             => $this->item1->item_name,
            'whs_code'              => 'WHS-SBY',
            'unit_msr'              => 'SAK',
            'ordered_qty'           => 20,
            'pick_qty'              => 20,
            'checked_qty'           => 20,
            'is_checked'            => true,
            'checked_by_checker'    => 'Checker Rochim',
            'checked_at'            => Carbon::parse('2026-10-22 17:30:00'),
            'end_time'              => Carbon::parse('2026-10-22 17:30:00'),
        ]);

        PicklistWorker::create([
            'picklist_id' => $picklist1->id,
            'item_code'   => 'SKU-MGMT-01',
            'item_name'   => 'Garam Kasar 50kg',
            'worker_name' => 'Slamet',
            'qty'         => 300,
            'uom'         => 'SAK',
            'rate_type'   => 'OVERTIME',
            'rate'        => 1500,
            'lembur_fee'  => 450000,
            'created_by'  => $this->user->id,
        ]);

        // Picklist 2: Picklist still OPEN without checker submit (should NOT appear in worker management list)
        $picklistOpen = Picklist::create([
            'picklist_no'     => 'PKL-OPEN-001',
            'shipping_type'   => 'internal',
            'status'          => Picklist::STATUS_OPEN,
            'posting_date'    => '2026-10-22',
            'due_date'        => '2026-10-22',
            'license_plate'   => 'L 2222 OPN',
            'driver_name'     => 'Driver Open',
            'checker_name'    => 'Checker Nanang',
            'created_by'      => $this->user->id,
        ]);

        // Test Endpoint 1: Dedicated path
        $res = $this->actingAs($this->user)
            ->getJson('/api/distributor-channel/v1/logistic/worker-management/picklists');

        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $res->json('data');
        $this->assertNotEmpty($data);

        // Find picklist 1 in list
        $found = collect($data)->firstWhere('id', $picklist1->id);
        $this->assertNotNull($found, 'Picklist 1 should be included');
        $this->assertEquals('PKL-MGMT-001', $found['picklist_no']);
        $this->assertEquals('DO-9901', $found['delivery_order_no']);

        // Check calculation summary
        $summary = $found['calculation_summary'];
        $this->assertEquals('OVERTIME', $summary['determined_rate_type']);
        $this->assertTrue($summary['is_overtime']);
        $this->assertEquals(300.0, (float) $summary['total_qty']);
        $this->assertEquals(450000.0, (float) $summary['total_overtime_amount']);
        $this->assertEquals(300000.0, (float) $summary['total_regular_amount']); // 300 * 1000
        $this->assertEquals(450000.0, (float) $summary['total_fee']);

        // Ensure open picklist is not listed
        $foundOpen = collect($data)->firstWhere('id', $picklistOpen->id);
        $this->assertNull($foundOpen, 'Open picklist without checker submit should not be in worker management list');

        // Test Alias path: /v1/logistic/picklists/worker-management
        $resAlias = $this->actingAs($this->user)
            ->getJson('/api/distributor-channel/v1/logistic/picklists/worker-management');
        $resAlias->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_get_worker_management_detail_returns_complete_breakdown(): void
    {
        // Picklist submitted during regular hours (14:00:00)
        $picklist = Picklist::create([
            'picklist_no'       => 'PKL-MGMT-002',
            'shipping_type'     => 'internal',
            'status'            => Picklist::STATUS_COMPLETED,
            'posting_date'      => '2026-10-22',
            'due_date'          => '2026-10-22',
            'delivery_order_no' => 'DO-9902',
            'license_plate'     => 'L 3333 REG',
            'driver_name'       => 'Driver Regular',
            'checker_name'      => 'Checker Nanang',
            'weigh_in_weight'   => 4000.0,
            'weigh_out_weight'  => 7000.0,
            'weigh_out_at'      => Carbon::parse('2026-10-22 14:15:00'),
            'created_by'        => $this->user->id,
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
            'checked_qty'           => 20,
            'is_checked'            => true,
            'checked_by_checker'    => 'Checker Nanang',
            'checked_at'            => Carbon::parse('2026-10-22 14:00:00'),
            'end_time'              => Carbon::parse('2026-10-22 14:00:00'),
        ]);

        // Worker 1: Agus handles 200 SAK SKU-MGMT-01
        PicklistWorker::create([
            'picklist_id' => $picklist->id,
            'item_code'   => 'SKU-MGMT-01',
            'item_name'   => 'Garam Kasar 50kg',
            'worker_name' => 'Agus',
            'qty'         => 200,
            'uom'         => 'SAK',
            'rate_type'   => 'REGULAR',
            'rate'        => 1000,
            'lembur_fee'  => 200000,
            'created_by'  => $this->user->id,
        ]);

        // Worker 2: Budi handles 100 SAK SKU-MGMT-02
        PicklistWorker::create([
            'picklist_id' => $picklist->id,
            'item_code'   => 'SKU-MGMT-02',
            'item_name'   => 'Garam Halus 25kg',
            'worker_name' => 'Budi',
            'qty'         => 100,
            'uom'         => 'SAK',
            'rate_type'   => 'REGULAR',
            'rate'        => 600,
            'lembur_fee'  => 60000,
            'created_by'  => $this->user->id,
        ]);

        $res = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/logistic/worker-management/picklists/{$picklist->id}");

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.picklist.picklist_no', 'PKL-MGMT-002')
            ->assertJsonPath('data.calculation_summary.determined_rate_type', 'REGULAR')
            ->assertJsonPath('data.calculation_summary.is_overtime', false)
            ->assertJsonPath('data.calculation_summary.total_workers', 2)
            ->assertJsonPath('data.calculation_summary.total_qty', 300)
            ->assertJsonPath('data.calculation_summary.total_regular_amount', 260000) // (200*1000) + (100*600)
            ->assertJsonPath('data.calculation_summary.total_overtime_amount', 390000) // (200*1500) + (100*900)
            ->assertJsonPath('data.calculation_summary.total_fee', 260000)
            ->assertJsonCount(2, 'data.workers')
            ->assertJsonCount(2, 'data.breakdown_by_worker')
            ->assertJsonCount(2, 'data.breakdown_by_item');

        $workerData = $res->json('data.workers');
        $this->assertEquals('Agus', $workerData[0]['worker_name']);
        $this->assertEquals('SAK', $workerData[0]['uom']);
        $this->assertEquals(1000, $workerData[0]['regular_rate']);
        $this->assertEquals(1500, $workerData[0]['overtime_rate']);
        $this->assertEquals(200000, $workerData[0]['regular_fee']);
        $this->assertEquals(300000, $workerData[0]['lembur_fee']);
        $this->assertEquals(200000, $workerData[0]['total_fee']);

        // Check alias route: /v1/logistic/picklists/{id}/worker-management
        $resAlias = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}/worker-management");
        $resAlias->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
