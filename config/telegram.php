<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Credentials & Defaults
    |--------------------------------------------------------------------------
    |
    | Bot token obtained from Telegram @BotFather and default chat ID / channel
    | for sending application alerts and general system notifications.
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    'chat_id' => env('TELEGRAM_CHAT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Dedicated Telegram Chat / Channel IDs
    |--------------------------------------------------------------------------
    |
    | You can route different categories of notifications to different groups/chats.
    |
    */

    'channels' => [
        'default'   => env('TELEGRAM_CHAT_ID', ''),
        'error'     => env('TELEGRAM_ERROR_CHAT_ID', env('TELEGRAM_CHAT_ID', '')),
        'report'    => env('TELEGRAM_REPORT_CHAT_ID', env('TELEGRAM_CHAT_ID', '')),
        'approval'  => env('TELEGRAM_APPROVAL_CHAT_ID', env('TELEGRAM_CHAT_ID', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram API Base URL & Request Settings
    |--------------------------------------------------------------------------
    */

    'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),

    'timeout' => env('TELEGRAM_TIMEOUT', 15),

    'parse_mode' => env('TELEGRAM_PARSE_MODE', 'HTML'), // 'HTML' or 'MarkdownV2' or 'Markdown'

    'disable_web_page_preview' => env('TELEGRAM_DISABLE_PREVIEW', true),

    'async' => env('TELEGRAM_ASYNC', false),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */

    'webhook' => [
        'url' => env('TELEGRAM_WEBHOOK_URL', ''),
        'secret_token' => env('TELEGRAM_WEBHOOK_SECRET', ''),
        'allowed_updates' => ['message', 'edited_message', 'callback_query'],
    ],

];
