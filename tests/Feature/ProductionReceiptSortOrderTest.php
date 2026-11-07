<?php

namespace Tests\Feature;

use App\Models\ProductionOrder;
use App\Models\ProductionReceipt;
use App\Models\ProductionReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductionReceiptSortOrderTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrCreate(
            ['username' => 'test_receipt_user'],
            [
                'name'     => 'Test Receipt User',
                'email'    => 'test_receipt@example.com',
                'password' => bcrypt('password123'),
            ]
        );
    }

    public function test_add_receipt_prod_sap_respects_custom_sort_order_and_numbering(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $order1 = ProductionOrder::create([
            'prod_order_no' => 'PDO-SORT-' . uniqid(),
            'doc_entry'     => 101,
            'doc_num'       => '101',
            'item_code'     => 'FG001',
            'warehouse'     => 'PRD01-01',
            'status'        => 'RELEASED',
            'planned_qty'   => 500,
            'cmplt_qty'     => 0,
            'type'          => 'S',
            'post_date'     => now()->toDateString(),
            'due_date'      => now()->addDays(2)->toDateString(),
        ]);

        $order2 = ProductionOrder::create([
            'prod_order_no' => 'PDO-SORT-' . uniqid(),
            'doc_entry'     => 102,
            'doc_num'       => '102',
            'item_code'     => 'FG002',
            'warehouse'     => 'PRD01-01',
            'status'        => 'RELEASED',
            'planned_qty'   => 300,
            'cmplt_qty'     => 0,
            'type'          => 'S',
            'post_date'     => now()->toDateString(),
            'due_date'      => now()->addDays(2)->toDateString(),
        ]);

        $order3 = ProductionOrder::create([
            'prod_order_no' => 'PDO-SORT-' . uniqid(),
            'doc_entry'     => 103,
            'doc_num'       => '103',
            'item_code'     => 'FG003',
            'warehouse'     => 'PRD01-01',
            'status'        => 'RELEASED',
            'planned_qty'   => 200,
            'cmplt_qty'     => 0,
            'type'          => 'S',
            'post_date'     => now()->toDateString(),
            'due_date'      => now()->addDays(2)->toDateString(),
        ]);

        $capturedSapPayload = null;
        Http::fake([
            '*/api/addreceiptprod' => function ($request) use (&$capturedSapPayload) {
                $capturedSapPayload = $request->data();
                return Http::response([
                    'ErrorCode' => 0,
                    'Message'   => 'Success - [AddReceiptProd] DocNum: 889900 (DocEntry: 889900)',
                    'DocNum'    => '889900',
                    'DocEntry'  => '889900',
                ], 200);
            },
        ]);

        // Simulasikan: User mencentang PDO ke-3 terlebih dahulu (sort_order = 1),
        // lalu PDO ke-1 (sort_order = 2), lalu PDO ke-2 (sort_order = 3).
        $payload = [
            'DocDate'    => '2026-11-07',
            'DocDueDate' => '2026-11-07',
            'Comments'   => 'Uji urutan PDO centang ke-3 duluan',
            'Shift'      => 'A',
            'Unit'       => 'SBY-UNIT1',
            'Lines'      => [
                [
                    'BaseEntry'  => 101, // PDO 1 tapi ingin di posisi 2
                    'Quantity'   => 50,
                    'sort_order' => 2,
                ],
                [
                    'BaseEntry'  => 102, // PDO 2 di posisi 3
                    'Quantity'   => 30,
                    'sort_order' => 3,
                ],
                [
                    'BaseEntry'  => 103, // PDO 3 dicentang pertama -> posisi 1
                    'Quantity'   => 20,
                    'sort_order' => 1,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/add-receipt-prod-sap', $payload);

        $response->assertStatus(200);

        // 1. Verifikasi payload ke SAP B1 sudah diurutkan sesuai sort_order (PDO 103 di urutan pertama)
        $this->assertNotNull($capturedSapPayload);
        $this->assertEquals(103, $capturedSapPayload['Lines'][0]['BaseEntry'], 'Baris pertama yang dikirim ke SAP harus PDO 103');
        $this->assertEquals(101, $capturedSapPayload['Lines'][1]['BaseEntry'], 'Baris kedua yang dikirim ke SAP harus PDO 101');
        $this->assertEquals(102, $capturedSapPayload['Lines'][2]['BaseEntry'], 'Baris ketiga yang dikirim ke SAP harus PDO 102');

        // Pastikan sort_order tidak bocor ke payload SAP
        $this->assertArrayNotHasKey('sort_order', $capturedSapPayload['Lines'][0]);

        // 2. Verifikasi data di database lokal
        $receipt = ProductionReceipt::where('doc_num', '889900')->first();
        $this->assertNotNull($receipt);

        $items = $receipt->items;
        $this->assertCount(3, $items);
        $this->assertEquals('103', (string)$items[0]->base_entry);
        $this->assertEquals(1, $items[0]->sort_order);

        $this->assertEquals('101', (string)$items[1]->base_entry);
        $this->assertEquals(2, $items[1]->sort_order);

        $this->assertEquals('102', (string)$items[2]->base_entry);
        $this->assertEquals(3, $items[2]->sort_order);
    }
}
