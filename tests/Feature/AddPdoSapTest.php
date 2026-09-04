<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddPdoSapTest extends TestCase
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

    public function test_add_pdo_sap_endpoint(): void
    {
        Http::fake([
            '*/api/addpdo' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Success create PDO',
                'DocEntry' => 12345,
                'DocNum' => 67890,
            ], 200),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $payload = [
            'ItemCode' => 'FG_SPECIAL_01',
            'Series' => 15,
            'PlannedQty' => 100.0,
            'PostingDate' => '2026-07-31T00:00:00',
            'DueDate' => '2026-08-10T00:00:00',
            'WhsCode' => '01',
            'Remarks' => 'Pembuatan barang spesial via API',
            'Shift' => 'Shift 1',
            'Unit' => 'Unit1',
            'Bomid' => '1312',
            'UserId' => '12',
            'AddonId' => '2',
            'Lines' => [
                [
                    'ItemType' => 'I',
                    'ItemCode' => 'RM001',
                    'BaseQty' => 2.5,
                    'WhsCode' => '01',
                    'IssueMethod' => 'M',
                    'OcrCode' => 'CC01',
                    'OcrCode2' => 'DP01',
                    'OcrCode3' => '',
                ],
                [
                    'ItemType' => 'R',
                    'ItemCode' => 'RES_MESIN_01',
                    'BaseQty' => 0.5,
                    'WhsCode' => '01',
                    'IssueMethod' => 'B',
                    'OcrCode' => 'CC01',
                    'OcrCode2' => 'DP01',
                    'OcrCode3' => '',
                ],
                [
                    'ItemType' => 'T',
                    'ItemCode' => 'Pastikan suhu mesin stabil',
                    'BaseQty' => 0,
                    'WhsCode' => '',
                    'IssueMethod' => 'M',
                    'OcrCode' => '',
                    'OcrCode2' => '',
                    'OcrCode3' => '',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/orders/sap', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.sap_response.ErrorCode', 0)
            ->assertJsonPath('data.payload.ItemCode', 'FG_SPECIAL_01')
            ->assertJsonPath('data.payload.Lines.0.ItemCode', 'RM001');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/addpdo') &&
                $request['ItemCode'] === 'FG_SPECIAL_01' &&
                $request['Shift'] === 'X' &&
                count($request['Lines']) === 3;
        });
    }

    public function test_add_pdo_sap_with_docnum_docentry_message_format(): void
    {
        Http::fake([
            '*/api/addpdo' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Success - [AddPDO] DocNum - DocEntry : 260910011 - 6715',
                'Result' => null,
            ], 200),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $payload = [
            'ItemCode' => 'FG_SPECIAL_02',
            'Series' => 15,
            'PlannedQty' => 50.0,
            'PostingDate' => '2026-07-31T00:00:00',
            'DueDate' => '2026-08-10T00:00:00',
            'WhsCode' => '01',
            'Remarks' => 'Test docnum docentry parsing',
            'Shift' => 'Shift 1',
            'Unit' => 'Unit1',
            'Lines' => [
                [
                    'ItemType' => 'I',
                    'ItemCode' => 'RM001',
                    'BaseQty' => 1.0,
                    'WhsCode' => '01',
                    'IssueMethod' => 'M',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/orders/sap', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.order.doc_num', '260910011')
            ->assertJsonPath('data.order.doc_entry', 6715)
            ->assertJsonPath('data.order.prod_order_no', '260910011');
    }
}
