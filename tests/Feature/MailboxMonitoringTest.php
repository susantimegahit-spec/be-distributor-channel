<?php

namespace Tests\Feature;

use App\Models\Mailbox;
use App\Models\MailboxDepartment;
use App\Models\MailboxQuotaRecommendation;
use Database\Seeders\MailboxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailboxMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailbox_seeder_runs_successfully(): void
    {
        $this->seed(MailboxSeeder::class);

        $this->assertGreaterThan(0, Mailbox::count());
        $this->assertGreaterThan(0, MailboxDepartment::count());
        $this->assertGreaterThan(0, MailboxQuotaRecommendation::count());

        $critical = Mailbox::where('status', Mailbox::STATUS_CRITICAL)->first();
        $this->assertNotNull($critical);
        $this->assertGreaterThanOrEqual(95, (float)$critical->usage_percentage);
    }

    public function test_dashboard_route_is_accessible_with_pulse_session(): void
    {
        $this->seed(MailboxSeeder::class);

        $response = $this->withSession(['pulse_authenticated' => true])
            ->get('/monitoringsm/mailboxes');

        $response->assertStatus(200);
        $response->assertSee('Monitoring Mailbox Server PT Susanti Megah');
    }

    public function test_mailbox_list_route_and_filter(): void
    {
        $this->seed(MailboxSeeder::class);

        $response = $this->withSession(['pulse_authenticated' => true])
            ->get('/monitoringsm/mailboxes/list?status=CRITICAL');

        $response->assertStatus(200);
        $response->assertSee('CRITICAL');
    }

    public function test_mailbox_show_detail_route(): void
    {
        $this->seed(MailboxSeeder::class);
        $mailbox = Mailbox::first();

        $response = $this->withSession(['pulse_authenticated' => true])
            ->get('/monitoringsm/mailboxes/' . $mailbox->id);

        $response->assertStatus(200);
        $response->assertSee($mailbox->email);
        $response->assertSeeText('Prediksi & Pertumbuhan Storage', false);
    }

    public function test_recommendations_and_approval_flow(): void
    {
        $this->seed(MailboxSeeder::class);
        $rec = MailboxQuotaRecommendation::where('status', 'PENDING')->first();
        $this->assertNotNull($rec);

        $response = $this->withSession(['pulse_authenticated' => true])
            ->post('/monitoringsm/mailboxes/recommendations/' . $rec->id, [
                'status' => 'APPROVED',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mailbox_quota_recommendations', [
            'id' => $rec->id,
            'status' => 'APPROVED',
        ]);
    }

    public function test_executive_reports_route(): void
    {
        $this->seed(MailboxSeeder::class);

        $response = $this->withSession(['pulse_authenticated' => true])
            ->get('/monitoringsm/mailboxes/reports');

        $response->assertStatus(200);
        $response->assertSee('MAILBOX STORAGE &amp; CAPACITY USAGE REPORT', false);
    }

    public function test_csv_export(): void
    {
        $this->seed(MailboxSeeder::class);

        $response = $this->withSession(['pulse_authenticated' => true])
            ->get('/monitoringsm/mailboxes/export/csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
