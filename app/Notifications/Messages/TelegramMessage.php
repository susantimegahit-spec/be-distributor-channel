<?php

namespace App\Notifications\Messages;

use App\Services\TelegramService;

class TelegramMessage
{
    protected ?string $toChatId = null;
    protected ?string $title = null;
    protected array $lines = [];
    protected array $fields = [];
    protected ?string $level = 'info';
    protected array $buttons = [];
    protected mixed $document = null;
    protected ?string $documentName = null;
    protected mixed $photo = null;
    protected ?string $caption = null;
    protected ?string $parseMode = 'HTML';
    protected bool $disableNotification = false;

    public static function create(?string $title = null): self
    {
        $instance = new self();
        if ($title) {
            $instance->title($title);
        }
        return $instance;
    }

    public function to(string $chatId): self
    {
        $this->toChatId = $chatId;
        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function line(string $line): self
    {
        $this->lines[] = $line;
        return $this;
    }

    public function lines(array $lines): self
    {
        foreach ($lines as $line) {
            $this->lines[] = (string) $line;
        }
        return $this;
    }

    public function field(string $label, mixed $value): self
    {
        $this->fields[$label] = $value;
        return $this;
    }

    public function fields(array $fields): self
    {
        foreach ($fields as $label => $val) {
            $this->fields[$label] = $val;
        }
        return $this;
    }

    public function level(string $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function success(): self
    {
        return $this->level('success');
    }

    public function warning(): self
    {
        return $this->level('warning');
    }

    public function error(): self
    {
        return $this->level('error');
    }

    public function critical(): self
    {
        return $this->level('critical');
    }

    public function info(): self
    {
        return $this->level('info');
    }

    public function button(string $text, string $url): self
    {
        $this->buttons[] = ['text' => $text, 'url' => $url];
        return $this;
    }

    public function callbackButton(string $text, string $callbackData): self
    {
        $this->buttons[] = ['text' => $text, 'callback_data' => $callbackData];
        return $this;
    }

    public function document(mixed $file, ?string $filename = null, ?string $caption = null): self
    {
        $this->document = $file;
        $this->documentName = $filename;
        if ($caption) {
            $this->caption = $caption;
        }
        return $this;
    }

    public function photo(mixed $photo, ?string $caption = null): self
    {
        $this->photo = $photo;
        if ($caption) {
            $this->caption = $caption;
        }
        return $this;
    }

    public function disableNotification(bool $disable = true): self
    {
        $this->disableNotification = $disable;
        return $this;
    }

    /**
     * Send via TelegramService.
     */
    public function send(TelegramService $telegramService, ?string $fallbackChatId = null): array
    {
        $targetChatId = $this->toChatId ?: $fallbackChatId;

        // 1. If photo is attached
        if ($this->photo !== null) {
            $caption = $this->caption ?: implode("\n", $this->lines);
            return $telegramService->sendPhoto($this->photo, $caption, $targetChatId);
        }

        // 2. If document is attached
        if ($this->document !== null) {
            $caption = $this->caption ?: implode("\n", $this->lines);
            return $telegramService->sendDocument($this->document, $caption, $targetChatId, $this->documentName);
        }

        // 3. Structured notification message
        $message = implode("\n", $this->lines);
        $title = $this->title ?: 'Notification';

        return $telegramService->sendNotification(
            title: $title,
            message: $message,
            level: $this->level ?? 'info',
            chatId: $targetChatId,
            buttons: $this->buttons ?: null,
            fields: $this->fields ?: null
        );
    }
}
