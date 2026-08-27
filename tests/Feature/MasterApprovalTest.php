<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Distributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Role $role;
    protected Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\MasterApprovalSeeder::class);

        // Create standard role
        $this->role = Role::create([
            'name' => 'distributor',
            'is_active' => true,
        ]);

        // Create distributor
        $this->distributor = Distributor::create([
            'code_customer' => 'C110000411',
            'name' => 'PT XYZ',
            'status' => 1,
        ]);

        // Create user
        $this->user = User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->role->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test getting master approvals without authentication.
     */
    public function test_get_master_approvals_unauthenticated(): void
    {
        $response = $this->getJson('/api/distributor-channel/v1/master-approvals');

        $response->assertStatus(401);
    }

    /**
     * Test getting master approvals list with authentication.
     */
    public function test_get_master_approvals_authenticated(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/master-approvals');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Daftar master approval berhasil diambil.',
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'action',
                        'notification_type',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ]);

        // Verify ordering is ID ascending
        $data = $response->json('data');
        $this->assertCount(6, $data);
        $this->assertEquals(1, $data[0]['id']);
        $this->assertEquals('DRAFT', $data[0]['name']);
        $this->assertEquals(6, $data[5]['id']);
        $this->assertEquals('ORDER_APPROVED', $data[5]['name']);
    }

    /**
     * Test getting approval stages from SAP API successfully.
     */
    public function test_get_approval_stages_from_sap_success(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/api/getstages' => \Illuminate\Support\Facades\Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'WstCode' => '1',
                        'Name' => 'Purchasing Manager',
                        'Remarks' => 'Purchasing Manager',
                        'Flex' => '0',
                    ],
                    [
                        'WstCode' => '2',
                        'Name' => 'Director',
                        'Remarks' => 'Director',
                        'Flex' => '0',
                    ],
                ],
            ], 200),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/master-approvals/stages');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data approval stages berhasil diambil dari SAP.',
                'data' => [
                    [
                        'WstCode' => '1',
                        'Name' => 'Purchasing Manager',
                    ],
                    [
                        'WstCode' => '2',
                        'Name' => 'Director',
                    ],
                ],
            ]);
    }

    /**
     * Test getting approval stages uses cache on subsequent calls.
     */
    public function test_get_approval_stages_uses_cache(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        \Illuminate\Support\Facades\Http::fake([
            '*/api/getstages' => \Illuminate\Support\Facades\Http::sequence()
                ->push([
                    'ErrorCode' => 0,
                    'Message' => '',
                    'Result' => [
                        [
                            'WstCode' => '1',
                            'Name' => 'Cached Manager',
                        ],
                    ],
                ], 200)
                ->push([
                    'ErrorCode' => 0,
                    'Message' => '',
                    'Result' => [
                        [
                            'WstCode' => '99',
                            'Name' => 'New Different Manager',
                        ],
                    ],
                ], 200),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        // First call - calls SAP (consumes 1st response from sequence)
        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/master-approvals/stages');

        $res1->assertStatus(200);
        $this->assertEquals('Cached Manager', $res1->json('data.0.Name'));

        // Second call - should hit Cache (does not consume 2nd response from sequence)
        $res2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/master-approvals/stages');

        $res2->assertStatus(200);
        $this->assertEquals('Cached Manager', $res2->json('data.0.Name'));

        // Third call with force refresh = true - bypasses cache (consumes 2nd response from sequence)
        $res3 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/master-approvals/stages?refresh=true');

        $res3->assertStatus(200);
        $this->assertEquals('New Different Manager', $res3->json('data.0.Name'));
    }

    /**
     * Test getting approvals without authentication.
     */
    public function test_get_approvals_unauthenticated(): void
    {
        $response = $this->getJson('/api/distributor-channel/v1/master-approvals/approvals');

        $response->assertStatus(401);
    }

    /**
     * Test getting approvals from SAP API with Status 'W' mapped to 'Pending'.
     */
    public function test_get_approvals_from_sap_success(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/api/getapproval' => \Illuminate\Support\Facades\Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'WddCode' => '7702',
                        'DocEntry' => '0',
                        'ObjType' => '22',
                        'DocDate' => '20260810',
                        'CurrStep' => '2',
                        'Status' => 'W',
                        'Remarks' => '',
                    ],
                    [
                        'WddCode' => '7687',
                        'DocEntry' => '0',
                        'ObjType' => '22',
                        'DocDate' => '20260810',
                        'CurrStep' => '2',
                        'Status' => 'Y',
                        'Remarks' => 'Approved by manager',
                    ],
                    [
                        'WddCode' => '7690',
                        'DocEntry' => '0',
                        'ObjType' => '22',
                        'DocDate' => '20260810',
                        'CurrStep' => '2',
                        'Status' => 'N',
                        'Remarks' => 'Rejected',
                    ],
                ],
            ], 200),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/master-approvals/approvals');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data approval berhasil diambil dari SAP.',
            ]);

        $data = $response->json('data');
        $this->assertCount(3, $data);
        // Verify Status 'W' is mapped to 'Pending'
        $this->assertEquals('7702', $data[0]['WddCode']);
        $this->assertEquals('Pending', $data[0]['Status']);

        // Verify other status mappings
        $this->assertEquals('Approved', $data[1]['Status']);
        $this->assertEquals('Rejected', $data[2]['Status']);
    }

    /**
     * Test getting approvals with custom query and aliases.
     */
    public function test_get_approvals_with_custom_query(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/api/getapproval' => function (\Illuminate\Http\Client\Request $request) {
                $payload = $request->data();
                $this->assertEquals(5, $payload['CustomQuery']);

                return \Illuminate\Support\Facades\Http::response([
                    'ErrorCode' => 0,
                    'Message' => '',
                    'Result' => [
                        [
                            'WddCode' => '9999',
                            'DocEntry' => '10',
                            'ObjType' => '22',
                            'DocDate' => '20260820',
                            'CurrStep' => '1',
                            'Status' => 'W',
                            'Remarks' => 'Custom Query Item',
                        ],
                    ],
                ], 200);
            },
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/master-approvals/getapproval', [
            'CustomQuery' => 5,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Pending', $response->json('data.0.Status'));
        $this->assertEquals('9999', $response->json('data.0.WddCode'));
    }

    /**
     * Test getting approvals caching and force refresh.
     */
    public function test_get_approvals_uses_cache(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        \Illuminate\Support\Facades\Http::fake([
            '*/api/getapproval' => \Illuminate\Support\Facades\Http::sequence()
                ->push([
                    'ErrorCode' => 0,
                    'Message' => '',
                    'Result' => [
                        [
                            'WddCode' => '7702',
                            'Status' => 'W',
                        ],
                    ],
                ], 200)
                ->push([
                    'ErrorCode' => 0,
                    'Message' => '',
                    'Result' => [
                        [
                            'WddCode' => '8888',
                            'Status' => 'W',
                        ],
                    ],
                ], 200),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        // First call - consumes 1st response
        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/master-approvals/approvals');

        $res1->assertStatus(200);
        $this->assertEquals('7702', $res1->json('data.0.WddCode'));

        // Second call - cached
        $res2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/master-approvals/approvals');

        $res2->assertStatus(200);
        $this->assertEquals('7702', $res2->json('data.0.WddCode'));

        // Third call - refresh bypasses cache
        $res3 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/master-approvals/approvals?refresh=true');

        $res3->assertStatus(200);
        $this->assertEquals('8888', $res3->json('data.0.WddCode'));
    }

    /**
     * Test getting approvals error handling from SAP.
     */
    public function test_get_approvals_sap_error(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/api/getapproval' => \Illuminate\Support\Facades\Http::response([
                'ErrorCode' => 1,
                'Message' => 'Database connection failed in SAP',
                'Result' => [],
            ], 200),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/master-approvals/approvals');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test getting approvals with empty dummy placeholder from SAP returns 'Data not found.'
     */
    public function test_get_approvals_empty_dummy_returns_data_not_found(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        \Illuminate\Support\Facades\Http::fake([
            '*/api/getapproval' => \Illuminate\Support\Facades\Http::response([
                'ErrorCode' => 0,
                'Message' => '',
                'Result' => [
                    [
                        'WddCode' => '0',
                        'DocEntry' => '0',
                        'ObjType' => '',
                        'DocDate' => '',
                        'CurrStep' => '0',
                        'Status' => '',
                        'Remarks' => '',
                    ],
                ],
            ], 200),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/master-approvals/approvals');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data not found.',
                'data' => [],
            ]);

        $this->assertCount(0, $response->json('data'));
    }

    /**
     * Test approving SAP request without authentication.
     */
    public function test_approve_sap_unauthenticated(): void
    {
        $response = $this->postJson('/api/distributor-channel/v1/master-approvals/approve-sap', [
            'approvalRequestCode' => '7702',
            'Username' => 'test_user',
            'Password' => 'secret',
            'Status' => 'Y',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test approving SAP request successfully (Status Y).
     */
    public function test_approve_sap_success(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/api/ApproveSAP' => function (\Illuminate\Http\Client\Request $request) {
                $payload = $request->data();
                $this->assertEquals('7702', $payload['approvalRequestCode']);
                $this->assertEquals('manager_user', $payload['Username']);
                $this->assertEquals('password123', $payload['Password']);
                $this->assertEquals('Y', $payload['Status']);
                $this->assertEquals('Approved by manager', $payload['Remarks']);

                return \Illuminate\Support\Facades\Http::response([
                    'ErrorCode' => 0,
                    'Message' => 'Success',
                    'Result' => [
                        'approvalRequestCode' => '7702',
                        'Status' => 'Y',
                    ],
                ], 200);
            },
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/master-approvals/approve-sap', [
            'approvalRequestCode' => '7702',
            'Username' => 'manager_user',
            'Password' => 'password123',
            'Status' => 'Y',
            'Remarks' => 'Approved by manager',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dokumen approval berhasil disetujui (Approve) di SAP.',
            ]);
    }

    /**
     * Test rejecting SAP request successfully (Status N with Remarks).
     */
    public function test_reject_sap_success(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/api/ApproveSAP' => function (\Illuminate\Http\Client\Request $request) {
                $payload = $request->data();
                $this->assertEquals('7702', $payload['approvalRequestCode']);
                $this->assertEquals('N', $payload['Status']);
                $this->assertEquals('Harga tidak cocok', $payload['Remarks']);

                return \Illuminate\Support\Facades\Http::response([
                    'ErrorCode' => 0,
                    'Message' => 'Success',
                    'Result' => [
                        'approvalRequestCode' => '7702',
                        'Status' => 'N',
                    ],
                ], 200);
            },
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/master-approvals/approve-sap', [
            'approvalRequestCode' => '7702',
            'Username' => 'manager_user',
            'Password' => 'password123',
            'Status' => 'N',
            'Remarks' => 'Harga tidak cocok',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dokumen approval berhasil ditolak (Reject) di SAP.',
            ]);
    }

    /**
     * Test rejecting SAP request validation error when Remarks is empty.
     */
    public function test_reject_sap_fails_without_remarks(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/master-approvals/approve-sap', [
            'approvalRequestCode' => '7702',
            'Username' => 'manager_user',
            'Password' => 'password123',
            'Status' => 'N',
            'Remarks' => '',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Remarks wajib diisi jika status N (Reject).',
            ]);
    }

    /**
     * Test approve SAP validation error for invalid Status value.
     */
    public function test_approve_sap_invalid_status(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/master-approvals/approve-sap', [
            'approvalRequestCode' => '7702',
            'Username' => 'manager_user',
            'Password' => 'password123',
            'Status' => 'INVALID_STATUS',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Status hanya menerima nilai Y (Approve) atau N (Reject).',
            ]);
    }

    /**
     * Test approve SAP error from SAP API.
     */
    public function test_approve_sap_api_error(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/api/ApproveSAP' => \Illuminate\Support\Facades\Http::response([
                'ErrorCode' => -1,
                'Message' => 'User not authorized in SAP',
                'Result' => null,
            ], 200),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/master-approvals/approve-sap', [
            'approvalRequestCode' => '7702',
            'Username' => 'invalid_user',
            'Password' => 'wrong_pass',
            'Status' => 'Y',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }
}
