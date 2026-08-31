<?php

namespace App\Notifications\Channels;

use App\Notifications\Messages\TelegramMessage;
use App\Services\TelegramService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramChannel
{
    public function __construct(protected TelegramService $telegramService)
    {
    }

    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @return array|null
     */
    public function send(mixed $notifiable, Notification $notification): ?array
    {
        if (!method_exists($notification, 'toTelegram')) {
            Log::warning('TelegramChannel: Notification ' . get_class($notification) . ' does not have a toTelegram() method.');
            return null;
        }

        try {
            /** @var TelegramMessage|string $message */
            $message = $notification->toTelegram($notifiable);

            // Determine target chat ID from notifiable if available
            $chatId = null;
            if (method_exists($notifiable, 'routeNotificationForTelegram')) {
                $chatId = $notifiable->routeNotificationForTelegram($notification);
            } elseif (isset($notifiable->telegram_chat_id)) {
                $chatId = $notifiable->telegram_chat_id;
            }

            if (is_string($message)) {
                return $this->telegramService->sendMessage($message, $chatId);
            }

            if ($message instanceof TelegramMessage) {
                return $message->send($this->telegramService, $chatId);
            }

            return null;
        } catch (Throwable $e) {
            Log::error('TelegramChannel send notification failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
