<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Distributor;
use App\Models\PushNotification;
use App\Events\PushNotificationCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Role $role;
    protected Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([PushNotificationCreated::class]);

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
     * Test notification endpoints require authentication.
     */
    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/distributor-channel/v1/notifications')->assertStatus(401);
        $this->postJson('/api/distributor-channel/v1/notifications/test')->assertStatus(401);
        $this->postJson('/api/distributor-channel/v1/notifications/read-all')->assertStatus(401);
    }

    /**
     * Test getting empty notification list.
     */
    public function test_get_notifications_empty_list(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/notifications');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Daftar notifikasi berhasil diambil.',
                'data' => [
                    'notifications' => [],
                    'unread_count' => 0,
                ]
            ]);
    }

    /**
     * Test sending test notification.
     */
    public function test_send_test_notification(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/notifications/test', [
            'title' => 'Halo Uji Coba',
            'message' => 'Ini pesan testing',
            'type' => 'info'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notifikasi test berhasil dikirim.',
                'data' => [
                    'title' => 'Halo Uji Coba',
                    'message' => 'Ini pesan testing',
                    'type' => 'info',
                    'is_read' => false,
                ]
            ]);

        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $this->user->id,
            'title' => 'Halo Uji Coba',
        ]);
    }

    /**
     * Test marking notification as read.
     */
    public function test_mark_as_read(): void
    {
        $notification = PushNotification::create([
            'user_id' => $this->user->id,
            'title' => 'Belum Dibaca',
            'message' => 'Pesan penting',
            'type' => 'info',
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/notifications/' . $notification->id . '/read');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notifikasi berhasil ditandai dibaca.',
                'data' => [
                    'id' => $notification->id,
                    'is_read' => true,
                ]
            ]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /**
     * Test marking all notifications as read.
     */
    public function test_mark_all_as_read(): void
    {
        PushNotification::create([
            'user_id' => $this->user->id,
            'title' => 'Notif 1',
            'message' => 'Pesan 1',
        ]);

        PushNotification::create([
            'user_id' => $this->user->id,
            'title' => 'Notif 2',
            'message' => 'Pesan 2',
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Semua notifikasi berhasil ditandai dibaca.',
                'data' => [
                    'updated' => 2,
                    'unread_count' => 0,
                ]
            ]);
    }

    /**
     * Test showing a specific notification.
     */
    public function test_show_notification(): void
    {
        $notification = PushNotification::create([
            'user_id' => $this->user->id,
            'title' => 'Notif Detail',
            'message' => 'Detail pesan',
            'type' => 'info',
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/notifications/' . $notification->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Detail notifikasi berhasil diambil.',
                'data' => [
                    'id' => $notification->id,
                    'title' => 'Notif Detail',
                    'message' => 'Detail pesan',
                ]
            ]);
    }

    /**
     * Test showing a non-existent notification returns 404.
     */
    public function test_show_notification_not_found(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/distributor-channel/v1/notifications/9999');

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status_code' => 404,
                'message' => 'Resource not found.',
            ]);
    }

    /**
     * Test deleting a specific notification.
     */
    public function test_delete_notification(): void
    {
        $notification = PushNotification::create([
            'user_id' => $this->user->id,
            'title' => 'Notif Hapus',
            'message' => 'Pesan hapus',
            'type' => 'info',
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/distributor-channel/v1/notifications/' . $notification->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notifikasi berhasil dihapus.',
            ]);

        $this->assertDatabaseMissing('push_notifications', [
            'id' => $notification->id,
        ]);
    }

    /**
     * Test deleting a non-existent notification returns 404.
     */
    public function test_delete_notification_not_found(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/distributor-channel/v1/notifications/9999');

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status_code' => 404,
                'message' => 'Resource not found.',
            ]);
    }
}
