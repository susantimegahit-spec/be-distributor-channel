<?php

namespace Tests\Feature;

use App\Models\PurchaseQuotation;
use App\Models\PurchaseQuotationOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseQuotationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_create_purchase_quotation_from_selected_pr_items()
    {
        $payload = [
            'title' => 'Permintaan Penawaran Sparepart Kompresor',
            'notes' => 'Tolong kirimkan penawaran paling lambat akhir minggu',
            'items' => [
                [
                    'requestId'     => '261010008',
                    'requestNumber' => '261010008',
                    'itemCode'      => 'ISP001694',
                    'itemName'      => 'RUMAH FILTER KOMPRESOR',
                    'quantity'      => 1,
                    'unit'          => 'Pcs',
                    'warehouse'     => 'FG01',
                    'requiredDate'  => '2026-10-28',
                    'notes'         => 'Untuk unit kompresor utama',
                ],
                [
                    'requestId'     => '261010008',
                    'requestNumber' => '261010008',
                    'itemCode'      => 'ISP000805',
                    'itemName'      => 'THINNER',
                    'quantity'      => 20,
                    'unit'          => 'Liter',
                    'warehouse'     => 'SPR04',
                    'requiredDate'  => '2026-10-30',
                ],
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchasing-request/quotations', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Request for Quotation berhasil dibuat.',
            ])
            ->assertJsonPath('data.title', 'Permintaan Penawaran Sparepart Kompresor')
            ->assertJsonPath('data.status', 'OPEN')
            ->assertJsonPath('data.items.0.itemCode', 'ISP001694')
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.items.0.unit', 'Pcs')
            ->assertJsonPath('data.items.1.itemCode', 'ISP000805')
            ->assertJsonPath('data.items.1.quantity', 20);

        $data = $response->json('data');
        $this->assertNotEmpty($data['number']);
        $this->assertStringStartsWith('RFQ-', $data['number']);
        $this->assertNotEmpty($data['token']);
        $this->assertEquals(32, strlen($data['token']));

        $this->assertDatabaseHas('purchase_quotations', [
            'id'     => $data['id'],
            'number' => $data['number'],
            'token'  => $data['token'],
            'status' => 'OPEN',
        ]);

        $this->assertDatabaseHas('purchase_quotation_items', [
            'purchase_quotation_id' => $data['id'],
            'item_code'             => 'ISP001694',
            'quantity'              => 1,
        ]);
    }

    public function test_user_can_list_quotations_with_filters()
    {
        $payload1 = [
            'title' => 'Pengadaan Cat',
            'items' => [
                [
                    'itemCode' => 'ISP000805',
                    'itemName' => 'THINNER',
                    'quantity' => 10,
                ],
            ],
        ];

        $payload2 = [
            'title' => 'Pengadaan Baut',
            'items' => [
                [
                    'itemCode' => 'ISP000123',
                    'itemName' => 'BAUT M10',
                    'quantity' => 100,
                ],
            ],
        ];

        $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/purchasing-request/quotations', $payload1);
        $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/purchasing-request/quotations', $payload2);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/purchasing-request/quotations?search=Cat');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Pengadaan Cat', $data[0]['title']);
    }

    public function test_user_can_view_update_and_delete_quotation()
    {
        $createResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchasing-request/quotations', [
                'title' => 'Sebelum Update',
                'items' => [
                    [
                        'itemCode' => 'ISP001694',
                        'itemName' => 'RUMAH FILTER KOMPRESOR',
                        'quantity' => 1,
                    ],
                ],
            ]);

        $quotationId = $createResponse->json('data.id');

        // View detail
        $viewResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/purchasing-request/quotations/' . $quotationId);
        $viewResponse->assertStatus(200)
            ->assertJsonPath('data.title', 'Sebelum Update');

        // Update
        $updateResponse = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/v1/purchasing-request/quotations/' . $quotationId, [
                'title'  => 'Sesudah Update',
                'status' => 'CLOSED',
            ]);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.title', 'Sesudah Update')
            ->assertJsonPath('data.status', 'CLOSED');

        $this->assertDatabaseHas('purchase_quotations', [
            'id'     => $quotationId,
            'title'  => 'Sesudah Update',
            'status' => 'CLOSED',
        ]);

        // Delete
        $deleteResponse = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/v1/purchasing-request/quotations/' . $quotationId);
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('purchase_quotations', ['id' => $quotationId]);
    }

    public function test_public_vendor_can_view_quotation_and_submit_offer()
    {
        $createResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchasing-request/quotations', [
                'title' => 'Pengadaan Thinner dan Filter',
                'items' => [
                    [
                        'requestId'     => '261010008',
                        'requestNumber' => '261010008',
                        'itemCode'      => 'ISP001694',
                        'itemName'      => 'RUMAH FILTER KOMPRESOR',
                        'quantity'      => 2,
                        'unit'          => 'Pcs',
                    ],
                    [
                        'requestId'     => '261010008',
                        'requestNumber' => '261010008',
                        'itemCode'      => 'ISP000805',
                        'itemName'      => 'THINNER',
                        'quantity'      => 10,
                        'unit'          => 'Liter',
                    ],
                ],
            ]);

        $token = $createResponse->json('data.token');
        $items = $createResponse->json('data.items');
        $filterItemId = $items[0]['id'];
        $thinnerItemId = $items[1]['id'];

        // Public view quotation by token (no auth required)
        $publicViewResponse = $this->getJson('/api/v1/public/quotations/' . $token);
        $publicViewResponse->assertStatus(200)
            ->assertJsonPath('data.token', $token)
            ->assertJsonCount(2, 'data.items');

        // Also test alternate public route /api/quotation-offer/{token}
        $altViewResponse = $this->getJson('/api/quotation-offer/' . $token);
        $altViewResponse->assertStatus(200)
            ->assertJsonPath('data.token', $token);

        // Vendor submits offer
        $vendorPayload = [
            'vendor' => [
                'company' => 'PT Makmur Jaya Abadi',
                'name'    => 'Hendro Siswanto',
                'email'   => 'hendro@makmur.co.id',
                'phone'   => '081298765432',
                'notes'   => 'Franco pabrik Gresik, tempo 30 hari',
            ],
            'items' => [
                [
                    'id'        => $filterItemId,
                    'itemCode'  => 'ISP001694',
                    'itemName'  => 'RUMAH FILTER KOMPRESOR',
                    'quantity'  => 2,
                    'unitPrice' => 250000,
                    'leadTime'  => '5 hari kerja',
                    'notes'     => 'Original equipment part',
                ],
                [
                    'id'        => $thinnerItemId,
                    'itemCode'  => 'ISP000805',
                    'itemName'  => 'THINNER',
                    'quantity'  => 10,
                    'unitPrice' => 45000,
                    'leadTime'  => 'Ready stock',
                    'notes'     => 'Kemasan jerigen 5L',
                ],
            ],
        ];

        // Submit to public endpoint
        $submitResponse = $this->postJson('/api/v1/public/quotations/' . $token . '/offers', $vendorPayload);

        $submitResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Penawaran harga vendor berhasil dikirim.',
            ])
            ->assertJsonPath('data.vendor.company', 'PT Makmur Jaya Abadi')
            ->assertJsonPath('data.vendor.name', 'Hendro Siswanto')
            ->assertJsonPath('data.status', 'SUBMITTED');

        // Total price = (2 * 250,000) + (10 * 45,000) = 500,000 + 450,000 = 950,000
        $this->assertEquals(950000, $submitResponse->json('data.totalPrice'));

        $offerId = $submitResponse->json('data.id');

        // Check internal offer listing and detail
        $offerListResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/purchasing-request/quotation-offers');
        $offerListResponse->assertStatus(200)
            ->assertJsonPath('data.data.0.vendor.company', 'PT Makmur Jaya Abadi');

        // Check offers for specific quotation
        $quotationId = $createResponse->json('data.id');
        $quotationOffersResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/purchasing-request/quotations/' . $quotationId . '/offers');
        $quotationOffersResponse->assertStatus(200)
            ->assertJsonCount(1, 'data.data');

        // Detail offer
        $offerDetailResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/purchasing-request/quotation-offers/' . $offerId);
        $offerDetailResponse->assertStatus(200)
            ->assertJsonPath('data.vendor.company', 'PT Makmur Jaya Abadi');

        // Update offer (e.g. PIC marks it ACCEPTED)
        $updateOfferResponse = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/v1/purchasing-request/quotation-offers/' . $offerId, [
                'status' => 'ACCEPTED',
            ]);
        $updateOfferResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'ACCEPTED');

        // Delete offer
        $deleteOfferResponse = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/v1/purchasing-request/quotation-offers/' . $offerId);
        $deleteOfferResponse->assertStatus(200);

        $this->assertDatabaseMissing('purchase_quotation_offers', ['id' => $offerId]);
    }

    public function test_submit_offer_via_alternate_public_route()
    {
        $createResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchasing-request/quotations', [
                'title' => 'Pengadaan Thinner',
                'items' => [
                    [
                        'itemCode'  => 'ISP000805',
                        'itemName'  => 'THINNER',
                        'quantity'  => 5,
                        'unit'      => 'Liter',
                    ],
                ],
            ]);

        $token = $createResponse->json('data.token');

        $vendorPayload = [
            'vendor' => [
                'company' => 'CV Sumber Makmur',
                'name'    => 'Budi',
                'email'   => 'budi@makmur.com',
                'phone'   => '08111222333',
            ],
            'items' => [
                [
                    'itemCode'  => 'ISP000805',
                    'itemName'  => 'THINNER',
                    'quantity'  => 5,
                    'unitPrice' => 40000,
                    'leadTime'  => '3 hari',
                ],
            ],
        ];

        $response = $this->postJson('/api/quotation-offer/' . $token, $vendorPayload);
        $response->assertStatus(201)
            ->assertJsonPath('data.vendor.company', 'CV Sumber Makmur')
            ->assertJsonPath('data.totalPrice', 200000);
    }
}

