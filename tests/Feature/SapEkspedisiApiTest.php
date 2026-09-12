<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class SapEkspedisiApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_get_nama_ekspedisi_returns_data_from_sap()
    {
        Http::fake([
            '*/api/getNamaEkspedisi' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'Code' => '01',
                        'U_NamaEkspedisi' => 'Internal',
                        'U_VendorEkspedisi' => 'Internal',
                    ],
                    [
                        'Code' => '02',
                        'U_NamaEkspedisi' => 'Ambil Sendiri',
                        'U_VendorEkspedisi' => 'Ambil Sendiri',
                    ],
                ],
            ], 200),
        ]);

        // Test GET
        $responseGet = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/distributor-channel/v1/ekspedisi/nama-ekspedisi');

        $responseGet->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Expedition list retrieved successfully from SAP.',
                'data' => [
                    [
                        'Code' => '01',
                        'U_NamaEkspedisi' => 'Internal',
                    ],
                    [
                        'Code' => '02',
                        'U_NamaEkspedisi' => 'Ambil Sendiri',
                    ],
                ],
            ]);

        // Test POST
        $responsePost = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/distributor-channel/v1/ekspedisi/nama-ekspedisi');

        $responsePost->assertStatus(200);
    }

    public function test_get_nama_checker_returns_data_from_sap()
    {
        Http::fake([
            '*/api/getNamaChecker' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'Code' => '001',
                        'Name' => 'AZHAR',
                    ],
                    [
                        'Code' => '002',
                        'Name' => 'SUBANDRI',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/distributor-channel/v1/ekspedisi/nama-checker');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Checker list retrieved successfully from SAP.',
                'data' => [
                    ['Code' => '001', 'Name' => 'AZHAR'],
                    ['Code' => '002', 'Name' => 'SUBANDRI'],
                ],
            ]);
    }

    public function test_get_kendaraan_returns_data_from_sap()
    {
        Http::fake([
            '*/api/getKendaraan' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'Nopol' => 'L9870CE',
                        'U_Merk' => 'HINO DUTRO',
                        'U_KapasitasKg' => '6000.000000',
                        'U_KapasitasM3' => '13.600000',
                    ],
                    [
                        'Nopol' => 'L9872CE',
                        'U_Merk' => 'HINO DUTRO',
                        'U_KapasitasKg' => '6000.000000',
                        'U_KapasitasM3' => '13.600000',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/distributor-channel/v1/ekspedisi/kendaraan');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Vehicle list retrieved successfully from SAP.',
                'data' => [
                    [
                        'Nopol' => 'L9870CE',
                        'U_Merk' => 'HINO DUTRO',
                        'U_KapasitasKg' => '6000.000000',
                        'U_KapasitasM3' => '13.600000',
                    ],
                ],
            ]);
    }

    public function test_get_sopir_returns_data_from_sap()
    {
        Http::fake([
            '*/api/getSopir' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'Name' => 'TEGUH',
                        'U_Cabang' => 'SBY',
                    ],
                    [
                        'Name' => 'SUPOYO',
                        'U_Cabang' => 'SBY',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/distributor-channel/v1/ekspedisi/sopir');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Driver list retrieved successfully from SAP.',
                'data' => [
                    ['Name' => 'TEGUH', 'U_Cabang' => 'SBY'],
                    ['Name' => 'SUPOYO', 'U_Cabang' => 'SBY'],
                ],
            ]);
    }

    public function test_returns_data_not_found_when_sap_result_is_empty_or_dummy()
    {
        Http::fake([
            '*/api/getNamaEkspedisi' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    ['Code' => '', 'U_NamaEkspedisi' => '', 'U_VendorEkspedisi' => ''],
                    ['Code' => '0', 'U_NamaEkspedisi' => '0', 'U_VendorEkspedisi' => ''],
                ],
            ], 200),
            '*/api/getNamaChecker' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [],
            ], 200),
            '*/api/getKendaraan' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    ['Nopol' => '', 'U_Merk' => ''],
                    ['Nopol' => '0', 'U_Merk' => ''],
                ],
            ], 200),
            '*/api/getSopir' => Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    ['Name' => '', 'U_Cabang' => ''],
                    ['Name' => '0', 'U_Cabang' => ''],
                ],
            ], 200),
        ]);

        // 1. Ekspedisi
        $resEkspedisi = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/distributor-channel/v1/ekspedisi/nama-ekspedisi');
        $resEkspedisi->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data not found.',
                'data' => [],
            ]);

        // 2. Checker
        $resChecker = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/distributor-channel/v1/ekspedisi/nama-checker');
        $resChecker->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data not found.',
                'data' => [],
            ]);

        // 3. Kendaraan
        $resKendaraan = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/distributor-channel/v1/ekspedisi/kendaraan');
        $resKendaraan->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data not found.',
                'data' => [],
            ]);

        // 4. Sopir
        $resSopir = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/distributor-channel/v1/ekspedisi/sopir');
        $resSopir->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data not found.',
                'data' => [],
            ]);
    }

    public function test_returns_error_response_when_sap_fails()
    {
        Http::fake([
            '*/api/getNamaEkspedisi' => Http::response('Internal Server Error', 500),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/distributor-channel/v1/ekspedisi/nama-ekspedisi');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }
}
