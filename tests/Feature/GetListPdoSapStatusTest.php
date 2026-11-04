<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GetListPdoSapStatusTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name'      => 'Prod User',
            'username'  => 'produser',
            'email'     => 'produser@test.com',
            'password'  => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    public function test_get_list_pdo_provides_full_status_and_status_name(): void
    {
        Http::fake([
            '*/api/getListPDO' => Http::response([
                'ErrorCode' => 0,
                'Result'    => [
                    [
                        'DocEntry'   => '1001',
                        'DocNum'     => '260810001',
                        'Status'     => 'P',
                        'StatusName' => 'Planned',
                    ],
                    [
                        'DocEntry'   => '1002',
                        'DocNum'     => '260810002',
                        'Status'     => 'R',
                        'StatusName' => 'Released',
                    ],
                    [
                        'DocEntry'   => '1003',
                        'DocNum'     => '260810003',
                        'Status'     => 'L',
                        'StatusName' => 'Closed',
                    ],
                    [
                        'DocEntry'   => '1004',
                        'DocNum'     => '260810004',
                        'Status'     => 'C',
                        'StatusName' => 'Canceled',
                    ],
                ],
            ], 200),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/distributor-channel/v1/production/get-list-pdo-sap');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(4, $data);

        // Item 0: P -> PLANNED
        $this->assertEquals('PLANNED', $data[0]['Status']);
        $this->assertEquals('PLANNED', $data[0]['status']);
        $this->assertEquals('Planned', $data[0]['StatusName']);

        // Item 1: R -> RELEASED
        $this->assertEquals('RELEASED', $data[1]['Status']);
        $this->assertEquals('RELEASED', $data[1]['status']);
        $this->assertEquals('Released', $data[1]['StatusName']);

        // Item 2: L -> CLOSED
        $this->assertEquals('CLOSED', $data[2]['Status']);
        $this->assertEquals('CLOSED', $data[2]['status']);
        $this->assertEquals('Closed', $data[2]['StatusName']);

        // Item 3: C -> CANCELLED
        $this->assertEquals('CANCELLED', $data[3]['Status']);
        $this->assertEquals('CANCELLED', $data[3]['status']);
        $this->assertEquals('Canceled', $data[3]['StatusName']);
    }
}
