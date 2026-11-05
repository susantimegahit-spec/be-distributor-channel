<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FastAddPurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_fast_add_pr_succeeds_with_exact_payload_and_returns_canonical_sap_response()
    {
        Http::fake([
            '*/api/addpr' => function ($request) {
                $payload = $request->data();

                // Assert full payload mapping sent to SAP B1
                $this->assertSame(0, $payload['Series']);
                $this->assertSame('2026-11-05', $payload['DocDate']);
                $this->assertSame('2026-11-12', $payload['DocDueDate']);
                $this->assertSame('2026-11-05', $payload['TaxDate']);
                $this->assertSame('2026-11-10', $payload['ReqDate']);
                $this->assertSame(1, $payload['Department']);
                $this->assertSame(1, $payload['Branch']);
                $this->assertSame('1', $payload['Requester']);
                $this->assertSame('John Doe', $payload['RequesterName']);
                $this->assertSame('I', $payload['DocType']);
                $this->assertSame('Permintaan Pembelian Bahan Baku', $payload['Comments']);
                $this->assertEquals(250000, $payload['Total']);
                $this->assertEquals(250000, $payload['DocTotal']);
                $this->assertSame('PORTAL_PURCHASING', $payload['AddonId']);
                $this->assertSame('admin', $payload['UserId']);

                $this->assertCount(1, $payload['Lines']);
                $line = $payload['Lines'][0];
                $this->assertSame('RM-GARAM-01', $line['ItemCode']);
                $this->assertEquals(100, $line['Quantity']);
                $this->assertSame('WHS-RAW', $line['WhsCode']);
                $this->assertSame('2026-11-10', $line['PQTReqDate']);
                $this->assertEquals(2500, $line['PriceBefDisc']);
                $this->assertEquals(2500, $line['UnitPrice']);
                $this->assertEquals(250000, $line['Total']);
                $this->assertEquals(250000, $line['LineTotal']);
                $this->assertSame('Kg', $line['UnitMsr']);
                $this->assertSame('Krosok Kualitas A', $line['FreeTxt']);
                $this->assertSame('CAB01', $line['OcrCode']);
                $this->assertSame('BU01', $line['OcrCode2']);
                $this->assertSame('DEPT01', $line['OcrCode3']);
                $this->assertSame('PPN11', $line['TaxCode']);
                $this->assertSame(1, $line['UomEntry']);

                return Http::response([
                    'ErrorCode' => 0,
                    'Message'   => 'Success - [AddPurchaseRequest] DocNum: 1000105 (DocEntry: 105)',
                ], 200);
            },
        ]);

        $requestPayload = [
            'Series'        => 0,
            'DocDate'       => '2026-11-05',
            'DocDueDate'    => '2026-11-12',
            'TaxDate'       => '2026-11-05',
            'ReqDate'       => '2026-11-10',
            'Department'    => 1,
            'Branch'        => 1,
            'Requester'     => '1',
            'RequesterName' => 'John Doe',
            'DocType'       => 'I',
            'Comments'      => 'Permintaan Pembelian Bahan Baku',
            'Total'         => 250000,
            'DocTotal'      => 250000,
            'AddonId'       => 'PORTAL_PURCHASING',
            'UserId'        => 'admin',
            'Lines'         => [
                [
                    'ItemCode'     => 'RM-GARAM-01',
                    'Quantity'     => 100,
                    'WhsCode'      => 'WHS-RAW',
                    'PQTReqDate'   => '2026-11-10',
                    'PriceBefDisc' => 2500,
                    'UnitPrice'    => 2500,
                    'Total'        => 250000,
                    'LineTotal'    => 250000,
                    'UnitMsr'      => 'Kg',
                    'FreeTxt'      => 'Krosok Kualitas A',
                    'OcrCode'      => 'CAB01',
                    'OcrCode2'     => 'BU01',
                    'OcrCode3'     => 'DEPT01',
                    'TaxCode'      => 'PPN11',
                    'UomEntry'     => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/addpr', $requestPayload);

        $response->assertStatus(200)
            ->assertJson([
                'ErrorCode' => 0,
                'Message'   => 'Success - [AddPurchaseRequest] DocNum: 1000105 (DocEntry: 105)',
            ]);

        // Verify local DB record was preserved with parsed DocEntry and DocNum
        $this->assertDatabaseHas('purchase_requests', [
            'sap_doc_entry' => 105,
            'sap_doc_num'   => '1000105',
            'status'        => 'SUBMITTED',
            'total_amount'  => 250000,
        ]);

        $this->assertDatabaseHas('purchase_request_details', [
            'item_code' => 'RM-GARAM-01',
            'quantity'  => 100,
            'whs_code'  => 'WHS-RAW',
            'unit_msr'  => 'Kg',
        ]);
    }

    public function test_fast_add_pr_returns_error_when_lines_is_empty()
    {
        $response = $this->postJson('/api/addpr', [
            'Series'  => 0,
            'DocDate' => '2026-11-05',
            'Lines'   => [],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ErrorCode' => 1,
                'Message'   => 'Failed - [AddPurchaseRequest] Parameter Lines is required and must not be empty',
            ]);
    }

    public function test_fast_add_pr_handles_sap_error()
    {
        Http::fake([
            '*/api/addpr' => Http::response([
                'ErrorCode' => 1,
                'Message'   => 'Failed - [AddPurchaseRequest] ItemCode not found in SAP',
            ], 200),
        ]);

        $response = $this->postJson('/api/addpr', [
            'Lines' => [
                [
                    'ItemCode' => 'INVALID-ITEM',
                    'Quantity' => 10,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ErrorCode' => 1,
                'Message'   => 'Failed - [AddPurchaseRequest] ItemCode not found in SAP',
            ]);
    }

    public function test_fast_add_pr_accessible_via_distributor_channel_and_v1_prefixes()
    {
        Http::fake([
            '*/api/addpr' => Http::response([
                'ErrorCode' => 0,
                'Message'   => 'Success - [AddPurchaseRequest] DocNum: 1000106 (DocEntry: 106)',
            ], 200),
        ]);

        $payload = [
            'Lines' => [
                [
                    'ItemCode' => 'RM-GARAM-01',
                    'Quantity' => 50,
                ],
            ],
        ];

        // 1. /api/v1/purchasing-request/addpr
        $res1 = $this->postJson('/api/v1/purchasing-request/addpr', $payload);
        $res1->assertStatus(200)->assertJson(['ErrorCode' => 0]);

        // 2. /api/distributor-channel/v1/purchasing-request/addpr
        $res2 = $this->postJson('/api/distributor-channel/v1/purchasing-request/addpr', $payload);
        $res2->assertStatus(200)->assertJson(['ErrorCode' => 0]);

        // 3. /api/distributor-channel/addpr
        $res3 = $this->postJson('/api/distributor-channel/addpr', $payload);
        $res3->assertStatus(200)->assertJson(['ErrorCode' => 0]);
    }
}
