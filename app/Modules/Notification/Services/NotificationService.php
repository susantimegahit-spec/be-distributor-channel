<?php

namespace App\Modules\Notification\Services;

use App\Events\PushNotificationCreated;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NotificationService
{
    public function paginateForUser(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return PushNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage)
            ->through(fn (PushNotification $notification) => $notification->toFrontendPayload());
    }

    /**
     * @return array<string, mixed>
     */
    public function listForUser(User $user, int $perPage = 10): array
    {
        $notifications = $this->paginateForUser($user, $perPage);

        return [
            'notifications' => $notifications->items(),
            'unread_count' => $this->unreadCount($user),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendToUser(User $user, array $data): PushNotification
    {
        $notification = PushNotification::create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'message' => $data['message'],
            'type' => $data['type'] ?? 'info',
            'url' => $data['url'] ?? null,
            'data' => $data['data'] ?? [],
        ]);

        broadcast(new PushNotificationCreated($notification))->toOthers();

        // Push to Firebase Cloud Messaging (FCM) for mobile devices
        try {
            $deviceTokens = $user->deviceTokens()->pluck('fcm_token');
            if ($deviceTokens->isNotEmpty()) {
                $fcmService = app(\App\Services\FCMService::class);
                foreach ($deviceTokens as $token) {
                    $fcmService->sendNotification(
                        $token,
                        $notification->title,
                        $notification->message,
                        array_merge([
                            'notification_id' => (string) $notification->id,
                            'type' => (string) $notification->type,
                            'url' => (string) ($notification->url ?? ''),
                        ], $data['data'] ?? [])
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("FCM Push notification error for user [{$user->id}]: " . $e->getMessage());
        }

        return $notification;
    }

    /**
     * @param array<string, mixed> $data
     * @return \Illuminate\Support\Collection<int, \App\Models\PushNotification>
     */
    public function sendToUsers(Collection $users, array $data): Collection
    {
        return $users->map(fn (User $user) => $this->sendToUser($user, $data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendTest(User $sender, array $data = []): PushNotification
    {
        return $this->sendToUser($sender, [
            'title' => $data['title'] ?? 'Test Push Notification',
            'message' => $data['message'] ?? 'Notifikasi test berhasil dikirim dari backend.',
            'type' => $data['type'] ?? 'test',
            'url' => $data['url'] ?? null,
            'data' => array_merge([
                'source' => 'test-module',
                'sent_by' => $sender->id,
            ], $data['data'] ?? []),
        ]);
    }

    public function markAsRead(User $user, int $notificationId): ?PushNotification
    {
        $notification = PushNotification::query()
            ->where('user_id', $user->id)
            ->find($notificationId);

        if (!$notification) {
            return null;
        }

        if (!$notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification;
    }

    public function markAllAsRead(User $user): int
    {
        return PushNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadCount(User $user): int
    {
        return PushNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function getById(User $user, int $notificationId): ?PushNotification
    {
        return PushNotification::query()
            ->where('user_id', $user->id)
            ->find($notificationId);
    }

    public function delete(User $user, int $notificationId): bool
    {
        $notification = PushNotification::query()
            ->where('user_id', $user->id)
            ->find($notificationId);

        if (!$notification) {
            return false;
        }

        return (bool) $notification->delete();
    }
}
