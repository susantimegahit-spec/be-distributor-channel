<?php

namespace Tests\Feature;

use App\Models\CustomerShipto;
use App\Models\Distributor;
use App\Models\Expedition;
use App\Models\ExpeditionRate;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExpeditionRateRankTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $warehouse;
    protected Distributor $distributor;
    protected CustomerShipto $shipto1;
    protected CustomerShipto $shipto2;
    protected Expedition $expeditionA;
    protected Expedition $expeditionB;
    protected ExpeditionRate $rateA;
    protected ExpeditionRate $rateB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Role & User
        $role = Role::firstOrCreate(
            ['id' => 1],
            ['name' => 'Admin', 'is_active' => true, 'accessible_systems' => ['distributor', 'ekspedisi']]
        );

        $this->user = User::firstOrCreate(
            ['username' => 'test_logistic_admin'],
            [
                'name' => 'Test Logistic Admin',
                'email' => 'logistic_admin@test.com',
                'password' => Hash::make('secret123'),
                'role_id' => $role->id,
                'is_active' => true,
            ]
        );

        // Create Warehouse (origin)
        $this->warehouse = Warehouse::firstOrCreate(
            ['whs_code' => 'FG04'],
            [
                'whs_name' => 'Gudang Finished Goods 04',
                'city'     => 'Surabaya',
            ]
        );

        // Create Distributor (customer)
        $this->distributor = Distributor::firstOrCreate(
            ['code_customer' => 'C210000285'],
            [
                'name'   => 'PT Maju Makmur Sentosa',
                'status' => 1,
            ]
        );

        // Create Customer Shiptos
        $this->shipto1 = CustomerShipto::firstOrCreate(
            ['card_code' => 'C210000285', 'address' => 'SHIPTO-SOLO-01'],
            [
                'name'  => 'Gudang Utama Solo',
                'city'  => 'Surakarta',
                'alias' => 'Gudang Solo',
            ]
        );

        $this->shipto2 = CustomerShipto::firstOrCreate(
            ['card_code' => 'C210000285', 'address' => 'SHIPTO-BYL-01'],
            [
                'name'  => 'Gudang Cabang Boyolali',
                'city'  => 'Boyolali',
                'alias' => 'Gudang Boyolali',
            ]
        );

        // Create Expeditions
        $this->expeditionA = Expedition::firstOrCreate(
            ['expedition_code' => 'EXP-001'],
            [
                'expedition_name' => 'KALOG Express',
                'status'          => 'ACTIVE',
            ]
        );

        $this->expeditionB = Expedition::firstOrCreate(
            ['expedition_code' => 'EXP-002'],
            [
                'expedition_name' => 'Dakota Cargo',
                'status'          => 'ACTIVE',
            ]
        );

        // Create Expedition Rates (Rate A is cheaper than Rate B)
        $this->rateA = ExpeditionRate::updateOrCreate(
            [
                'expedition_id'  => $this->expeditionA->id,
                'warehouse_id'   => $this->warehouse->id,
                'destination_id' => $this->shipto1->id,
            ],
            [
                'transport_mode'  => 'DARAT',
                'service_type'    => 'REGULER',
                'min_tonnage'     => 100,
                'max_tonnage'     => 5000,
                'price'           => 1500000,
                'eta_days'        => 2,
                'status'          => 'ACTIVE',
                'flag'            => true,
                'approval_status' => 'APPROVED',
            ]
        );

        $this->rateB = ExpeditionRate::updateOrCreate(
            [
                'expedition_id'  => $this->expeditionB->id,
                'warehouse_id'   => $this->warehouse->id,
                'destination_id' => $this->shipto1->id,
            ],
            [
                'transport_mode'  => 'DARAT',
                'service_type'    => 'REGULER',
                'min_tonnage'     => 100,
                'max_tonnage'     => 5000,
                'price'           => 1850000,
                'eta_days'        => 3,
                'status'          => 'ACTIVE',
                'flag'            => true,
                'approval_status' => 'APPROVED',
            ]
        );
    }

    public function test_rank_returns_recommendations_when_destination_id_is_passed_as_card_code(): void
    {
        // FE sends destination_id with card_code value
        $response = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/ekspedisi/rates/rank?origin=FG04&destination_id=C210000285");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        // Verify sorted by price ASC: rateA (1.5M) before rateB (1.85M)
        $data = $response->json('data');
        $this->assertEquals($this->rateA->id, $data[0]['id']);
        $this->assertEquals($this->rateB->id, $data[1]['id']);
    }

    public function test_rank_returns_recommendations_when_destination_id_is_lowercase_card_code(): void
    {
        // Case-insensitive card_code resolution
        $response = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/ekspedisi/rates/rank?origin=fg04&destination_id=c210000285");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_rank_returns_recommendations_when_destination_id_is_numeric_shipto_id(): void
    {
        // FE sends numeric destination_id pointing to shipto1
        $response = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/ekspedisi/rates/rank?origin=FG04&destination_id={$this->shipto1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_rank_returns_recommendations_when_secondary_shipto_resolves_all_customer_rates(): void
    {
        // FE sends destination_id of shipto2 (secondary shipto), rate was registered under shipto1 of the same customer
        $response = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/ekspedisi/rates/rank?origin=FG04&destination_id={$this->shipto2->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_rank_returns_recommendations_when_card_code_param_is_passed_directly(): void
    {
        // FE sends card_code directly
        $response = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/ekspedisi/rates/rank?origin=FG04&card_code=C210000285");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_rank_returns_recommendations_via_post_method(): void
    {
        // FE calls via POST with JSON body
        $response = $this->actingAs($this->user)
            ->postJson("/api/distributor-channel/v1/ekspedisi/rates/rank", [
                'origin'         => 'FG04',
                'destination_id' => 'C210000285',
                'weight'         => 1000,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_rank_filters_by_weight_tonnage_correctly(): void
    {
        // Out of range weight (6000 kg exceeds max_tonnage 5000)
        $response = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/ekspedisi/rates/rank?origin=FG04&destination_id=C210000285&weight=6000");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');

        // Within range weight (500 kg)
        $validResponse = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/ekspedisi/rates/rank?origin=FG04&destination_id=C210000285&weight=500");

        $validResponse->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_rank_validation_fails_when_origin_or_destination_is_missing(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/ekspedisi/rates/rank?origin=FG04");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
