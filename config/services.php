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
        'report_auth_token' => env('N8N_REPORT_AUTH_TOKEN', env('N8N_AUTH_TOKEN')),
        'system_user_id' => env('N8N_SYSTEM_USER_ID', 1),
    ],

    // Диспетчер рассылки (замена n8n «Send Emails»). По умолчанию выключен —
    // включается флагом после сверки и отключения n8n-воркфлоу.
    'email_dispatch' => [
        'enabled' => env('EMAILS_DISPATCH_ENABLED', false),
        // Сколько ошибок отправки подряд по ящику получателя → блокировка адреса
        // (письма ему больше не ставятся в очередь). Успех сбрасывает счётчик.
        'recipient_error_threshold' => (int) env('EMAILS_RECIPIENT_ERROR_THRESHOLD', 3),

        // Адаптивный пейсинг по получателю (to_email): чтобы не задолбить поставщика
        // пачкой. На каждом тике интервал между письмами одному получателю =
        // clamp(остаток_рабочего_окна / pending_получателю, MIN, MAX). Низкая
        // нагрузка → MAX (≈раз в час), выше → плавно чаще, но не ниже MIN.
        'recipient_interval_min_seconds' => (int) env('EMAILS_RECIPIENT_INTERVAL_MIN', 300),   // пол: 5 мин
        'recipient_interval_max_seconds' => (int) env('EMAILS_RECIPIENT_INTERVAL_MAX', 3600),  // потолок: 1 ч
        // Конец рабочего окна рассылки (час + таймзона) — горизонт, по которому
        // размазываем дневной объём. Совпадает с расписанием emails:dispatch-pending.
        'work_window_end_hour' => (int) env('EMAILS_WORK_WINDOW_END_HOUR', 20),
        'work_window_timezone' => env('EMAILS_WORK_WINDOW_TZ', 'Europe/Riga'),
    ],

    // Приём почты (замена n8n «Receive and Route Emails v3»). По умолчанию выключен —
    // включается флагом после сверки и отключения n8n-воркфлоу.
    'email_receive' => [
        'enabled' => env('EMAILS_RECEIVE_ENABLED', false),
        'per_mailbox_limit' => (int) env('EMAILS_RECEIVE_LIMIT', 20),
    ],

    // Переходный период: дублирование вложений входящих писем в Google Drive, чтобы
    // downstream-воркфлоу n8n «Process Email Conversations» (читает Drive-URL из
    // email_attachments.file_path) продолжал работать. По умолчанию выключено —
    // включается флагом ПОСЛЕ настройки OAuth2. См.
    // App\Services\Senders\GoogleDriveUploader.
    //   OAuth2 refresh-token пользователя liftway.ru (приложение типа Internal →
    //   refresh-token не протухает). folder_id — папка-приёмник (PQSFiles) в его Drive.
    'attachments_drive' => [
        'enabled' => env('ATTACHMENTS_DRIVE_ENABLED', false),
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'timeout' => (int) env('GOOGLE_DRIVE_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot
    |--------------------------------------------------------------------------
    */

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Validation Services
    |--------------------------------------------------------------------------
    */

    'neverbounce' => [
        'api_key' => env('NEVERBOUNCE_API_KEY'),
    ],

    'emaillistverify' => [
        'api_key' => env('EMAILLISTVERIFY_API_KEY'),
    ],

    'datavalidation' => [
        'api_key' => env('DATAVALIDATION_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Validation Provider
    |--------------------------------------------------------------------------
    | Провайдер для валидации email: neverbounce, emaillistverify, datavalidation
    | Если не указан, будет использоваться только базовая валидация
    */

    'email_validation_provider' => env('EMAIL_VALIDATION_PROVIDER', null),

    /*
     * Классификация API-заявок через OpenAI-совместимый прокси (ai.lazylift.ru).
     * Используется сервисом App\Services\Api\OpenAIClassifierClient.
     */
    'openai_classifier' => [
        'base_url' => env('OPENAI_CLASSIFIER_BASE_URL', 'https://ai.lazylift.ru/v1'),
        'api_key' => env('OPENAI_CLASSIFIER_API_KEY'),
        'proxy_key' => env('OPENAI_CLASSIFIER_PROXY_KEY'),
        'model_mini' => env('OPENAI_CLASSIFIER_MODEL_MINI', 'gpt-4o-mini'),
        'model_full' => env('OPENAI_CLASSIFIER_MODEL_FULL', 'gpt-4o'),
        'timeout' => (int) env('OPENAI_CLASSIFIER_TIMEOUT', 30),
    ],

    /*
     * Yandex Cloud Search API v2 (https://searchapi.api.cloud.yandex.net/v2/web/search).
     * Используется App\Services\Discovery\YandexSearchClient для поиска новых поставщиков.
     */
    'yandex_search' => [
        'endpoint' => env('YANDEX_SEARCH_ENDPOINT', 'https://searchapi.api.cloud.yandex.net/v2/web/search'),
        'api_key' => env('YANDEX_SEARCH_API_KEY'),
        'folder_id' => env('YANDEX_SEARCH_FOLDER_ID'),
        'results_per_query' => (int) env('YANDEX_SEARCH_RESULTS_PER_QUERY', 5),
        'timeout' => (int) env('YANDEX_SEARCH_TIMEOUT', 30),
    ],

];
