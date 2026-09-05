<?php

namespace App\Logging;

use App\Services\TelegramService;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class TelegramLogHandler extends AbstractProcessingHandler
{
    protected ?string $chatId;
    protected TelegramService $telegramService;

    public function __construct(
        int|string|Level $level = Level::Error,
        bool $bubble = true,
        ?string $chatId = null
    ) {
        parent::__construct($level, $bubble);
        $this->chatId = $chatId;
        $this->telegramService = app(TelegramService::class);
    }

    /**
     * Writes the record down to the log of the implementing handler.
     *
     * @param LogRecord $record
     */
    protected function write(LogRecord $record): void
    {
        if (!$this->telegramService->isConfigured()) {
            return;
        }

        try {
            $levelName = strtolower($record->level->getName());
            $message = $record->message;
            $context = $record->context;

            // If context has an exception object, format it using sendException
            if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
                $this->telegramService->sendException(
                    $context['exception'],
                    $message !== $context['exception']->getMessage() ? $message : null,
                    $this->chatId
                );
                return;
            }

            // Otherwise send structured notification
            $fields = [];
            if (!empty($context)) {
                // Filter out non-serializable objects and limit keys
                $cleanContext = [];
                foreach (array_slice($context, 0, 8, true) as $k => $v) {
                    if (is_scalar($v) || is_null($v)) {
                        $cleanContext[$k] = $v;
                    } elseif (is_array($v)) {
                        $cleanContext[$k] = json_encode($v);
                    }
                }
                $fields = $cleanContext;
            }

            $badgeLevel = match ($levelName) {
                'debug', 'info', 'notice' => 'info',
                'warning' => 'warning',
                'error' => 'error',
                'critical', 'alert', 'emergency' => 'critical',
                default => 'error',
            };

            $this->telegramService->sendNotification(
                title: "Log Event [{$record->level->getName()}]",
                message: (string) $message,
                level: $badgeLevel,
                chatId: $this->chatId,
                fields: $fields ?: null
            );
        } catch (Throwable $e) {
            // Prevent logging handler from causing infinite recursion or breaking request
        }
    }
}
