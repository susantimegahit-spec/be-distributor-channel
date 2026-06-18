<?php

namespace Tests\Feature;

use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Notification User',
            'username' => 'notifuser',
            'email' => 'notif@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test_token')->plainTextToken;
    }

    public function test_user_can_send_test_notification(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/distributor-channel/v1/notifications/test', [
            'title' => 'Testing Notif',
            'message' => 'Pesan test dari PHPUnit.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notifikasi test berhasil dikirim.',
                'data' => [
                    'user_id' => $this->user->id,
                    'title' => 'Testing Notif',
                    'message' => 'Pesan test dari PHPUnit.',
                    'is_read' => false,
                ],
            ]);

        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $this->user->id,
            'title' => 'Testing Notif',
            'message' => 'Pesan test dari PHPUnit.',
            'read_at' => null,
        ]);
    }

    public function test_user_can_list_and_mark_notification_as_read(): void
    {
        $notification = PushNotification::create([
            'user_id' => $this->user->id,
            'title' => 'Order Baru',
            'message' => 'Ada order baru.',
            'type' => 'order',
        ]);

        $listResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/distributor-channel/v1/notifications');

        $listResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'unread_count' => 1,
                ],
            ])
            ->assertJsonPath('data.notifications.0.id', $notification->id);

        $readResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/distributor-channel/v1/notifications/{$notification->id}/read");

        $readResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notifikasi berhasil ditandai dibaca.',
                'data' => [
                    'id' => $notification->id,
                    'is_read' => true,
                ],
            ]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        PushNotification::create([
            'user_id' => $this->user->id,
            'title' => 'Notifikasi 1',
            'message' => 'Pesan 1',
        ]);
        PushNotification::create([
            'user_id' => $this->user->id,
            'title' => 'Notifikasi 2',
            'message' => 'Pesan 2',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/distributor-channel/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'updated' => 2,
                    'unread_count' => 0,
                ],
            ]);

        $this->assertSame(0, PushNotification::where('user_id', $this->user->id)->whereNull('read_at')->count());
    }
}
