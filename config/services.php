<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | n8n Integration
    |--------------------------------------------------------------------------
    */
    
    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL', 'https://liftway.app.n8n.cloud/webhook'),
        'auth_token' => env('N8N_AUTH_TOKEN'),
        'api_key' => env('N8N_API_KEY'),
        'sender_webhook_url' => env('N8N_SENDER_WEBHOOK_URL'),
        'sender_auth_token' => env('N8N_SENDER_AUTH_TOKEN'),
        'parse_webhook_url' => env('N8N_PARSE_WEBHOOK_URL', env('N8N_WEBHOOK_URL', 'https://liftway.app.n8n.cloud/webhook') . '/parse-request'),
        'parse_auth_token' => env('N8N_PARSE_AUTH_TOKEN'),
        'system_user_id' => env('N8N_SYSTEM_USER_ID', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot
    |--------------------------------------------------------------------------
    */
    
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    ],

];
