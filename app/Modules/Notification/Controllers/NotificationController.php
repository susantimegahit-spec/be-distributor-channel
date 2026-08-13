<?php

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Notification\Requests\SendNotificationRequest;
use App\Modules\Notification\Services\NotificationService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponseFormatter;

    public function __construct(private NotificationService $notificationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 10), 50));

        return $this->successResponse(
            $this->notificationService->listForUser($request->user(), $perPage),
            'Daftar notifikasi berhasil diambil.'
        );
    }

    public function send(SendNotificationRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $targetUser = isset($payload['user_id'])
            ? User::findOrFail($payload['user_id'])
            : $request->user();

        unset($payload['user_id']);

        $notification = $this->notificationService->sendToUser($targetUser, $payload);

        return $this->successResponse(
            $notification->toFrontendPayload(),
            'Notifikasi berhasil dikirim.'
        );
    }

    public function sendTest(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:1000',
            'type' => 'nullable|string|max:50',
            'url' => 'nullable|string|max:500',
            'data' => 'nullable|array',
        ]);

        $notification = $this->notificationService->sendTest($request->user(), $payload);

        return $this->successResponse(
            $notification->toFrontendPayload(),
            'Notifikasi test berhasil dikirim.'
        );
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = $this->notificationService->markAsRead($request->user(), $id);

        if (!$notification) {
            abort(404, 'Notifikasi tidak ditemukan.');
        }

        return $this->successResponse(
            $notification->toFrontendPayload(),
            'Notifikasi berhasil ditandai dibaca.'
        );
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $this->notificationService->markAllAsRead($request->user());

        return $this->successResponse(
            [
                'updated' => $updated,
                'unread_count' => $this->notificationService->unreadCount($request->user()),
            ],
            'Semua notifikasi berhasil ditandai dibaca.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $notification = $this->notificationService->getById($request->user(), $id);

        if (!$notification) {
            abort(404, 'Notifikasi tidak ditemukan.');
        }

        return $this->successResponse(
            $notification->toFrontendPayload(),
            'Detail notifikasi berhasil diambil.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = $this->notificationService->delete($request->user(), $id);

        if (!$deleted) {
            abort(404, 'Notifikasi tidak ditemukan.');
        }

        return $this->successResponse(
            null,
            'Notifikasi berhasil dihapus.'
        );
    }

    /**
     * Register or update FCM device token for the authenticated user.
     */
    public function registerDeviceToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string',
            'device_type' => 'nullable|string|in:android,ios,web',
        ]);

        $deviceToken = $request->user()->deviceTokens()->updateOrCreate(
            ['fcm_token' => $validated['fcm_token']],
            ['device_type' => $validated['device_type'] ?? null]
        );

        return $this->successResponse([
            'id' => $deviceToken->id,
            'fcm_token' => $deviceToken->fcm_token,
            'device_type' => $deviceToken->device_type,
        ], 'Device token FCM berhasil didaftarkan.');
    }

    /**
     * Delete FCM device token for the authenticated user.
     */
    public function deleteDeviceToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $deleted = $request->user()->deviceTokens()
            ->where('fcm_token', $validated['fcm_token'])
            ->delete();

        return $this->successResponse([
            'deleted' => (bool) $deleted
        ], 'Device token FCM berhasil dihapus.');
    }
}
