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

            $chatIds = is_array($chatId) ? $chatId : (!empty($chatId) ? [$chatId] : [null]);
            $responses = [];

            foreach ($chatIds as $targetId) {
                if (is_string($message)) {
                    $responses[] = $this->telegramService->sendMessage($message, $targetId);
                } elseif ($message instanceof TelegramMessage) {
                    $responses[] = $message->send($this->telegramService, $targetId);
                }
            }

            return count($responses) === 1 ? $responses[0] : ['ok' => true, 'results' => $responses];
        } catch (Throwable $e) {
            Log::error('TelegramChannel send notification failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
