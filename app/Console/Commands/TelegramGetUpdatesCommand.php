<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TelegramGetUpdatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:get-updates {--limit=20 : Maximum number of updates to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch recent Telegram updates to easily find your Chat ID or Group ID';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        $this->info('📡 Fetching recent updates from Telegram Bot API...');

        if (!$telegramService->isConfigured()) {
            $this->error('❌ TELEGRAM_BOT_TOKEN is not configured in your .env file.');
            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $response = $telegramService->getUpdates(limit: $limit);

        if (empty($response['ok'])) {
            $this->error('❌ Failed to get updates from Telegram.');
            $this->error('Reason: ' . ($response['description'] ?? 'Unknown error'));
            return self::FAILURE;
        }

        $updates = $response['result'] ?? [];

        if (empty($updates)) {
            $this->warn('ℹ️ No recent messages found.');
            $this->line('');
            $this->line('👉 To find your Chat ID:');
            $this->line('  1. Open your Telegram App.');
            $this->line('  2. Search for your bot and click [Start] or send any message (e.g. "halo bot").');
            $this->line('  3. If using a Group, add your bot to the group and send a message.');
            $this->line('  4. Re-run this command: `php artisan telegram:get-updates`');
            return self::SUCCESS;
        }

        $rows = [];
        $uniqueChats = [];

        foreach ($updates as $update) {
            $updateId = $update['update_id'] ?? '-';
            $msg = $update['message'] ?? $update['edited_message'] ?? $update['my_chat_member'] ?? $update['channel_post'] ?? [];
            
            $chat = $msg['chat'] ?? [];
            $from = $msg['from'] ?? [];

            $chatId = $chat['id'] ?? '-';
            $chatType = $chat['type'] ?? 'unknown';
            $chatTitle = $chat['title'] ?? ($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '');
            $username = !empty($chat['username']) ? '@' . $chat['username'] : (!empty($from['username']) ? '@' . $from['username'] : '-');
            $text = $msg['text'] ?? ($msg['caption'] ?? '(Non-text event)');
            $date = !empty($msg['date']) ? Carbon::createFromTimestamp($msg['date'])->toDateTimeString() : '-';

            if (!empty($chatId) && !isset($uniqueChats[$chatId])) {
                $uniqueChats[$chatId] = [
                    'id' => $chatId,
                    'type' => $chatType,
                    'title' => trim($chatTitle) ?: 'Unknown',
                    'username' => $username,
                ];
            }

            $rows[] = [
                $updateId,
                $date,
                $chatId,
                $chatType,
                trim($chatTitle) ?: '-',
                $username,
                mb_strimwidth($text, 0, 30, '...'),
            ];
        }

        $this->table(
            ['Update ID', 'Date/Time', 'Chat ID', 'Type', 'Chat/Sender Name', 'Username', 'Message'],
            $rows
        );

        $this->line('');
        $this->info('🔑 Detected Unique Chat IDs:');
        foreach ($uniqueChats as $c) {
            $this->line("  • Chat ID: <fg=green;options=bold>{$c['id']}</> | Type: [{$c['type']}] | Name: {$c['title']} ({$c['username']})");
        }

        $this->line('');
        $this->comment('👉 Copy the Chat ID above and set it in your .env file:');
        $this->line('   TELEGRAM_CHAT_ID=' . (array_key_first($uniqueChats) ?? ''));
        $this->line('');

        return self::SUCCESS;
    }
}
