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
                    'FreeTxt' => '',
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
                    'FreeTxt' => '',
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
                $request['ReqDate'] === '2025-10-22' &&
                $request['AddonId'] === '2' &&
                $request['Lines'][0]['ItemCode'] === 'JS000009' &&
                $request['Lines'][0]['Quantity'] == 2.00 &&
                $request['Lines'][0]['UomEntry'] === '-1' &&
                $request['Lines'][0]['UomCode'] === '-1' &&
                $request['Lines'][0]['WhsCode'] === '01' &&
                $request['Lines'][0]['UnitMsr'] === 'Pcs' &&
                $request['Lines'][0]['FreeTxt'] === '' &&
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
        $pr = \App\Models\PurchaseRequest::first();
        $this->assertNotNull($pr->req_date);
        $this->assertEquals('2025-10-22', $pr->req_date->format('Y-m-d'));

        $this->assertDatabaseHas('purchase_request_details', [
            'item_code' => 'JS000009',
            'quantity' => 2.00,
            'uom_entry' => '-1',
            'uom_code' => '-1',
            'whs_code' => '01',
            'unit_msr' => 'Pcs',
            'free_txt' => '',
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
                    'FreeTxt' => '',
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

    public function test_get_departments_returns_sap_department_list()
    {
        Http::fake([
            '*/api/GetDepartment' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    ['Code' => '-2', 'Remarks' => 'General Department', 'Name' => 'General'],
                    ['Code' => '1', 'Remarks' => 'Production', 'Name' => 'PRO'],
                    ['Code' => '2', 'Remarks' => 'Quality Control', 'Name' => 'QC'],
                ],
            ], 200)
        ]);

        $response = $this->getJson('/api/GetDepartment');

        $response->assertStatus(200);
        $response->assertJson([
            'ErrorCode' => 0,
            'Message' => '',
            'Result' => [
                ['Code' => '-2', 'Remarks' => 'General Department', 'Name' => 'General'],
                ['Code' => '1', 'Remarks' => 'Production', 'Name' => 'PRO'],
                ['Code' => '2', 'Remarks' => 'Quality Control', 'Name' => 'QC'],
            ]
        ]);
    }

    public function test_get_departments_post_method_supported()
    {
        Http::fake([
            '*/api/GetDepartment' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    ['Code' => '-2', 'Remarks' => 'General Department', 'Name' => 'General'],
                    ['Code' => '1', 'Remarks' => 'Production', 'Name' => 'PRO'],
                ],
            ], 200)
        ]);

        $response = $this->postJson('/api/GetDepartment');

        $response->assertStatus(200);
        $response->assertJson([
            'ErrorCode' => 0,
            'Message' => '',
            'Result' => [
                ['Code' => '-2', 'Remarks' => 'General Department', 'Name' => 'General'],
                ['Code' => '1', 'Remarks' => 'Production', 'Name' => 'PRO'],
            ]
        ]);
    }

    public function test_get_departments_filters_by_search_term()
    {
        Http::fake([
            '*/api/GetDepartment' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    ['Code' => '-2', 'Remarks' => 'General Department', 'Name' => 'General'],
                    ['Code' => '1', 'Remarks' => 'Production', 'Name' => 'PRO'],
                    ['Code' => '2', 'Remarks' => 'Quality Control', 'Name' => 'QC'],
                ],
            ], 200)
        ]);

        $response = $this->getJson('/api/GetDepartment?search=PRO');

        $response->assertStatus(200);
        $response->assertJson([
            'ErrorCode' => 0,
            'Message' => '',
            'Result' => [
                ['Code' => '1', 'Remarks' => 'Production', 'Name' => 'PRO'],
            ]
        ]);
        $response->assertJsonMissing(['Name' => 'General']);
    }

    public function test_get_departments_graceful_fallback_when_sap_fails()
    {
        Http::fake([
            '*/api/GetDepartment' => Http::response(['ErrorCode' => -1, 'Message' => 'Error'], 500)
        ]);

        $response = $this->getJson('/api/GetDepartment');

        $response->assertStatus(200);
        $response->assertJson([
            'ErrorCode' => 0,
            'Message' => '',
        ]);
        $this->assertNotEmpty($response->json('Result'));
    }

    public function test_get_departments_v1_endpoints_return_standard_response()
    {
        Http::fake([
            '*/api/GetDepartment' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    ['Code' => '-2', 'Remarks' => 'General Department', 'Name' => 'General'],
                    ['Code' => '1', 'Remarks' => 'Production', 'Name' => 'PRO'],
                ],
            ], 200)
        ]);

        // 1. v1 purchasing-request
        $response1 = $this->actingAs($this->user)->getJson('/api/distributor-channel/v1/purchasing-request/departments');
        $response1->assertStatus(200);
        $response1->assertJson([
            'success' => true,
            'message' => 'Daftar department berhasil diambil.',
            'data' => [
                ['Code' => '-2', 'Remarks' => 'General Department', 'Name' => 'General'],
                ['Code' => '1', 'Remarks' => 'Production', 'Name' => 'PRO'],
            ]
        ]);

        // 2. v1 general departments
        $response2 = $this->actingAs($this->user)->getJson('/api/distributor-channel/v1/departments');
        $response2->assertStatus(200);
        $response2->assertJson([
            'success' => true,
            'message' => 'Daftar department berhasil diambil.',
            'data' => [
                ['Code' => '-2', 'Remarks' => 'General Department', 'Name' => 'General'],
                ['Code' => '1', 'Remarks' => 'Production', 'Name' => 'PRO'],
            ]
        ]);
    }

    public function test_create_purchasing_request_with_explicit_req_date_matches_expected_sap_payload()
    {
        Http::fake([
            '*/api/addpr' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Success',
                'Result' => 61230,
            ], 200)
        ]);

        $payload = [
            'Series' => '6123',
            'ReqType' => '12',
            'ReqDate' => '2026-10-19',
            'Requester' => 'API',
            'RequesterName' => 'Administrator PT Susanti Megah',
            'Department' => '4',
            'DocDate' => '2026-10-19',
            'DocDueDate' => '2026-10-19',
            'Comments' => 'tes',
            'UserId' => '1',
            'AddonId' => '2',
            'Lines' => [
                [
                    'ItemCode' => 'ISP000723',
                    'PQTReqDate' => '2026-10-19',
                    'Quantity' => 1,
                    'UomEntry' => '-1',
                    'WhsCode' => 'FG01',
                    'UnitMsr' => 'Pcs',
                    'FreeTxt' => 'coba PR',
                    'OcrCode' => 'SBY',
                    'OcrCode2' => 'GRM',
                    'OcrCode3' => 'MKT',
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
                $request['Series'] === '6123' &&
                $request['ReqType'] === '12' &&
                $request['ReqDate'] === '2026-10-19' &&
                $request['Requester'] === 'API' &&
                $request['RequesterName'] === 'Administrator PT Susanti Megah' &&
                $request['Department'] === '4' &&
                $request['DocDate'] === '2026-10-19' &&
                $request['DocDueDate'] === '2026-10-19' &&
                $request['Comments'] === 'tes' &&
                $request['UserId'] === '1' &&
                $request['AddonId'] === '2' &&
                $request['Lines'][0]['ItemCode'] === 'ISP000723' &&
                $request['Lines'][0]['PQTReqDate'] === '2026-10-19' &&
                $request['Lines'][0]['Quantity'] == 1 &&
                $request['Lines'][0]['UomEntry'] === '-1' &&
                $request['Lines'][0]['WhsCode'] === 'FG01' &&
                $request['Lines'][0]['UnitMsr'] === 'Pcs' &&
                $request['Lines'][0]['FreeTxt'] === 'coba PR' &&
                $request['Lines'][0]['OcrCode'] === 'SBY' &&
                $request['Lines'][0]['OcrCode2'] === 'GRM' &&
                $request['Lines'][0]['OcrCode3'] === 'MKT';
        });

        $this->assertDatabaseHas('purchase_requests', [
            'series' => '6123',
            'req_type' => '12',
            'requester' => 'API',
            'requester_name' => 'Administrator PT Susanti Megah',
            'department' => '4',
            'sap_doc_entry' => 61230,
        ]);
        $pr = \App\Models\PurchaseRequest::where('series', '6123')->first();
        $this->assertNotNull($pr->req_date);
        $this->assertEquals('2026-10-19', $pr->req_date->format('Y-m-d'));
    }

    public function test_free_txt_defaults_to_empty_string_when_omitted()
    {
        Http::fake([
            '*/api/addpr' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Success',
                'Result' => 77777,
            ], 200)
        ]);

        $payload = [
            'Series' => '6123',
            'ReqType' => '12',
            'Lines' => [
                [
                    'ItemCode' => 'ISP000723',
                    'Quantity' => 1,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/purchasing-request/requests', $payload);
        $response->assertStatus(201);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request['Lines'][0]['FreeTxt'] === '';
        });
    }

    public function test_get_draft_pr_returns_data_not_found_when_sap_returns_dummy_zero_record()
    {
        Http::fake([
            '*/api/GetDraftPR' => Http::response([
                'ErrorCode' => 0,
                'Message'   => '',
                'Result'    => [
                    [
                        'WddCode'           => '0',
                        'DocEntry'          => '0',
                        'DraftEntry'        => '0',
                        'DraftNum'          => '0',
                        'DocNum'            => '0',
                        'DocTotal'          => '0.000000',
                        'ObjType'           => '',
                        'DocDate'           => '',
                        'CurrStep'          => '0',
                        'Status'            => '',
                        'StatusCode'        => '',
                        'StatusDescription' => '',
                        'Remarks'           => '',
                    ]
                ],
            ], 200),
        ]);

        // Test standard REST route
        $response = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/purchasing-request/draft-pr', [
            'CostumQuery' => '59',
            'UserId'      => 'admin',
            'From'        => '20260901',
            'To'          => '20260930',
            'Status'      => '',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Data not found.')
            ->assertJsonPath('data', []);

        // Test direct GetDraftPR route (SAP format)
        $sapResponse = $this->actingAs($this->user)->postJson('/api/distributor-channel/GetDraftPR', [
            'CostumQuery' => '59',
            'UserId'      => 'admin',
            'From'        => '20260901',
            'To'          => '20260930',
            'Status'      => '',
        ]);

        $sapResponse->assertStatus(200)
            ->assertJsonPath('ErrorCode', 0)
            ->assertJsonPath('Message', 'Data not found')
            ->assertJsonPath('Result', []);
    }

    public function test_get_draft_pr_returns_valid_draft_items()
    {
        Http::fake([
            '*/api/GetDraftPR' => Http::response([
                'ErrorCode' => 0,
                'Message'   => '',
                'Result'    => [
                    [
                        'WddCode'           => '12',
                        'DocEntry'          => '101',
                        'DraftEntry'        => '201',
                        'DraftNum'          => '201',
                        'DocNum'            => '101',
                        'DocTotal'          => '500000.000000',
                        'ObjType'           => '1470000113',
                        'DocDate'           => '2026-09-15',
                        'CurrStep'          => '1',
                        'Status'            => 'W',
                        'StatusCode'        => 'W',
                        'StatusDescription' => 'Pending Approval',
                        'Remarks'           => 'Draft PR Urgent',
                    ]
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/purchasing-request/draft-pr', [
            'CostumQuery' => '59',
            'From'        => '20260901',
            'To'          => '20260930',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.DraftNum', '201')
            ->assertJsonPath('data.0.Remarks', 'Draft PR Urgent');
    }

    public function test_get_list_pr_generated_returns_data_not_found_when_empty_or_zeros()
    {
        Http::fake([
            '*/api/GetListPRGenerated' => Http::response([
                'ErrorCode' => 0,
                'Message'   => '',
                'Result'    => [
                    [
                        'DocEntry' => '0',
                        'DocNum'   => '0',
                        'DocDate'  => '',
                    ]
                ],
            ], 200),
        ]);

        // REST Route
        $response = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/purchasing-request/generated', [
            'CostumQuery' => 'IT',
            'From'        => '20250901',
            'To'          => '20260930',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Data not found.')
            ->assertJsonPath('data', []);

        // Direct SAP Route
        $sapResponse = $this->actingAs($this->user)->postJson('/api/distributor-channel/GetListPRGenerated', [
            'CostumQuery' => 'IT',
            'From'        => '20250901',
            'To'          => '20260930',
        ]);

        $sapResponse->assertStatus(200)
            ->assertJsonPath('ErrorCode', 0)
            ->assertJsonPath('Message', 'Data not found')
            ->assertJsonPath('Result', []);
    }

    public function test_get_list_pr_generated_returns_valid_records()
    {
        Http::fake([
            '*/api/GetListPRGenerated' => Http::response([
                'ErrorCode' => 0,
                'Message'   => '',
                'Result'    => [
                    [
                        'DocEntry'      => '5339',
                        'DocNum'        => '260840020',
                        'DocDate'       => '20260810',
                        'DocDueDate'    => '20260817',
                        'ReqDate'       => '20260817',
                        'RequesterName' => 'Produksi, Maintenance & Logistik',
                        'Department'    => '1',
                        'DocStatus'     => 'Closed',
                        'Comments'      => 'UNTUK CAT POMPA ALL UNIT 2 DAN ALAT BERAT',
                    ],
                    [
                        'DocEntry'      => '5338',
                        'DocNum'        => '260840019',
                        'DocDate'       => '20260810',
                        'DocDueDate'    => '20260817',
                        'ReqDate'       => '20260817',
                        'RequesterName' => 'Produksi, Maintenance & Logistik',
                        'Department'    => '1',
                        'DocStatus'     => 'Open',
                        'Comments'      => 'UNTUK PERAWATAN PRODUKSI UNIT 1 DAN UNIT 2 GRESIK',
                    ],
                ],
            ], 200),
        ]);

        // Test POST REST endpoint
        $response = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/purchasing-request/generated', [
            'CostumQuery' => 'IT',
            'From'        => '20250901',
            'To'          => '20260930',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.DocNum', '260840020')
            ->assertJsonPath('data.1.DocNum', '260840019');

        // Test direct SAP endpoint
        $sapResponse = $this->actingAs($this->user)->postJson('/api/distributor-channel/GetListPRGenerated', [
            'CostumQuery' => 'IT',
            'From'        => '20250901',
            'To'          => '20260930',
        ]);

        $sapResponse->assertStatus(200)
            ->assertJsonPath('ErrorCode', 0)
            ->assertJsonCount(2, 'Result')
            ->assertJsonPath('Result.0.DocEntry', '5339');
    }

    public function test_close_pr_returns_sap_failed_format_when_not_found()
    {
        Http::fake([
            '*/api/ClosePR' => Http::response([
                'ErrorCode' => 0,
                'Message'   => 'Failed - [ClosePurchaseRequest] Data tidak ditemukan',
                'Result'    => null,
            ], 200),
        ]);

        // Direct SAP format endpoint
        $response = $this->postJson('/api/ClosePR', [
            'DocEntry' => '999999',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ErrorCode' => 0,
                'Message'   => 'Failed - [ClosePurchaseRequest] Data tidak ditemukan',
                'Result'    => null,
            ]);

        // REST endpoint format
        $restResponse = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/purchasing-request/close-pr', [
            'DocEntry' => '999999',
        ]);

        $restResponse->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Failed - [ClosePurchaseRequest] Data tidak ditemukan');
    }

    public function test_close_pr_success_and_updates_local_status()
    {
        Http::fake([
            '*/api/ClosePR' => Http::response([
                'ErrorCode' => 0,
                'Message'   => 'Success - [ClosePurchaseRequest] PR Closed',
                'Result'    => 'Success',
            ], 200),
        ]);

        // Create local PR
        $pr = \App\Models\PurchaseRequest::create([
            'pr_number'     => 'PR-TEST-CLOSE-01',
            'department'    => '1',
            'cost_center'   => 'CC01',
            'doc_date'      => '2026-10-23',
            'doc_due_date'  => '2026-10-30',
            'status'        => 'APPROVED',
            'sap_doc_entry' => 5339,
        ]);

        // Call direct endpoint
        $response = $this->postJson('/api/ClosePR', [
            'DocEntry' => '5339',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('ErrorCode', 0)
            ->assertJsonPath('Message', 'Success - [ClosePurchaseRequest] PR Closed');

        $this->assertEquals('COMPLETED', $pr->fresh()->status);
    }

    public function test_close_pr_validation_error_when_doc_entry_missing()
    {
        $response = $this->postJson('/api/ClosePR', []);

        $response->assertStatus(400)
            ->assertJsonPath('ErrorCode', 1)
            ->assertJsonPath('Message', 'Failed - [ClosePurchaseRequest] Parameter DocEntry is required')
            ->assertJsonPath('Result', null);
    }

    public function test_cancel_pr_returns_sap_failed_format_when_not_found()
    {
        Http::fake([
            '*/api/CancelPR' => Http::response([
                'ErrorCode' => 0,
                'Message'   => 'Failed - [CancelPurchaseRequest] Data tidak ditemukan',
                'Result'    => null,
            ], 200),
        ]);

        // Direct SAP format endpoint
        $response = $this->postJson('/api/CancelPR', [
            'DocEntry' => '999999',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ErrorCode' => 0,
                'Message'   => 'Failed - [CancelPurchaseRequest] Data tidak ditemukan',
                'Result'    => null,
            ]);

        // REST endpoint format
        $restResponse = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/purchasing-request/cancel-pr', [
            'DocEntry' => '999999',
        ]);

        $restResponse->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Failed - [CancelPurchaseRequest] Data tidak ditemukan');
    }

    public function test_cancel_pr_success_and_updates_local_status()
    {
        Http::fake([
            '*/api/CancelPR' => Http::response([
                'ErrorCode' => 0,
                'Message'   => 'Success - [CancelPurchaseRequest] PR Cancelled',
                'Result'    => 'Success',
            ], 200),
        ]);

        // Create local PR
        $pr = \App\Models\PurchaseRequest::create([
            'pr_number'     => 'PR-TEST-CANCEL-01',
            'department'    => '1',
            'cost_center'   => 'CC01',
            'doc_date'      => '2026-10-23',
            'doc_due_date'  => '2026-10-30',
            'status'        => 'DRAFT',
            'sap_doc_entry' => 5338,
        ]);

        // Call direct endpoint
        $response = $this->postJson('/api/CancelPR', [
            'DocEntry' => '5338',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('ErrorCode', 0)
            ->assertJsonPath('Message', 'Success - [CancelPurchaseRequest] PR Cancelled');

        $this->assertEquals('CANCELLED', $pr->fresh()->status);
    }

    public function test_cancel_pr_validation_error_when_doc_entry_missing()
    {
        $response = $this->postJson('/api/CancelPR', []);

        $response->assertStatus(400)
            ->assertJsonPath('ErrorCode', 1)
            ->assertJsonPath('Message', 'Failed - [CancelPurchaseRequest] Parameter DocEntry is required')
            ->assertJsonPath('Result', null);
    }

    public function test_get_detail_pr_direct_endpoint_success()
    {
        $sampleSapResponse = [
            'ErrorCode' => 0,
            'Message'   => '',
            'Result'    => [
                'Table1' => [
                    [
                        'DocEntry'       => '5339',
                        'DocNum'         => '260840020',
                        'Series'         => '6493',
                        'SeriesName'     => 'GSK26-08',
                        'DocDate'        => '20260810',
                        'DocDueDate'     => '20260817',
                        'ReqDate'        => '20260817',
                        'Department'     => '1',
                        'DepartmentName' => 'PRO',
                        'RequesterName'  => 'Produksi, Maintenance & Logistik',
                        'Requester'      => 'LOG34',
                        'ReqType'        => '12',
                        'Comments'       => 'UNTUK CAT POMPA ALL UNIT 2 DAN ALAT BERAT',
                        'U_AddonId'      => '',
                        'U_UserId'       => '0',
                        'DocStatus'      => 'Closed',
                    ]
                ],
                'Table2' => [
                    [
                        'DocEntry'   => '5339',
                        'LineNum'    => '0',
                        'ItemCode'   => 'ISP000805',
                        'ItemName'   => 'THINNER',
                        'Quantity'   => '20.000000',
                        'WhsCode'    => 'SPR04',
                        'WhsName'    => 'GUDANG SPAREPART - GRESIK',
                        'PQTReqDate' => '20260817',
                        'UomEntry'   => '-1',
                        'UnitMsr'    => 'Liter',
                        'FreeTxt'    => '',
                        'OcrCode'    => 'GSK',
                        'OcrCode2'   => 'GRM',
                        'OcrCode3'   => 'PRO',
                        'LineStatus' => 'Closed',
                    ]
                ]
            ]
        ];

        Http::fake([
            '*/api/GetDetailPR' => Http::response($sampleSapResponse, 200),
        ]);

        // Direct POST /api/GetDetailPR
        $postResponse = $this->postJson('/api/GetDetailPR', [
            'CustomQuery' => '5339',
        ]);

        $postResponse->assertStatus(200)
            ->assertJsonPath('ErrorCode', 0)
            ->assertJsonPath('Result.Table1.0.DocEntry', '5339')
            ->assertJsonPath('Result.Table1.0.DocNum', '260840020')
            ->assertJsonPath('Result.Table2.0.ItemCode', 'ISP000805')
            ->assertJsonPath('Result.Table2.0.UnitMsr', 'Liter');

        // Direct GET /api/GetDetailPR?CustomQuery=5339
        $getResponse = $this->getJson('/api/GetDetailPR?CustomQuery=5339');

        $getResponse->assertStatus(200)
            ->assertJsonPath('ErrorCode', 0)
            ->assertJsonPath('Result.Table1.0.DocEntry', '5339');
    }

    public function test_get_detail_pr_rest_endpoint_success()
    {
        $sampleSapResponse = [
            'ErrorCode' => 0,
            'Message'   => '',
            'Result'    => [
                'Table1' => [
                    [
                        'DocEntry'       => '5339',
                        'DocNum'         => '260840020',
                        'Department'     => '1',
                        'DepartmentName' => 'PRO',
                    ]
                ],
                'Table2' => [
                    [
                        'DocEntry' => '5339',
                        'LineNum'  => '0',
                        'ItemCode' => 'ISP000805',
                        'ItemName' => 'THINNER',
                        'UnitMsr'  => 'Liter',
                    ]
                ]
            ]
        ];

        Http::fake([
            '*/api/GetDetailPR' => Http::response($sampleSapResponse, 200),
        ]);

        // REST endpoint GET
        $restResponse = $this->actingAs($this->user)->getJson('/api/distributor-channel/v1/purchasing-request/detail-pr?CustomQuery=5339');

        $restResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.Table1.0.DocEntry', '5339')
            ->assertJsonPath('data.items.0.ItemCode', 'ISP000805');

        // REST endpoint with path param /detail/{docEntry}
        $pathResponse = $this->actingAs($this->user)->getJson('/api/distributor-channel/v1/purchasing-request/detail/5339');

        $pathResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.Table1.0.DocEntry', '5339');
    }

    public function test_get_detail_pr_validation_error_when_doc_entry_missing()
    {
        // Direct endpoint
        $directResponse = $this->postJson('/api/GetDetailPR', []);
        $directResponse->assertStatus(400)
            ->assertJsonPath('ErrorCode', 1)
            ->assertJsonPath('Result', null);

        // REST endpoint
        $restResponse = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/purchasing-request/detail-pr', []);
        $restResponse->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_get_detail_pr_returns_data_not_found_when_sap_returns_dummy()
    {
        $dummySapResponse = [
            'ErrorCode' => 0,
            'Message'   => '',
            'Result'    => [
                'Table1' => [
                    [
                        'DocEntry' => '0',
                        'DocNum'   => '0',
                    ]
                ],
                'Table2' => [
                    [
                        'DocEntry' => '0',
                        'ItemCode' => '',
                    ]
                ]
            ]
        ];

        Http::fake([
            '*/api/GetDetailPR' => Http::response($dummySapResponse, 200),
        ]);

        // Direct SAP format
        $directResponse = $this->postJson('/api/GetDetailPR', [
            'CustomQuery' => '999999',
        ]);

        $directResponse->assertStatus(200)
            ->assertJsonPath('ErrorCode', 0)
            ->assertJsonPath('Message', 'Data not found')
            ->assertJsonPath('Result.Table1', [])
            ->assertJsonPath('Result.Table2', []);

        // REST format
        $restResponse = $this->actingAs($this->user)->getJson('/api/distributor-channel/v1/purchasing-request/detail-pr?CustomQuery=999999');

        $restResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Data not found.')
            ->assertJsonPath('data', null);
    }

    public function test_get_detail_pr_handles_sap_error()
    {
        Http::fake([
            '*/api/GetDetailPR' => Http::response([
                'ErrorCode' => 1,
                'Message'   => 'SAP internal error',
                'Result'    => null,
            ], 200),
        ]);

        // Direct SAP format
        $directResponse = $this->postJson('/api/GetDetailPR', [
            'CustomQuery' => '5339',
        ]);

        $directResponse->assertStatus(200)
            ->assertJsonPath('ErrorCode', 1)
            ->assertJsonPath('Message', 'SAP internal error')
            ->assertJsonPath('Result', null);

        // REST format
        $restResponse = $this->actingAs($this->user)->postJson('/api/distributor-channel/v1/purchasing-request/detail-pr', [
            'CustomQuery' => '5339',
        ]);

        $restResponse->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'SAP internal error');
    }
}


