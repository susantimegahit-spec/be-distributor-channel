<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PurchaseRequestSapIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_create_purchasing_request_fails_and_does_not_save_when_sap_returns_error()
    {
        Http::fake([
            '*/api/addpr' => Http::response([
                'ErrorCode' => -2028,
                'Message' => 'Failed - [AddPurchaseRequest] No matching records found (ODBC -2028)',
                'Result' => null,
            ], 200)
        ]);

        $payload = [
            'Series' => '4876',
            'ReqType' => '12',
            'Requester' => 'IND01',
            'RequesterName' => 'Purchasing Balaraja',
            'Department' => '9',
            'DocDate' => '2025-10-22',
            'DocDueDate' => '2025-10-22',
            'Comments' => '',
            'UserId' => '19',
            'AddonId' => '2',
            'Lines' => [
                [
                    'ItemCode' => 'JS000009',
                    'PQTReqDate' => '2025-10-22',
                    'Quantity' => 2.00,
                    'UomEntry' => '-1',
                    'UomCode' => '-1',
                    'WhsCode' => '01',
                    'UnitMsr' => 'Pcs',
                    'FreeTxt' => 'untuk upgrade',
                    'OcrCode' => 'BLR',
                    'OcrCode2' => 'GRM',
                    'OcrCode3' => 'PCG',
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/purchasing-request/requests', $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Failed - [AddPurchaseRequest] No matching records found (ODBC -2028)',
            'errors' => [
                'ErrorCode' => -2028,
                'Message' => 'Failed - [AddPurchaseRequest] No matching records found (ODBC -2028)',
                'Result' => null,
            ]
        ]);

        $this->assertDatabaseCount('purchase_requests', 0);
        $this->assertDatabaseCount('purchase_request_details', 0);
    }

    public function test_create_purchasing_request_succeeds_and_saves_when_sap_returns_success()
    {
        Http::fake([
            '*/api/addpr' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Success',
                'Result' => 54321,
            ], 200)
        ]);

        $payload = [
            'Series' => '4876',
            'ReqType' => '12',
            'Requester' => 'IND01',
            'RequesterName' => 'Purchasing Balaraja',
            'Department' => '9',
            'DocDate' => '2025-10-22',
            'DocDueDate' => '2025-10-22',
            'Comments' => 'Test PR',
            'UserId' => '19',
            'AddonId' => '2',
            'Lines' => [
                [
                    'ItemCode' => 'JS000009',
                    'PQTReqDate' => '2025-10-22',
                    'Quantity' => 2.00,
                    'UomEntry' => '-1',
                    'UomCode' => '-1',
                    'WhsCode' => '01',
                    'UnitMsr' => 'Pcs',
                    'FreeTxt' => 'untuk upgrade',
                    'OcrCode' => 'BLR',
                    'OcrCode2' => 'GRM',
                    'OcrCode3' => 'PCG',
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/purchasing-request/requests', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Purchasing Request berhasil dibuat.',
        ]);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_ends_with($request->url(), '/api/addpr') &&
                $request['Series'] === '4876' &&
                $request['ReqType'] === '12' &&
                $request['AddonId'] === '2' &&
                $request['Lines'][0]['ItemCode'] === 'JS000009' &&
                $request['Lines'][0]['Quantity'] == 2.00 &&
                $request['Lines'][0]['UomEntry'] === '-1' &&
                $request['Lines'][0]['UomCode'] === '-1' &&
                $request['Lines'][0]['WhsCode'] === '01' &&
                $request['Lines'][0]['UnitMsr'] === 'Pcs' &&
                $request['Lines'][0]['FreeTxt'] === 'untuk upgrade' &&
                $request['Lines'][0]['OcrCode'] === 'BLR' &&
                $request['Lines'][0]['OcrCode2'] === 'GRM' &&
                $request['Lines'][0]['OcrCode3'] === 'PCG';
        });

        $this->assertDatabaseCount('purchase_requests', 1);
        $this->assertDatabaseHas('purchase_requests', [
            'series' => '4876',
            'req_type' => '12',
            'requester' => 'IND01',
            'requester_name' => 'Purchasing Balaraja',
            'department' => '9',
            'sap_doc_entry' => 54321,
        ]);

        $this->assertDatabaseHas('purchase_request_details', [
            'item_code' => 'JS000009',
            'quantity' => 2.00,
            'uom_entry' => '-1',
            'uom_code' => '-1',
            'whs_code' => '01',
            'unit_msr' => 'Pcs',
            'free_txt' => 'untuk upgrade',
            'ocr_code' => 'BLR',
            'ocr_code2' => 'GRM',
            'ocr_code3' => 'PCG',
        ]);
    }

    public function test_get_series_returns_sap_series_list()
    {
        Http::fake([
            '*/api/getSeries' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    ['Series' => '4182', 'SeriesName' => 'BJM25-10'],
                    ['Series' => '4876', 'SeriesName' => 'BLR25-10'],
                ],
            ], 200)
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/distributor-channel/v1/purchasing-request/series?CustomQuery=20251022');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Daftar series Purchasing Request berhasil diambil.',
            'data' => [
                ['Series' => '4182', 'SeriesName' => 'BJM25-10'],
                ['Series' => '4876', 'SeriesName' => 'BLR25-10'],
            ]
        ]);
    }

    public function test_get_items_returns_items_with_default_uom()
    {
        Http::fake([
            '*/api/ListItem' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'ItemCode' => 'JS000009',
                        'ItemName' => 'JOP 250 M B @ 10,5 KG/BAL',
                        'SUoMEntry' => '4',
                        'SalUnitMsr' => 'Bal',
                        'Perkg' => '10.500000',
                    ]
                ],
            ], 200)
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/distributor-channel/v1/purchasing-request/items?search=JS000009');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Daftar item untuk Purchasing Request berhasil diambil.',
            'data' => [
                [
                    'item_code' => 'JS000009',
                    'uom_entry' => '-1',
                    'uom_code' => '-1',
                    'uom' => 'Bal',
                ]
            ]
        ]);
    }

    public function test_create_purchasing_request_via_add_pr_alias_succeeds()
    {
        Http::fake([
            '*/api/addpr' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Success',
                'Result' => 54322,
            ], 200)
        ]);

        $payload = [
            'Series' => '4876',
            'ReqType' => '12',
            'Requester' => 'IND01',
            'RequesterName' => 'Purchasing Balaraja',
            'Department' => '9',
            'DocDate' => '2025-10-22',
            'DocDueDate' => '2025-10-22',
            'Comments' => '',
            'UserId' => '19',
            'AddOnId' => '2',
            'Lines' => [
                [
                    'ItemCode' => 'JS000009',
                    'PQTReqDate' => '2025-10-22',
                    'Quantity' => 2.00,
                    'UomEntry' => '-1',
                    'UomCode' => '-1',
                    'WhsCode' => '01',
                    'UnitMsr' => 'Pcs',
                    'FreeTxt' => 'untuk upgrade',
                    'OcrCode' => 'BLR',
                    'OcrCode2' => 'GRM',
                    'OcrCode3' => 'PCG',
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/purchasing-request/add-pr', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Purchasing Request berhasil dibuat.',
        ]);
    }
}
