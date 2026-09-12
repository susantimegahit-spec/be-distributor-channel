<?php

namespace Tests\Feature;

use App\Models\MasterLeadtime;
use Tests\TestCase;

class MasterDataWebDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $migration = require database_path('migrations/ekspedisi/2026_09_12_000001_create_master_leadtimes_table.php');
        $migration->up();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/monitoringsm/master-data');
        $response->assertRedirect('/monitoringsm/login');
    }

    public function test_authenticated_admin_can_view_master_data_dashboard(): void
    {
        $response = $this->withSession(['pulse_authenticated' => true])
            ->get('/monitoringsm/master-data');

        $response->assertStatus(200)
            ->assertSee('Master Data Hub')
            ->assertSee('Master Leadtime')
            ->assertSee('Warehouse Origins')
            ->assertSee('Master Expeditions');
    }

    public function test_admin_hub_contains_link_to_master_data(): void
    {
        $response = $this->withSession(['pulse_authenticated' => true])
            ->get('/monitoringsm/hub');

        $response->assertStatus(200)
            ->assertSee('/monitoringsm/master-data')
            ->assertSee('Master Data Management (Dynamic CRUD)');
    }

    public function test_can_create_new_master_leadtime_record_via_web(): void
    {
        $response = $this->withSession(['pulse_authenticated' => true])
            ->post('/monitoringsm/master-data/master_leadtimes', [
                'origin_warehouse_code' => 'WHS-TEST',
                'origin_warehouse_name' => 'Gudang Uji Coba Surabaya',
                'destination_name'      => 'Gudang Uji Coba Denpasar',
                'destination_city'      => 'Denpasar',
                'avg_lead_time_days'    => '2.75',
                'lead_time_unit'        => 'DAYS',
                'transport_mode'        => 'LAND',
                'status'                => 'ACTIVE',
            ]);

        $response->assertRedirect('/monitoringsm/master-data?table=master_leadtimes');
        $response->assertSessionHas('success');

        $leadtime = MasterLeadtime::where('origin_warehouse_code', 'WHS-TEST')->first();
        $this->assertNotNull($leadtime);
        $this->assertEquals('Gudang Uji Coba Surabaya', $leadtime->origin_warehouse_name);
        $this->assertEquals('2.75', $leadtime->avg_lead_time_days);
    }

    public function test_can_update_master_leadtime_record_via_web(): void
    {
        $leadtime = MasterLeadtime::create([
            'origin_warehouse_name' => 'Gudang Awal',
            'destination_name'      => 'Tujuan Awal',
            'avg_lead_time_days'    => '1.50',
            'lead_time_unit'        => 'DAYS',
            'status'                => 'ACTIVE',
        ]);

        $response = $this->withSession(['pulse_authenticated' => true])
            ->post("/monitoringsm/master-data/master_leadtimes/{$leadtime->id}/update", [
                'origin_warehouse_name' => 'Gudang Telah Diupdate',
                'destination_name'      => 'Tujuan Telah Diupdate',
                'avg_lead_time_days'    => '3.50',
                'lead_time_unit'        => 'DAYS',
                'status'                => 'ACTIVE',
            ]);

        $response->assertRedirect('/monitoringsm/master-data?table=master_leadtimes');
        $response->assertSessionHas('success');

        $leadtime->refresh();
        $this->assertEquals('Gudang Telah Diupdate', $leadtime->origin_warehouse_name);
        $this->assertEquals('3.50', $leadtime->avg_lead_time_days);
    }

    public function test_can_delete_master_leadtime_record_via_web(): void
    {
        $leadtime = MasterLeadtime::create([
            'origin_warehouse_name' => 'Gudang Hapus',
            'destination_name'      => 'Tujuan Hapus',
            'avg_lead_time_days'    => '1.00',
            'lead_time_unit'        => 'DAYS',
            'status'                => 'ACTIVE',
        ]);

        $response = $this->withSession(['pulse_authenticated' => true])
            ->post("/monitoringsm/master-data/master_leadtimes/{$leadtime->id}/delete");

        $response->assertRedirect('/monitoringsm/master-data?table=master_leadtimes');
        $response->assertSessionHas('success');

        $this->assertNull(MasterLeadtime::find($leadtime->id));
    }
}
