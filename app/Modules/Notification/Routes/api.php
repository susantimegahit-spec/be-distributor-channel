<?php

use App\Modules\Notification\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::post('v1/broadcasting/auth', fn (Request $request) => Broadcast::auth($request))
    ->middleware('auth:sanctum');

Route::prefix('v1/notifications')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/', [NotificationController::class, 'send']);
    Route::post('/test', [NotificationController::class, 'sendTest']);
    Route::post('/telegram/test', [NotificationController::class, 'sendTelegramTest']);
    Route::get('/telegram/connect-link', [NotificationController::class, 'getConnectLink']);
    Route::get('/telegram/recipients', [NotificationController::class, 'listTelegramRecipients']);
    Route::post('/telegram/recipients', [NotificationController::class, 'addTelegramRecipient']);
    Route::delete('/telegram/recipients/{id}', [NotificationController::class, 'deleteTelegramRecipient'])->whereNumber('id');
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/device-token', [NotificationController::class, 'registerDeviceToken']);
    Route::delete('/device-token', [NotificationController::class, 'deleteDeviceToken']);
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->whereNumber('id');
    Route::get('/{id}', [NotificationController::class, 'show'])->whereNumber('id');
    Route::delete('/{id}', [NotificationController::class, 'destroy'])->whereNumber('id');
});

// Public Telegram Webhook Endpoint
Route::post('v1/telegram/webhook', [NotificationController::class, 'handleWebhook']);
