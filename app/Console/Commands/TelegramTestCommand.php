<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Exception;
use Illuminate\Console\Command;

class TelegramTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:test 
                            {message? : Custom test message content}
                            {--chat= : Target Chat ID (defaults to TELEGRAM_CHAT_ID in .env)}
                            {--type=info : Notification type (info|success|warning|error|critical)}
                            {--exception : Simulate and send a system exception alert}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test notification to Telegram to verify bot configuration';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        $this->info('🚀 Testing Telegram Bot configuration...');

        if (!$telegramService->isConfigured()) {
            $this->error('❌ TELEGRAM_BOT_TOKEN is not configured in your .env file.');
            $this->line('👉 Please set TELEGRAM_BOT_TOKEN=... in .env and try again.');
            return self::FAILURE;
        }

        // Test 1: Verify Bot Identity (getMe)
        $this->comment('🔍 Verifying Bot Token with Telegram API (getMe)...');
        $botInfo = $telegramService->getMe();

        if (empty($botInfo['ok'])) {
            $this->error('❌ Failed to authenticate with Telegram API.');
            $this->error('Reason: ' . ($botInfo['description'] ?? 'Unknown error'));
            return self::FAILURE;
        }

        $botData = $botInfo['result'] ?? [];
        $botName = $botData['first_name'] ?? 'Unknown';
        $botUsername = $botData['username'] ?? 'Unknown';
        $botId = $botData['id'] ?? 'Unknown';

        $this->info("✅ Bot Authenticated: {$botName} (@{$botUsername}) [ID: {$botId}]");

        $chatId = $this->option('chat');
        $resolvedChatId = $telegramService->resolveChatId($chatId);

        if (empty($resolvedChatId)) {
            $this->warn('⚠️ No Chat ID specified in --chat or TELEGRAM_CHAT_ID.');
            $this->line('💡 Tip: Run `php artisan telegram:get-updates` to see recent chats and find your Chat ID.');
            return self::FAILURE;
        }

        $this->comment("📤 Sending test notification to Chat ID [{$resolvedChatId}]...");

        if ($this->option('exception')) {
            $simulatedException = new Exception('This is a simulated test exception from `php artisan telegram:test --exception`.');
            $result = $telegramService->sendException(
                $simulatedException,
                'CLI Test Command',
                $resolvedChatId
            );
        } else {
            $type = $this->option('type') ?: 'info';
            $customMessage = $this->argument('message') ?: 'Halo! Ini adalah notifikasi pengujian dari backend SMESTA Distributor Channel.';

            $result = $telegramService->sendNotification(
                title: 'Test Notifikasi Telegram',
                message: $customMessage,
                level: $type,
                chatId: $resolvedChatId,
                buttons: [
                    ['text' => '🌐 Buka Dashboard', 'url' => config('app.url', 'http://localhost:8000')],
                ],
                fields: [
                    'Sistem' => config('app.name', 'SMESTA'),
                    'Environment' => config('app.env', 'local'),
                    'Waktu' => now()->toDateTimeString(),
                    'Tipe' => ucfirst($type),
                ]
            );
        }

        if (!empty($result['ok'])) {
            $this->info('🎉 Notification sent successfully to Telegram!');
            return self::SUCCESS;
        }

        $this->error('❌ Failed to send notification.');
        $this->error('Telegram Response: ' . ($result['description'] ?? json_encode($result)));
        return self::FAILURE;
    }
}
