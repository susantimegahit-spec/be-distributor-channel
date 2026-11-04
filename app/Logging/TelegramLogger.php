<?php

namespace App\Logging;

use Monolog\Level;
use Monolog\Logger;

class TelegramLogger
{
    /**
     * Create a custom Monolog instance for Telegram.
     *
     * @param array $config
     * @return Logger
     */
    public function __invoke(array $config): Logger
    {
        $level = $config['level'] ?? 'error';
        $chatId = $config['chat_id'] ?? null;

        $monologLevel = match (strtolower((string) $level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Error,
        };

        $handler = new TelegramLogHandler(
            level: $monologLevel,
            bubble: true,
            chatId: $chatId
        );

        return new Logger('telegram', [$handler]);
    }
}
