<?php

namespace Tests\Feature;

use App\Models\MasterLeadtime;
use App\Models\User;
use App\Models\WarehouseOrigin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MasterLeadtimeCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected MasterLeadtime $leadtime;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Logistics Admin',
            'username' => 'logistics_admin',
            'email' => 'logistics_admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'logistic',
            'is_active' => true,
        ]);

        $this->leadtime = MasterLeadtime::create([
            'origin_warehouse_code' => 'WHS-SUB',
            'origin_warehouse_name' => 'Gudang Utama Surabaya Waru',
            'origin_city'           => 'SURABAYA',
            'destination_code'      => 'DEST-BDG',
            'destination_name'      => 'Bandung Hub',
            'destination_city'      => 'BANDUNG',
            'destination_province'  => 'JAWA BARAT',
            'avg_lead_time_days'    => 2.50,
            'min_lead_time_days'    => 2.00,
            'max_lead_time_days'    => 3.00,
            'lead_time_unit'        => 'DAYS',
            'transport_mode'        => 'LAND',
            'distance_km'           => 680.00,
            'status'                => 'ACTIVE',
            'remarks'               => 'Regular trucking',
            'created_by'            => $this->user->id,
            'updated_by'            => $this->user->id,
        ]);
    }

    public function test_get_leadtimes_list_with_filters(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/distributor-channel/v1/ekspedisi/leadtimes?search=Bandung');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Master lead times retrieved successfully.',
            ]);

        $items = $response->json('data.data');
        $this->assertNotEmpty($items);
        $this->assertEquals('Bandung Hub', $items[0]['destination_name']);
    }

    public function test_create_leadtime_successfully(): void
    {
        $payload = [
            'origin_warehouse_code' => 'WHS-JKT',
            'origin_warehouse_name' => 'Gudang Cakung',
            'origin_city'           => 'JAKARTA',
            'destination_code'      => 'DEST-SMG',
            'destination_name'      => 'Semarang Hub',
            'destination_city'      => 'SEMARANG',
            'destination_province'  => 'JAWA TENGAH',
            'avg_lead_time_days'    => 1.50,
            'transport_mode'        => 'LAND',
            'status'                => 'ACTIVE',
            'remarks'               => 'Fast track linehaul',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/ekspedisi/leadtimes', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Master lead time created successfully.',
                'data' => [
                    'destination_name' => 'Semarang Hub',
                    'avg_lead_time_days' => 1.50,
                ],
            ]);

        $this->assertDatabaseHas('master_leadtimes', [
            'destination_code' => 'DEST-SMG',
            'destination_city' => 'SEMARANG',
        ]);
    }

    public function test_create_leadtime_validation_error(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/distributor-channel/v1/ekspedisi/leadtimes', [
                'origin_warehouse_code' => 'WHS-JKT',
                // missing destination_name and avg_lead_time_days
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_get_leadtime_detail(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/distributor-channel/v1/ekspedisi/leadtimes/{$this->leadtime->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Master lead time detail retrieved successfully.',
                'data' => [
                    'id' => $this->leadtime->id,
                    'destination_name' => 'Bandung Hub',
                ],
            ]);
    }

    public function test_update_leadtime_successfully(): void
    {
        $payload = [
            'avg_lead_time_days' => 3.50,
            'remarks' => 'Updated due to toll road construction',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/distributor-channel/v1/ekspedisi/leadtimes/{$this->leadtime->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Master lead time updated successfully.',
                'data' => [
                    'avg_lead_time_days' => 3.50,
                    'remarks' => 'Updated due to toll road construction',
                ],
            ]);

        $this->assertEquals(3.50, (float) $this->leadtime->fresh()->avg_lead_time_days);
    }

    public function test_delete_leadtime_successfully(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/distributor-channel/v1/ekspedisi/leadtimes/{$this->leadtime->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Master lead time deleted successfully.',
            ]);

        $this->assertNull(MasterLeadtime::find($this->leadtime->id));
    }
}
