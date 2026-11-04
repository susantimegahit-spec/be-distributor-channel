<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Item;
use App\Models\Picklist;
use App\Models\PicklistItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LogisticPicklistItemStartTimeTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Distributor $distributor;
    protected Item $item;
    protected SalesOrder $order;
    protected SalesOrderDetail $orderDetail;
    protected Picklist $picklist;
    protected PicklistItem $picklistItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->distributor = Distributor::firstOrCreate(
            ['code_customer' => 'CUST-TEST-STARTTIME'],
            [
                'name' => 'PT Distributor StartTime Test',
                'depo' => 'SURABAYA',
            ]
        );

        $this->user = User::firstOrCreate(
            ['username' => 'tester_starttime'],
            [
                'name'          => 'Checker StartTime',
                'email'         => 'tester_starttime@example.com',
                'password'      => bcrypt('password123'),
                'role'          => 'checker',
                'code_customer' => 'CUST-TEST-STARTTIME',
                'is_active'     => true,
            ]
        );

        $this->item = Item::firstOrCreate(
            ['item_code' => 'SKU-START-01'],
            [
                'item_name'    => 'Garam Halus 250gr',
                'sal_unit_msr' => 'SAK',
                'per_kg'       => 25.0,
            ]
        );

        $uid = uniqid();

        $this->order = SalesOrder::create([
            'order_no'        => 'SO-TEST-START-' . $uid,
            'sap_doc_entry'   => 998811,
            'sap_doc_num'     => '998811',
            'docentry'        => 998811,
            'distributor_id'  => $this->distributor->id,
            'card_code'       => $this->distributor->code_customer,
            'customer_name'   => 'Toko StartTime',
            'doc_date'        => now()->toDateString(),
            'req_due_date'    => now()->addDays(2)->toDateString(),
            'doc_due_date'    => now()->addDays(2)->toDateString(),
            'eta_date'        => now()->addDays(3)->toDateString(),
            'status'          => 'ORDER_APPROVED',
            'logistic_status' => 'APPROVED',
            'total_amount'    => 500000,
            'created_by'      => $this->user->id,
        ]);

        $this->orderDetail = SalesOrderDetail::create([
            'sales_order_id' => $this->order->id,
            'item_code'      => $this->item->item_code,
            'item_name'      => $this->item->item_name,
            'quantity'       => 10,
            'whs_code'       => 'WHS-TEST',
            'unit_price'     => 50000,
            'line_total'     => 500000,
            'line_num'       => 0,
            'baseline'       => 0,
        ]);

        $this->picklist = Picklist::create([
            'picklist_no'   => 'PKL-TEST-START-' . $uid,
            'shipping_type' => Picklist::SHIPPING_TYPE_INTERNAL,
            'status'        => Picklist::STATUS_OPEN,
            'posting_date'  => now()->toDateString(),
            'due_date'      => now()->addDays(2)->toDateString(),
            'total_weight'  => 250.0,
            'created_by'    => $this->user->id,
        ]);

        $this->picklistItem = PicklistItem::create([
            'picklist_id'            => $this->picklist->id,
            'sales_order_id'         => $this->order->id,
            'sales_order_detail_id'  => $this->orderDetail->id,
            'item_code'              => $this->item->item_code,
            'item_name'              => $this->item->item_name,
            'whs_code'               => 'WHS-TEST',
            'unit_msr'               => 'SAK',
            'ordered_qty'            => 10,
            'pick_qty'               => 10,
            'checked_qty'            => 0,
            'is_checked'             => false,
            'unit_weight'            => 25.0,
            'total_weight'           => 250.0,
        ]);
    }

    public function test_can_record_start_time_with_explicit_timestamp_and_checker_name(): void
    {
        $startTimeStr = '2026-10-21 08:30:00';

        $response = $this->actingAs($this->user)->postJson(
            "/api/distributor-channel/v1/logistic/picklists/{$this->picklist->id}/items/{$this->picklistItem->id}/start-time",
            [
                'start_time'   => $startTimeStr,
                'checker_name' => 'Nanang Checker',
            ]
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Start time recorded successfully.')
            ->assertJsonPath('data.item_id', $this->picklistItem->id)
            ->assertJsonPath('data.picklist_id', $this->picklist->id)
            ->assertJsonPath('data.picklist_status', 'inprogress')
            ->assertJsonPath('data.checked_by_checker', 'Nanang Checker')
            ->assertJsonPath('data.start_time', $startTimeStr)
            ->assertJsonPath('data.uom', 'SAK');

        $this->picklistItem->refresh();
        $this->assertEquals($startTimeStr, $this->picklistItem->start_time?->toDateTimeString());
        $this->assertEquals('Nanang Checker', $this->picklistItem->checked_by_checker);

        $this->picklist->refresh();
        $this->assertEquals('inprogress', $this->picklist->status);
        $this->assertEquals('Nanang Checker', $this->picklist->checker_name);
    }

    public function test_defaults_to_now_when_start_time_is_omitted(): void
    {
        $before = now()->subSeconds(2);

        $response = $this->actingAs($this->user)->postJson(
            "/api/distributor-channel/v1/logistic/picklists/{$this->picklist->id}/items/{$this->picklistItem->id}/start-time",
            []
        );

        $after = now()->addSeconds(2);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->picklistItem->refresh();
        $this->assertNotNull($this->picklistItem->start_time);
        $this->assertTrue($this->picklistItem->start_time->between($before, $after));
    }

    public function test_preserves_existing_start_time_on_subsequent_calls_unless_overwrite_is_true(): void
    {
        $originalTime = Carbon::parse('2026-10-21 08:00:00');
        $this->picklistItem->start_time = $originalTime;
        $this->picklistItem->save();

        // 1. Subsequent call without overwrite and without explicit start_time (app minimized and resumed)
        $res1 = $this->actingAs($this->user)->postJson(
            "/api/distributor-channel/v1/logistic/picklists/{$this->picklist->id}/items/{$this->picklistItem->id}/start-time",
            []
        );

        $res1->assertStatus(200)
            ->assertJsonPath('data.start_time', '2026-10-21 08:00:00');

        $this->picklistItem->refresh();
        $this->assertEquals('2026-10-21 08:00:00', $this->picklistItem->start_time->toDateTimeString());

        // 2. Call with overwrite: true
        $newTimeStr = '2026-10-21 09:15:00';
        $res2 = $this->actingAs($this->user)->postJson(
            "/api/distributor-channel/v1/logistic/picklists/{$this->picklist->id}/items/{$this->picklistItem->id}/start-time",
            [
                'start_time' => $newTimeStr,
                'overwrite'  => true,
            ]
        );

        $res2->assertStatus(200)
            ->assertJsonPath('data.start_time', $newTimeStr);

        $this->picklistItem->refresh();
        $this->assertEquals($newTimeStr, $this->picklistItem->start_time->toDateTimeString());
    }

    public function test_can_call_via_alias_routes(): void
    {
        // 1. Alias route /{id}/items/{itemId}/start
        $res1 = $this->actingAs($this->user)->postJson(
            "/api/distributor-channel/v1/logistic/picklists/{$this->picklist->id}/items/{$this->picklistItem->id}/start",
            ['checker_name' => 'Checker Alias 1']
        );
        $res1->assertStatus(200)->assertJsonPath('success', true);

        // 2. Direct route /items/{itemId}/start-time (without picklist id in URL)
        $res2 = $this->actingAs($this->user)->postJson(
            "/api/distributor-channel/v1/logistic/picklists/items/{$this->picklistItem->id}/start-time",
            ['checker_name' => 'Checker Alias 2']
        );
        $res2->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_cannot_start_item_for_cancelled_or_completed_picklist(): void
    {
        $this->picklist->status = Picklist::STATUS_CANCELED;
        $this->picklist->save();

        $res = $this->actingAs($this->user)->postJson(
            "/api/distributor-channel/v1/logistic/picklists/{$this->picklist->id}/items/{$this->picklistItem->id}/start-time",
            []
        );

        $res->assertStatus(400)
            ->assertJsonPath('success', false);
    }
}
