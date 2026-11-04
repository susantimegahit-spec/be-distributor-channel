<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:webhook 
                            {action=info : Action to execute (info|set|delete)}
                            {--url= : Webhook URL (required when action is set)}
                            {--secret= : Optional secret token for webhook header verification}
                            {--drop-pending : Drop pending updates when deleting webhook}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage Telegram Bot Webhook (get info, register, or delete)';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        if (!$telegramService->isConfigured()) {
            $this->error('❌ TELEGRAM_BOT_TOKEN is not configured in your .env file.');
            return self::FAILURE;
        }

        $action = strtolower($this->argument('action'));

        return match ($action) {
            'info' => $this->showWebhookInfo($telegramService),
            'set' => $this->setWebhook($telegramService),
            'delete' => $this->deleteWebhook($telegramService),
            default => $this->invalidAction($action),
        };
    }

    protected function showWebhookInfo(TelegramService $telegramService): int
    {
        $this->info('📡 Fetching Telegram Webhook Info...');
        $response = $telegramService->getWebhookInfo();

        if (empty($response['ok'])) {
            $this->error('❌ Failed to get webhook info: ' . ($response['description'] ?? 'Unknown error'));
            return self::FAILURE;
        }

        $result = $response['result'] ?? [];
        $url = $result['url'] ?? '';

        $this->table(
            ['Parameter', 'Value'],
            [
                ['Webhook URL', !empty($url) ? $url : '(Not set - using getUpdates polling)'],
                ['Has Custom Certificate', ($result['has_custom_certificate'] ?? false) ? 'Yes' : 'No'],
                ['Pending Update Count', (string) ($result['pending_update_count'] ?? 0)],
                ['Last Error Date', isset($result['last_error_date']) ? date('Y-m-d H:i:s', $result['last_error_date']) : '-'],
                ['Last Error Message', $result['last_error_message'] ?? '-'],
                ['Max Connections', (string) ($result['max_connections'] ?? '-')],
            ]
        );

        return self::SUCCESS;
    }

    protected function setWebhook(TelegramService $telegramService): int
    {
        $url = $this->option('url') ?: config('telegram.webhook.url');

        if (empty($url)) {
            $this->error('❌ Webhook URL is required. Provide via --url="https://..." or TELEGRAM_WEBHOOK_URL in .env');
            return self::FAILURE;
        }

        $secret = $this->option('secret') ?: config('telegram.webhook.secret_token');
        $this->comment("🔗 Setting Webhook to: {$url}...");

        $response = $telegramService->setWebhook($url, $secret);

        if (!empty($response['ok'])) {
            $this->info('✅ Webhook successfully set!');
            $this->line('Description: ' . ($response['description'] ?? 'Webhook was set'));
            return self::SUCCESS;
        }

        $this->error('❌ Failed to set webhook: ' . ($response['description'] ?? 'Unknown error'));
        return self::FAILURE;
    }

    protected function deleteWebhook(TelegramService $telegramService): int
    {
        $dropPending = (bool) $this->option('drop-pending');
        $this->comment('🗑️ Deleting Webhook...');

        $response = $telegramService->deleteWebhook($dropPending);

        if (!empty($response['ok'])) {
            $this->info('✅ Webhook successfully removed!');
            return self::SUCCESS;
        }

        $this->error('❌ Failed to delete webhook: ' . ($response['description'] ?? 'Unknown error'));
        return self::FAILURE;
    }

    protected function invalidAction(string $action): int
    {
        $this->error("❌ Invalid action [{$action}]. Available actions: info, set, delete.");
        return self::FAILURE;
    }
}
