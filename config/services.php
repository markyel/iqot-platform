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

        // ГЛОБАЛЬНЫЙ минимальный интервал (сек) между ЛЮБЫМИ двумя отправками всей
        // платформы — анти-бан для SMTP-хоста. beget банит IP за параллельность/частоту
        // с одного адреса; через прокси весь трафик идёт с одного IP, поэтому темп
        // ограничиваем глобально (а не только per-sender). n8n слал в один поток с
        // паузой ~2с — это безопасный профиль. 0 = выключено.
        'global_min_interval_seconds' => (int) env('EMAILS_GLOBAL_MIN_INTERVAL', 2),

        // Dual-path SMTP: слать половину писем напрямую (IP прода → beget), половину
        // через прокси (45.146.167.20) — два source-IP, суммарный темп ~2x. Каждый канал
        // со своим gap-гейтом. По умолчанию выключено.
        'dual_smtp_enabled' => (bool) env('EMAILS_SMTP_DUAL_ENABLED', false),
        'direct_smtp_host' => env('EMAILS_DIRECT_SMTP_HOST', '185.78.30.58'),

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

    // Двухэтапный таргетинг рассылки (#4): перед отправкой ищем позиции батча в
    // Яндексе (1 запрос на позицию, много результатов), делим пул на A (сайт нашёлся
    // → письмо со ссылками-намёками) и B (как раньше). Новые домены → discovery.
    'email_pretarget' => [
        'enabled' => (bool) env('EMAILS_PRETARGET_ENABLED', false),
        'results_per_query' => (int) env('EMAILS_PRETARGET_RESULTS', 20),
        'max_items_per_batch' => (int) env('EMAILS_PRETARGET_MAX_ITEMS', 10),
        'discovery_enabled' => (bool) env('EMAILS_PRETARGET_DISCOVERY', true),
        // Дневной потолок Яндекс-запросов (анти-перерасход квоты/денег). 0 = без лимита.
        'daily_query_cap' => (int) env('EMAILS_PRETARGET_DAILY_CAP', 2000),
    ],

    // Реакция на ОТПИСКУ поставщика (AI-сигнал unsubscribe в ответе, см.
    // SupplierUnsubscribeEscalator). 1-я отписка → пауза + увеличенный личный интервал;
    // повторная (>= disable_threshold) → отключение от рассылки. enabled=false — только
    // детект/лог без действий.
    'email_unsubscribe' => [
        'enabled' => (bool) env('EMAILS_UNSUBSCRIBE_ENABLED', true),
        'pause_days' => (int) env('EMAILS_UNSUBSCRIBE_PAUSE_DAYS', 7),
        'escalated_interval_seconds' => (int) env('EMAILS_UNSUBSCRIBE_INTERVAL', 604800), // 7 дней
        'disable_threshold' => (int) env('EMAILS_UNSUBSCRIBE_DISABLE_THRESHOLD', 2),
    ],

    // Приём почты (замена n8n «Receive and Route Emails v3»). По умолчанию выключен —
    // включается флагом после сверки и отключения n8n-воркфлоу.
    'email_receive' => [
        'enabled' => env('EMAILS_RECEIVE_ENABLED', false),
        'per_mailbox_limit' => (int) env('EMAILS_RECEIVE_LIMIT', 20),
    ],

    // AI-анализ ответов поставщиков (замена n8n «Process Email Conversations»).
    // Берёт необработанные входящие письма, прогоняет тело + вложения (КП) через AI,
    // извлекает офферы/вопросы и пишет в request_item_responses / *_multi_responses /
    // supplier_questions, обновляет статус беседы. По умолчанию ВЫКЛЮЧЕН — включать
    // только ПОСЛЕ отключения n8n-воркфлоу (вставки multi/questions не идемпотентны,
    // параллельная работа двух систем плодит дубли).
    'email_analysis' => [
        'enabled' => (bool) env('EMAILS_ANALYZE_ENABLED', false),
        // Отдельный ключ модели (промпт большой → берём полноценную модель, не mini).
        'model' => env('EMAILS_ANALYSIS_MODEL', 'gpt-4o'),
        // Таймаут запроса к AI: промпт+документы большие, дефолтных 30с мало.
        'timeout' => (int) env('EMAILS_ANALYSIS_TIMEOUT', 120),
        // Потолок токенов ответа (офферов/вопросов может быть много).
        'max_tokens' => (int) env('EMAILS_ANALYSIS_MAX_TOKENS', 4096),
        // Сколько писем за тик ставим в очередь анализа.
        'batch_limit' => (int) env('EMAILS_ANALYZE_BATCH_LIMIT', 50),
        // Лимит текста вложений в промпте (начало+конец, если длиннее).
        'doc_max_chars' => (int) env('EMAILS_ANALYZE_DOC_MAX_CHARS', 30000),
        // 2-шаговый веб-сёрфинг вместо Tavily: если AI вернул fetch_urls и цен нет —
        // грузим страницы и делаем второй прогон AI с их содержимым.
        'fetch_urls' => (bool) env('EMAILS_ANALYZE_FETCH_URLS', true),
        'fetch_max' => (int) env('EMAILS_ANALYZE_FETCH_MAX', 3),       // макс. ссылок за письмо
        'fetch_chars' => (int) env('EMAILS_ANALYZE_FETCH_CHARS', 8000), // лимит текста со страницы
        'fetch_timeout' => (int) env('EMAILS_ANALYZE_FETCH_TIMEOUT', 15),
        // Headless-рендер (Chromium) как fallback к HTTP: сайты с JS-антиботом
        // (Beget: заглушка set_cookie()+reload()) отдают цены только после запуска
        // браузера. Если HTTP вернул короткий огрызок (< http_min_chars) — рендерим.
        'headless_enabled' => (bool) env('EMAILS_ANALYZE_HEADLESS', true),
        'headless_chrome_path' => env('EMAILS_ANALYZE_CHROME_PATH', '/usr/bin/google-chrome-stable'),
        // Писчий HOME для Chrome (воркеры под www-data, чей /var/www не пишется).
        'headless_home' => env('EMAILS_ANALYZE_HEADLESS_HOME', storage_path('app/headless')),
        'headless_timeout' => (int) env('EMAILS_ANALYZE_HEADLESS_TIMEOUT', 30),
        // Порог «огрызка»: HTTP-текст короче → пробуем headless.
        'http_min_chars' => (int) env('EMAILS_ANALYZE_HTTP_MIN_CHARS', 200),
    ],

    // Триаж вопросов поставщиков (замена n8n «Process Supplier Questions»).
    // Берёт supplier_questions.status='pending', через AI решает можно ли ответить
    // автоматически: ДА → формирует письмо-ответ в outgoing_replies (status='pending',
    // отправку делает ОТДЕЛЬНЫЙ воркфлоу/процесс), НЕТ → дедуплицирует через
    // question_consolidation и направляет автору в author_questions. По умолчанию
    // ВЫКЛЮЧЕН — включать только ПОСЛЕ отключения n8n-воркфлоу (вставки в
    // author_questions / question_consolidation / outgoing_replies не идемпотентны,
    // параллельная работа двух систем плодит дубли).
    'email_questions' => [
        'enabled' => (bool) env('EMAILS_QUESTIONS_ENABLED', false),
        // Авто-закрытие зависших вопросов к автору (команда emails:auto-close-questions).
        'autoclose_enabled' => (bool) env('EMAILS_AUTOCLOSE_ENABLED', false),
        // Модель AI (промпт классификации компактный → дефолт mini).
        'model' => env('EMAILS_QUESTIONS_MODEL', 'gpt-4o-mini'),
        // Таймаут запроса к AI.
        'timeout' => (int) env('EMAILS_QUESTIONS_TIMEOUT', 60),
        // Потолок токенов ответа.
        'max_tokens' => (int) env('EMAILS_QUESTIONS_MAX_TOKENS', 1024),
        // Сколько вопросов за тик ставим в очередь.
        'batch_limit' => (int) env('EMAILS_QUESTIONS_BATCH_LIMIT', 10),
        // Сколько прошлых ответов автора по этой заявке подмешиваем в промпт.
        'history_limit' => (int) env('EMAILS_QUESTIONS_HISTORY_LIMIT', 15),
    ],

    // Отправка готовых ответов поставщикам (замена n8n «Send Outgoing Replies»).
    // Берёт outgoing_replies.status='pending' (их создаёт триаж emails:process-questions),
    // шлёт через SMTP отправителя (Symfony Mailer, как массовая рассылка) с заголовками
    // threading (In-Reply-To/References), на успехе пишет email_messages
    // (direction='outgoing') + status='sent'. По умолчанию ВЫКЛЮЧЕН — включать только
    // ПОСЛЕ отключения n8n-воркфлоу «Send Outgoing Replies» (иначе двойная отправка).
    'email_replies' => [
        'enabled' => (bool) env('EMAILS_REPLIES_ENABLED', false),
        // Сколько готовых ответов за тик ставим в очередь отправки.
        'batch_limit' => (int) env('EMAILS_REPLIES_BATCH_LIMIT', 30),
        // Сколько раз ретраить транзиентную ошибку коннекта (битый IP round-robin
        // smtp.beget.com) до перевода ответа в 'failed'. Зеркало email_queue.max_retries.
        'max_retries' => (int) env('EMAILS_REPLIES_MAX_RETRIES', 3),
    ],

    // Разбор неопознанных писем (замена n8n «Process Unidentified Emails v4»). Второй
    // проход по unidentified_emails (status='pending', reason!='bounce'): мягкий матч
    // токена (полный + базовая часть до дефиса), сбор кандидат-батчей по домену
    // поставщика, AI-идентификация по НАЗВАНИЮ товара. При успехе письмо мигрирует в
    // боевую беседу (email_messages/email_attachments, email_queue='replied') и далее
    // подхватывается AI-анализом. По умолчанию ВЫКЛЮЧЕН — включать только ПОСЛЕ
    // отключения n8n-воркфлоу (миграция email_messages деду­плицируется по message_id,
    // но email_attachments — нет; параллельная работа двух систем плодит дубли вложений).
    'email_identify' => [
        'enabled' => (bool) env('EMAILS_IDENTIFY_ENABLED', false),
        // Модель AI (n8n брал gpt-3.5-turbo; сопоставление по названию сложное → gpt-4o).
        'model' => env('EMAILS_IDENTIFY_MODEL', 'gpt-4o'),
        'timeout' => (int) env('EMAILS_IDENTIFY_TIMEOUT', 120),
        'max_tokens' => (int) env('EMAILS_IDENTIFY_MAX_TOKENS', 1024),
        // Сколько писем за тик ставим в очередь identify.
        'batch_limit' => (int) env('EMAILS_IDENTIFY_BATCH_LIMIT', 50),
        // Потолок попыток (порт «processing_attempts < 5»): больше — manual_review.
        'max_attempts' => (int) env('EMAILS_IDENTIFY_MAX_ATTEMPTS', 5),
        // Окно поиска отправленных писем для токенов/кандидатов (дней).
        'lookback_days' => (int) env('EMAILS_IDENTIFY_LOOKBACK_DAYS', 60),
        // Лимит кандидат-батчей в промпте.
        'candidate_limit' => (int) env('EMAILS_IDENTIFY_CANDIDATE_LIMIT', 50),
        // Минимальная уверенность AI для идентификации (порт «confidence >= 0.5»).
        'min_confidence' => (float) env('EMAILS_IDENTIFY_MIN_CONFIDENCE', 0.5),
        // Лимит текста вложений (КП) в промпте (начало+конец, если длиннее).
        'doc_max_chars' => (int) env('EMAILS_IDENTIFY_DOC_MAX_CHARS', 30000),
    ],

    // Генерация рассылок (порт n8n «Create Email Queue v4 (AI)»): собирает заявки,
    // бьёт позиции на батчи (≤5), подбирает профильных поставщиков, назначает ящик-
    // отправитель, AI-генерит тело письма и трекинг-токен, рендерит уникальный HTML на
    // каждого поставщика и пишет строки в email_queue (pending) для DispatchPendingEmails.
    //   АНТИ-ФИНГЕРПРИНТИНГ: стиль письма (template/tone/token) привязан к sender'у и
    //   стабилен из рассылки в рассылку → письма от РАЗНЫХ отправителей не похожи. Нельзя
    //   вводить единый генератор тела/токена/вёрстки — всё рулится назначением per-sender.
    //   ИДЕМПОТЕНТНОСТЬ: гарантируется claim'ом заявок (draft/new/active→queued_for_sending)
    //   в начале команды; email_batches/email_queue INSERT'ы НЕ идемпотентны.
    //   Флаг по умолчанию OFF — включать ТОЛЬКО после отключения n8n «Create Email Queue v4».
    'email_generate' => [
        'enabled' => (bool) env('EMAILS_GENERATE_ENABLED', false),
        // Тело письма: выше качеством (1 AI-вызов на батч, не на поставщика → стоимость ок).
        'body_model' => env('EMAILS_GENERATE_BODY_MODEL', 'gpt-4o'),
        // Температура тела — уникальность формулировок.
        'body_temperature' => (float) env('EMAILS_GENERATE_BODY_TEMP', 0.7),
        // Токен генерится AI по стилю sender'а (token_templates.prompt_template).
        'token_model' => env('EMAILS_GENERATE_TOKEN_MODEL', 'gpt-4o-mini'),
        'token_use_ai' => (bool) env('EMAILS_GENERATE_TOKEN_USE_AI', true),
        'timeout' => (int) env('EMAILS_GENERATE_TIMEOUT', 60),
        'max_tokens' => (int) env('EMAILS_GENERATE_MAX_TOKENS', 1500),
        // Заявок за тик (n8n брал LIMIT 20).
        'request_limit' => (int) env('EMAILS_GENERATE_REQUEST_LIMIT', 20),
        // Позиций на батч (n8n MAX 5).
        'items_per_batch' => (int) env('EMAILS_GENERATE_ITEMS_PER_BATCH', 5),
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
