<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramService
{
    protected string $botToken;
    protected string $apiUrl;
    protected string $defaultChatId;
    protected array $channels;
    protected int $timeout;
    protected string $defaultParseMode;
    protected bool $disablePreview;

    public function __construct()
    {
        $this->botToken = (string) config('telegram.bot_token', config('services.telegram.bot_token', ''));
        $this->apiUrl = rtrim((string) config('telegram.api_url', 'https://api.telegram.org'), '/');
        $this->defaultChatId = (string) config('telegram.chat_id', config('services.telegram.chat_id', ''));
        $this->channels = config('telegram.channels', []);
        $this->timeout = (int) config('telegram.timeout', 15);
        $this->defaultParseMode = (string) config('telegram.parse_mode', 'HTML');
        $this->disablePreview = (bool) config('telegram.disable_web_page_preview', true);
    }

    /**
     * Check if bot is configured with token and default chat ID.
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken);
    }

    /**
     * Resolve target chat ID based on input, channel key, or fallback default.
     */
    public function resolveChatId(?string $chatId = null, string $channelKey = 'default'): ?string
    {
        if (!empty($chatId)) {
            return (string) $chatId;
        }

        if (!empty($this->channels[$channelKey])) {
            return (string) $this->channels[$channelKey];
        }

        return !empty($this->defaultChatId) ? $this->defaultChatId : null;
    }

    /**
     * Send raw message to a Telegram chat.
     *
     * @param string $text
     * @param string|null $chatId
     * @param string|null $parseMode
     * @param array|null $replyMarkup (e.g. ['inline_keyboard' => [[['text' => 'Btn', 'url' => 'https://...']]]])
     * @param bool $disableNotification
     * @param int|null $replyToMessageId
     * @return array
     */
    public function sendMessage(
        string $text,
        ?string $chatId = null,
        ?string $parseMode = null,
        ?array $replyMarkup = null,
        bool $disableNotification = false,
        ?int $replyToMessageId = null
    ): array {
        $targetChatId = $this->resolveChatId($chatId);

        if (!$this->isConfigured()) {
            Log::warning('TelegramService: TELEGRAM_BOT_TOKEN is not configured.');
            return ['ok' => false, 'error' => 'TELEGRAM_BOT_TOKEN is not configured'];
        }

        if (empty($targetChatId)) {
            Log::warning('TelegramService: Target Chat ID is missing or not configured.');
            return ['ok' => false, 'error' => 'Target Chat ID is missing'];
        }

        // Truncate to Telegram maximum text length (4096 chars)
        $text = $this->truncateText($text, 4000);

        $payload = [
            'chat_id' => $targetChatId,
            'text' => $text,
            'parse_mode' => $parseMode ?? $this->defaultParseMode,
            'disable_web_page_preview' => $this->disablePreview,
            'disable_notification' => $disableNotification,
        ];

        if (!empty($replyMarkup)) {
            $payload['reply_markup'] = $replyMarkup;
        }

        if (!empty($replyToMessageId)) {
            $payload['reply_to_message_id'] = $replyToMessageId;
        }

        return $this->sendRequest('sendMessage', $payload);
    }

    /**
     * Send structured styled notification (Info, Success, Warning, Error, Critical).
     *
     * @param string $title
     * @param string $message
     * @param string $level (info|success|warning|error|danger|critical)
     * @param string|null $chatId
     * @param array|null $buttons List of buttons: [['text' => 'View', 'url' => '...']]
     * @param array|null $fields Key-value pairs of details e.g. ['Order No' => 'SO-001', 'Total' => 'Rp 500.000']
     * @return array
     */
    public function sendNotification(
        string $title,
        string $message,
        string $level = 'info',
        ?string $chatId = null,
        ?array $buttons = null,
        ?array $fields = null
    ): array {
        $level = strtolower($level);
        $badge = match ($level) {
            'success' => '✅ <b>SUCCESS</b>',
            'warning' => '⚠️ <b>WARNING</b>',
            'error', 'danger' => '🚨 <b>ERROR</b>',
            'critical' => '🔥 <b>CRITICAL ALERT</b>',
            default => 'ℹ️ <b>INFO</b>',
        };

        $appName = config('app.name', 'SMESTA');
        $env = strtoupper(config('app.env', 'local'));
        $time = now()->format('d-m-Y H:i:s T');

        $text = "{$badge} | <b>{$appName}</b> [<code>{$env}</code>]\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━\n";
        $text .= "📌 <b>" . $this->escapeHtml($title) . "</b>\n\n";
        $text .= $this->escapeHtml($message) . "\n";

        if (!empty($fields)) {
            $text .= "\n<b>📋 Detail:</b>\n";
            foreach ($fields as $key => $val) {
                $formattedVal = is_array($val) ? json_encode($val) : (string) $val;
                $text .= "• <b>" . $this->escapeHtml((string) $key) . ":</b> " . $this->escapeHtml($formattedVal) . "\n";
            }
        }

        $text .= "\n⏱ <i>{$time}</i>";

        $replyMarkup = null;
        if (!empty($buttons)) {
            $replyMarkup = $this->buildInlineKeyboard($buttons);
        }

        $channelKey = in_array($level, ['error', 'danger', 'critical']) ? 'error' : 'default';
        $targetChatId = $this->resolveChatId($chatId, $channelKey);

        return $this->sendMessage($text, $targetChatId, 'HTML', $replyMarkup);
    }

    /**
     * Send structured exception / crash alert to Telegram error channel.
     *
     * @param Throwable $exception
     * @param string|null $context
     * @param string|null $chatId
     * @return array
     */
    public function sendException(
        Throwable $exception,
        ?string $context = null,
        ?string $chatId = null
    ): array {
        $appName = config('app.name', 'SMESTA');
        $env = strtoupper(config('app.env', 'local'));
        $time = now()->format('d-m-Y H:i:s T');

        $exceptionClass = get_class($exception);
        $errorMessage = $exception->getMessage() ?: '(No message)';
        $file = $exception->getFile();
        $line = $exception->getLine();

        $text = "🚨 <b>SYSTEM EXCEPTION ALERT</b> | <b>{$appName}</b> [<code>{$env}</code>]\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━\n";
        
        if (!empty($context)) {
            $text .= "📍 <b>Context:</b> " . $this->escapeHtml($context) . "\n";
        }

        if (app()->runningInConsole()) {
            $text .= "💻 <b>Trigger:</b> CLI / Artisan Command\n";
        } elseif (request()) {
            $method = request()->method();
            $url = request()->fullUrl();
            $ip = request()->ip();
            $text .= "🌐 <b>Request:</b> <code>{$method}</code> " . $this->escapeHtml($url) . "\n";
            $text .= "👤 <b>Client IP:</b> <code>{$ip}</code>\n";
        }

        $text .= "⚠️ <b>Exception:</b> <code>{$exceptionClass}</code>\n";
        $text .= "💬 <b>Message:</b>\n<pre>" . $this->escapeHtml($this->truncateText($errorMessage, 500)) . "</pre>\n";
        $text .= "📁 <b>Location:</b> <code>{$file}:{$line}</code>\n\n";

        // Trace preview
        $traceLines = explode("\n", $exception->getTraceAsString());
        $tracePreview = implode("\n", array_slice($traceLines, 0, 5));
        $text .= "📜 <b>Trace Preview:</b>\n<pre>" . $this->escapeHtml($this->truncateText($tracePreview, 800)) . "</pre>\n";
        $text .= "\n⏱ <i>{$time}</i>";

        $targetChatId = $this->resolveChatId($chatId, 'error');

        return $this->sendMessage($text, $targetChatId, 'HTML');
    }

    /**
     * Send photo to Telegram chat.
     *
     * @param string|resource $photo (URL or local file path)
     * @param string|null $caption
     * @param string|null $chatId
     * @param string|null $parseMode
     * @param array|null $replyMarkup
     * @return array
     */
    public function sendPhoto(
        mixed $photo,
        ?string $caption = null,
        ?string $chatId = null,
        ?string $parseMode = null,
        ?array $replyMarkup = null
    ): array {
        $targetChatId = $this->resolveChatId($chatId);
        if (!$this->isConfigured() || empty($targetChatId)) {
            return ['ok' => false, 'error' => 'Bot or chat ID is not configured'];
        }

        $endpoint = "{$this->apiUrl}/bot{$this->botToken}/sendPhoto";

        try {
            $request = Http::timeout($this->timeout);

            $data = [
                'chat_id' => $targetChatId,
                'parse_mode' => $parseMode ?? $this->defaultParseMode,
            ];

            if (!empty($caption)) {
                $data['caption'] = $this->truncateText($caption, 1024);
            }

            if (!empty($replyMarkup)) {
                $data['reply_markup'] = json_encode($replyMarkup);
            }

            if (is_string($photo) && file_exists($photo)) {
                $response = $request->attach('photo', file_get_contents($photo), basename($photo))
                    ->post($endpoint, $data);
            } elseif (is_string($photo) && filter_var($photo, FILTER_VALIDATE_URL)) {
                $data['photo'] = $photo;
                $response = $request->asForm()->post($endpoint, $data);
            } else {
                $response = $request->attach('photo', $photo, 'photo.jpg')
                    ->post($endpoint, $data);
            }

            return $response->json() ?? ['ok' => $response->successful()];
        } catch (Throwable $e) {
            Log::error('TelegramService sendPhoto error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send document / file / PDF to Telegram chat.
     *
     * @param string|resource $document (URL or local file path or binary)
     * @param string|null $caption
     * @param string|null $chatId
     * @param string|null $filename
     * @param string|null $parseMode
     * @param array|null $replyMarkup
     * @return array
     */
    public function sendDocument(
        mixed $document,
        ?string $caption = null,
        ?string $chatId = null,
        ?string $filename = null,
        ?string $parseMode = null,
        ?array $replyMarkup = null
    ): array {
        $targetChatId = $this->resolveChatId($chatId);
        if (!$this->isConfigured() || empty($targetChatId)) {
            return ['ok' => false, 'error' => 'Bot or chat ID is not configured'];
        }

        $endpoint = "{$this->apiUrl}/bot{$this->botToken}/sendDocument";

        try {
            $request = Http::timeout($this->timeout);

            $data = [
                'chat_id' => $targetChatId,
                'parse_mode' => $parseMode ?? $this->defaultParseMode,
            ];

            if (!empty($caption)) {
                $data['caption'] = $this->truncateText($caption, 1024);
            }

            if (!empty($replyMarkup)) {
                $data['reply_markup'] = json_encode($replyMarkup);
            }

            if (is_string($document) && file_exists($document)) {
                $name = $filename ?: basename($document);
                $response = $request->attach('document', file_get_contents($document), $name)
                    ->post($endpoint, $data);
            } elseif (is_string($document) && filter_var($document, FILTER_VALIDATE_URL)) {
                $data['document'] = $document;
                $response = $request->asForm()->post($endpoint, $data);
            } else {
                $name = $filename ?: 'document.pdf';
                $response = $request->attach('document', $document, $name)
                    ->post($endpoint, $data);
            }

            return $response->json() ?? ['ok' => $response->successful()];
        } catch (Throwable $e) {
            Log::error('TelegramService sendDocument error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get Bot Identity information.
     */
    public function getMe(): array
    {
        return $this->sendRequest('getMe');
    }

    /**
     * Get recent updates from Telegram (useful for finding Chat IDs).
     */
    public function getUpdates(int $offset = 0, int $limit = 100): array
    {
        return $this->sendRequest('getUpdates', [
            'offset' => $offset,
            'limit'  => $limit,
            'timeout' => 0,
        ]);
    }

    /**
     * Set Webhook URL.
     */
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        $payload = [
            'url' => $url,
            'allowed_updates' => config('telegram.webhook.allowed_updates', ['message', 'edited_message', 'callback_query']),
        ];

        if (!empty($secretToken)) {
            $payload['secret_token'] = $secretToken;
        }

        return $this->sendRequest('setWebhook', $payload);
    }

    /**
     * Delete Webhook.
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): array
    {
        return $this->sendRequest('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates,
        ]);
    }

    /**
     * Get Webhook Info.
     */
    public function getWebhookInfo(): array
    {
        return $this->sendRequest('getWebhookInfo');
    }

    /**
     * Execute HTTP request to Telegram Bot API.
     */
    protected function sendRequest(string $method, array $params = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'error_code' => 401,
                'description' => 'TELEGRAM_BOT_TOKEN is not configured.',
            ];
        }

        $url = "{$this->apiUrl}/bot{$this->botToken}/{$method}";

        try {
            $response = Http::timeout($this->timeout)
                ->asJson()
                ->post($url, $params);

            $body = $response->json();

            if (!$response->successful() || (isset($body['ok']) && $body['ok'] === false)) {
                $desc = $body['description'] ?? $response->body();
                Log::warning("TelegramService API call [{$method}] failed: {$desc}");
                return $body ?? ['ok' => false, 'description' => $desc];
            }

            return $body ?? ['ok' => true];
        } catch (Throwable $e) {
            Log::error("TelegramService Exception on [{$method}]: " . $e->getMessage());
            return [
                'ok' => false,
                'description' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build standard Telegram Inline Keyboard structure from flat array.
     * Example input: [['text' => 'Open', 'url' => 'https://...'], ['text' => 'Approve', 'callback_data' => 'approve_1']]
     */
    public function buildInlineKeyboard(array $buttons): array
    {
        $keyboard = [];
        $row = [];

        foreach ($buttons as $btn) {
            if (isset($btn[0]) && is_array($btn[0])) {
                // Multi-row format already provided
                $keyboard[] = $btn;
                continue;
            }

            $buttonItem = ['text' => $btn['text'] ?? 'Button'];
            if (!empty($btn['url'])) {
                $buttonItem['url'] = $btn['url'];
            } elseif (!empty($btn['callback_data'])) {
                $buttonItem['callback_data'] = $btn['callback_data'];
            }

            $row[] = $buttonItem;
            if (count($row) >= 2) {
                $keyboard[] = $row;
                $row = [];
            }
        }

        if (!empty($row)) {
            $keyboard[] = $row;
        }

        return ['inline_keyboard' => $keyboard];
    }

    /**
     * Escape special HTML characters for Telegram HTML parse_mode.
     */
    public function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Truncate string to max length safely.
     */
    public function truncateText(string $text, int $max = 4000): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 15) . "\n...[truncated]";
    }
}
