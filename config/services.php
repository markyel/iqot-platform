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

        // (устар.) Абсолютный порог spam_reject_count для отключения из IncomingEmailRouter.
        // Больше НЕ используется для отключения — оно вынесено в emails:spam-reject-guard
        // (по ДОЛЕ за окно, корректная атрибуция). Счётчик spam_reject_count теперь просто
        // копится (для видимости), а гасит/возвращает ящики гвард ниже (email_spam_guard).
        'sender_spam_threshold' => (int) env('EMAILS_SENDER_SPAM_THRESHOLD', 5),

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

        // ЗАЩИТА БОЕВОГО IP: слать только через релеи. Отправитель с smtp_server НЕ из
        // whitelist relay_hosts коннектится напрямую с основного IP прода — блокируем на
        // отправке (письмо/ответ). Whitelist = провайдеры, для которых поднят релей
        // (сейчас только beget через /etc/hosts→релей + каналы). Пополнять при добавлении
        // релей-прокси для нового провайдера.
        'relay_only' => (bool) env('EMAILS_RELAY_ONLY', true),
        'relay_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('EMAILS_RELAY_HOSTS', 'smtp.beget.com'))))),

        // ГЕНЕРИК-ТРАНСПORT ЧЕРЕЗ МИКРОСЕРВИС РЕЛЕЯ (universal-email-service, FastAPI :8000).
        // Вместо Symfony Mailer → socat (per-domain туннель) шлём HTTP POST /send на релей,
        // передавая smtp_server/креды ящика + тело/вложения; SMTP наружу делает релей с
        // СВОИМ IP (боевой IP не светится, ноль per-domain настройки). За флагом.
        //   via_microservice — мастер-флаг (по умолч. off → текущий socat-путь).
        //   microservice_urls — ПУЛ релеев (JSON), напр.
        //     [{"url":"http://45.146.167.20:8000","weight":1},{"url":"http://155.212.185.101:8000","weight":1}]
        //     (можно и CSV/массив строк). RelayHttpMailer раскладывает отправку per-send по
        //     всему пулу (по id письма/ответа, взвешенно) + failover на СЛЕДУЮЩИЙ релей при
        //     недоступности узла (только connect-ошибка релея, НЕ SMTP-ошибка). ПРОВАЙДЕР-
        //     НЕЗАВИСИМ: релей коннектится к smtp_server:port ЛЮБОГО провайдера (beget,
        //     wwwsend/sprinthost и т.д.) — per-domain socat больше не нужен. verify_cert по
        //     провайдеру (false для не-beget: общий/несовпадающий сертификат).
        //   microservice_url — одиночный релей (fallback, если microservice_urls пуст).
        //   microservice_api_key — X-API-Key (совпадает с API_KEY в .env релеев).
        //   microservice_sender_ids — БЕЛЫЙ СПИСОК sender_id для обкатки (пусто → все beget-ящики).
        //     На тест ставим один id; убедились — очищаем, чтобы шли все.
        //   microservice_timeout — таймаут HTTP-запроса к релею (сек).
        'via_microservice' => (bool) env('EMAILS_SEND_VIA_MICROSERVICE', false),
        'microservice_urls' => json_decode((string) env('EMAILS_MICROSERVICE_URLS', '[]'), true) ?: [],
        'microservice_url' => rtrim((string) env('EMAILS_MICROSERVICE_URL', ''), '/'),
        'microservice_api_key' => (string) env('EMAILS_MICROSERVICE_API_KEY', ''),
        'microservice_sender_ids' => array_values(array_filter(array_map(
            'intval',
            array_filter(array_map('trim', explode(',', (string) env('EMAILS_MICROSERVICE_SENDER_IDS', ''))), 'strlen')
        ))),
        'microservice_timeout' => (int) env('EMAILS_MICROSERVICE_TIMEOUT', 60),

        // Адаптивный пейсинг по получателю (to_email): чтобы не задолбить поставщика
        // пачкой. На каждом тике интервал между письмами одному получателю =
        // clamp(остаток_рабочего_окна / pending_получателю, MIN, MAX). Низкая
        // нагрузка → MAX (≈раз в час), выше → плавно чаще, но не ниже MIN.
        'recipient_interval_min_seconds' => (int) env('EMAILS_RECIPIENT_INTERVAL_MIN', 300),   // пол: 5 мин
        'recipient_interval_max_seconds' => (int) env('EMAILS_RECIPIENT_INTERVAL_MAX', 3600),  // потолок: 1 ч
        // Дневной потолок писем на ОДНОГО получателя ЧЕРЕЗ ВСЕ наши ящики (анти-FBL:
        // маркетплейс-адреса типа tiu.ru матчатся под много категорий и их добивали
        // 100+/день с 80+ ящиков → спам-жалоба → ожог доменов). n8n держал ≤19/день.
        // Это БАЗА; per-адрес cap адаптируется (recipient_mailboxes.daily_cap) командой
        // emails:recompute-recipient-caps. День — по МСК (локальный день получателя). 0 = выкл.
        'recipient_daily_cap' => (int) env('EMAILS_RECIPIENT_DAILY_CAP', 10),
        // Границы и шаг адаптации per-адрес cap (по вовлечённости).
        'recipient_cap_max' => (int) env('EMAILS_RECIPIENT_CAP_MAX', 15),        // ответил/офферы → к max
        'recipient_cap_min' => (int) env('EMAILS_RECIPIENT_CAP_MIN', 5),         // нет реакции/баунсы → к min
        'recipient_cap_step' => (int) env('EMAILS_RECIPIENT_CAP_STEP', 2),       // постепенно, шаг/день
        'recipient_cap_cold_sends' => (int) env('EMAILS_RECIPIENT_CAP_COLD_SENDS', 12), // столько писем без ответа → снижаем
        // Конец рабочего окна рассылки (час + таймзона) — горизонт, по которому
        // размазываем дневной объём. Совпадает с расписанием emails:dispatch-pending.
        'work_window_end_hour' => (int) env('EMAILS_WORK_WINDOW_END_HOUR', 20),
        'work_window_timezone' => env('EMAILS_WORK_WINDOW_TZ', 'Europe/Riga'),
        // Доля ОСТАТКА окна, под которую планируем размазывание (0..1, дефолт 0.5).
        // <1 резервирует часть окна под НОВЫЕ заявки дня → выше утренний темп, ровнее пила.
        'work_window_fraction' => (float) env('EMAILS_WORK_WINDOW_FRACTION', 0.5),
    ],

    // Адаптивный двухволновой пул: если подобранный пул > порога — волна 1 шлёт
    // ужесточённому поднабору (явная привязка к типу + ранжир по confidence/rating до
    // порога), остаток откладывается в «пул расширения». Через followup_delay_days, если
    // ответов по заявке < followup_min_responses, волна 2 досылает по пулу расширения.
    'email_pool' => [
        'wave1_threshold' => (int) env('EMAILS_POOL_WAVE1_THRESHOLD', 150),
        'followup_enabled' => (bool) env('EMAILS_POOL_FOLLOWUP_ENABLED', true),
        'followup_delay_days' => (int) env('EMAILS_POOL_FOLLOWUP_DELAY_DAYS', 2),
        'followup_min_responses' => (int) env('EMAILS_POOL_FOLLOWUP_MIN_RESPONSES', 3),

        // ВОЛНЫ V2 (мастер-флаг, по умолч. off → текущее деление по размеру пула). ON:
        // деление на 3 волны по «температуре» Яндекс-матча вместо размера пула —
        //   В1 (tier1) сразу: поставщик совпал по ОСНОВНОМУ запросу (бренд+артикул+название);
        //   В2 (tier2) +1 день: совпал ТОЛЬКО по облегчённым запросам (артикул/название);
        //   В3 (tier3) held: не совпал ни по одному → холодная, релиз при КП<4 (followup).
        // Флаг также включает облегчённые Яндекс-запросы в SupplierTargetingService.
        'waves_v2' => (bool) env('EMAILS_WAVES_V2', false),
        // Метрика холодной В3 (followup): порог полученных КП по заявке (< → шлём В3).
        // LEGACY-батч-уровневый счёт разных поставщиков (используется, только если
        // wave3_min_offers_per_item/covered_fraction не заданы — см. ПОЗИЦИОННЫЙ критерий ниже).
        'wave3_min_offers' => (int) env('EMAILS_WAVE3_MIN_OFFERS', 4),
        // ПОЗИЦИОННОЕ покрытие холодной В3 (followup): «достаточно КП» = у ДОЛИ позиций
        // батча (>= wave3_min_covered_fraction) есть >= wave3_min_offers_per_item ценовых
        // ответов. Иначе одна вечно-дефицитная позиция не должна держать пул — потому доля,
        // а не «все позиции». Гейт покрытия ⇒ отменяем В3, недобор ⇒ отпускаем В3.
        'wave3_min_offers_per_item' => (int) env('EMAILS_WAVE3_MIN_OFFERS_PER_ITEM', 1),
        'wave3_min_covered_fraction' => (float) env('EMAILS_WAVE3_MIN_COVERED_FRACTION', 0.8),
        // Задержка (дни) перед отправкой тёплой В2 (tier2) относительно генерации.
        'wave2_delay_days' => (int) env('EMAILS_WAVE2_DELAY_DAYS', 1),
        // Волна 2 «добор пула»: по пул-поставщикам НЕ из волны 1 делаем Яндекс-запрос
        // `<термин позиций> (site:d1 | … | site:dK)` — за 1 запрос находим у кого из пула
        // есть страница под контекст заявки (ссылку цитируем, но текст — ПОЗИЦИЯ заявки).
        //   wave2_pool_cap — сколько топ-поставщиков пула (по confidence/rating) добирать;
        //   wave2_site_chunk — доменов в одном OR-запросе (лимит длины ~15).
        'wave2_pool_cap' => (int) env('EMAILS_WAVE2_POOL_CAP', 45),
        'wave2_site_chunk' => (int) env('EMAILS_WAVE2_SITE_CHUNK', 15),
        // Потолок site:-OR запросов на батч (per-позиция: гоняем термин КАЖДОЙ позиции по
        // ещё не совпавшим доменам пула → покрываем разнотипные батчи + цитируем поставщику
        // ту позицию, чья страница у него нашлась). Ограничивает бюджет Яндекса.
        'wave2_max_site_queries' => (int) env('EMAILS_WAVE2_MAX_SITE_QUERIES', 12),

        // Гейт качества волны 1 (discovery-first): если пул батча < min_pool ИЛИ доля
        // найденных Яндексом < min_match_rate% — откладываем генерацию батча, гоним
        // discovery по кандидатам, и повторяем когда discovery готов (переиспользуя
        // сохранённую Яндекс-выдачу, уже без оглядки на порог). За флагом (по умолч. off).
        'gate_enabled' => (bool) env('EMAILS_POOL_GATE_ENABLED', false),
        'gate_min_pool' => (int) env('EMAILS_POOL_MIN', 30),
        'gate_min_match_rate' => (int) env('EMAILS_POOL_MIN_MATCH_RATE', 25), // %
    ],

    // Накопительная отсрочка по загрузке получателей (Version A). Тонкий АНОНИМНЫЙ батч
    // (< target_items позиций), чей пул поставщиков заметно перегружен (доля адресатов с
    // pending >= loaded_pending превышает loaded_fraction_pct%), откладывается в
    // deferred_batches (reason='recipient_load', status='accumulating'). Команда
    // emails:process-load-deferred копит однородные (тип+домен) позиции и выпускает батч,
    // когда набралось >= target_items ЛИБО пул разгрузился ЛИБО прошло max_hold_hours.
    // Именные заявки НЕ откладываются (клиентский приоритет). За флагом (по умолч. off).
    'email_load_defer' => [
        'enabled' => (bool) env('EMAILS_LOAD_DEFER_ENABLED', false),
        'target_items' => (int) env('EMAILS_LOAD_DEFER_TARGET', 3),
        'loaded_pending' => (int) env('EMAILS_LOAD_DEFER_LOADED_PENDING', 10),
        'loaded_fraction_pct' => (int) env('EMAILS_LOAD_DEFER_FRACTION', 10), // %
        'max_hold_hours' => (int) env('EMAILS_LOAD_DEFER_MAX_HOLD_HOURS', 48),
        // Смежные категории (LLM-склейка): заявка-якорь притягивает родственные отложенные
        // сироты того же домена (позиции с того же объекта/агрегата — ролик+замок+отводка
        // одного лифта) в одну сборную заявку. Решает LLM (1 вызов на батч с сиротами),
        // без хардкода таксономии. Сироты цепляются к живым заявкам, а не ждут друг друга.
        'cluster_enabled' => (bool) env('EMAILS_LOAD_CLUSTER_ENABLED', false),
        'cluster_model' => env('EMAILS_LOAD_CLUSTER_MODEL', 'gpt-4o-mini'),
    ],

    // Прогрев отправителей (Phase 3): новый/сброшенный ящик стартует с малого дневного
    // лимита и растёт за успешные дни. Лимит гейтит ГЕНЕРАЦИЮ (не генерим больше, чем
    // ящики могут отослать); большой батч бьётся на несколько ящиков. global_daily_cap —
    // предохранитель на весь IP/платформу от нового всплеска. За флагом (по умолч. off).
    'email_warmup' => [
        'enabled' => (bool) env('EMAILS_WARMUP_ENABLED', false),
        'start' => (int) env('EMAILS_WARMUP_START', 30),            // стартовый дневной лимит ящика
        'step_pct' => (int) env('EMAILS_WARMUP_STEP_PCT', 20),      // +% за успешный день
        'cap' => (int) env('EMAILS_WARMUP_CAP', 200),               // потолок дневного лимита ящика (зрелый домен)
        // Потолок дневного лимита ящика ПО ВОЗРАСТУ ДОМЕНА (дней от создания первого ящика
        // на домене). Свежий домен нельзя разгонять до cap за неделю — почтовики видят
        // всплеск с молодого домена и жгут репутацию (кейс wwwsend). Формат «дней:лимит»
        // по возрастанию; действует НИЖНЯЯ подходящая ступень, дальше — общий cap.
        'age_caps' => env('EMAILS_WARMUP_AGE_CAPS', '3:15,7:25,14:40,21:60,30:90,45:130'),
        'global_daily_cap' => (int) env('EMAILS_GLOBAL_DAILY_CAP', 10000), // потолок писем/сутки на всю платформу
        'max_sub_batches' => (int) env('EMAILS_WARMUP_MAX_SUBBATCHES', 10), // максимум под-батчей на батч (2 AI-вызова каждый)
    ],

    // Гвард спам-реджекта отправителей (emails:spam-reject-guard): отключение по ДОЛЕ,
    // а не по абсолютному счётчику. Считает окно window_days: доля = спам-реджекты /
    // отправлено (только при отправлено >= min_sent). >= disable_rate_pct → отключить;
    // отключённый с долей < reenable_rate_pct → вернуть (гистерезис, авто-восстановление).
    // Атрибуция спама — по ящику, на чей IMAP пришёл NDR (реальный отправитель). За флагом.
    'email_spam_guard' => [
        'enabled' => (bool) env('EMAILS_SPAM_GUARD_ENABLED', false),
        'window_days' => (int) env('EMAILS_SPAM_GUARD_WINDOW_DAYS', 3),
        'min_sent' => (int) env('EMAILS_SPAM_GUARD_MIN_SENT', 30),          // объём для ОТКЛЮЧЕНИЯ (строгий)
        'reenable_min_sent' => (int) env('EMAILS_SPAM_GUARD_REENABLE_MIN_SENT', 10), // объём для ВОЗВРАТА (мягкий)
        'disable_rate_pct' => (float) env('EMAILS_SPAM_GUARD_DISABLE_PCT', 15),   // порог доли спам-реджекта
        'dead_rate_pct' => (float) env('EMAILS_SPAM_GUARD_DEAD_PCT', 10),         // порог доли мёртвых адресов (страховка)
        'today_disable_pct' => (float) env('EMAILS_SPAM_GUARD_TODAY_PCT', 25),    // порог доли спама за ТЕКУЩИЕ сутки (ловит резкий всплеск)
        'today_min_sent' => (int) env('EMAILS_SPAM_GUARD_TODAY_MIN_SENT', 30),    // мин. отправок сегодня для дневного триггера
        'reenable_rate_pct' => (float) env('EMAILS_SPAM_GUARD_REENABLE_PCT', 8),
    ],

    // Каналы отправки (Phase 3c): пул egress-каналов для диверсификации исходящего IP
    // (репутация общего релей-IP горит на всплеске → mail.ru спам-флаг на все ящики;
    // лечится разными source-IP). Применяется ТОЛЬКО к beget-ящикам; распределение
    // per-send по всему пулу (по id письма/ответа) — поток каждого ящика раскладывается
    // по ВСЕМ каналам (shared-пул: ровная нагрузка, +релей без churn, бан IP отнимает
    // 1/N у всех) — см. App\Services\Senders\RelayChannelSelector. Пусто → текущий
    // одиночный путь (smtp.beget.com → /etc/hosts → релей :8000), полный backward-compat.
    //
    // Канал: {host, port, source_ip, peer_name, weight}. Два типа:
    //   - proxy: host=IP релея, port=порт (релей слушает разные порты под разные egress-IP);
    //            source_ip — документирует egress-IP этого порта (для SPF/rDNS). peer_name
    //            по умолчанию smtp.beget.com (сертификат beget при коннекте на IP).
    //   - direct: host=прямой IP beget, source_ip=внешний IP VDS (bindto сокета) — «N IP
    //            на 1 VDS» (app→beget напрямую, mail.ru видит source_ip). peer_name=smtp.beget.com.
    // Задаётся JSON в env EMAILS_RELAY_CHANNELS, напр.:
    //   [{"host":"45.146.167.20","port":8000,"source_ip":"45.146.167.20","weight":1},
    //    {"host":"45.146.167.20","port":8001,"source_ip":"45.146.167.21","weight":1}]
    // На каждый source_ip нужен свой rDNS/PTR + запись в SPF доменов-отправителей.
    'email_relays' => [
        'channels' => json_decode((string) env('EMAILS_RELAY_CHANNELS', '[]'), true) ?: [],
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
    // Фаза 2: capacity-планировщик (backlog send_intents + ленивый рендер под ёмкость).
    // Пока OFF — строится параллельно текущему генератору. Включать ТОЛЬКО вместе с
    // выключением EMAILS_GENERATE_ENABLED (иначе оба обрабатывают заявки).
    'email_planner' => [
        'enabled' => (bool) env('EMAILS_PLANNER_ENABLED', false),
        // Заявок за тик билдера интентов.
        'build_request_limit' => (int) env('EMAILS_PLANNER_BUILD_LIMIT', 50),
        // Дефолтная цель офферов на позицию (если requests.offer_target не задан).
        'offer_target_default' => (int) env('EMAILS_PLANNER_OFFER_TARGET', 4),
        // Окно свежести: билдер берёт заявки не старше N дней (живой набор, не архив).
        'fresh_days' => (int) env('EMAILS_PLANNER_FRESH_DAYS', 7),
        // v2 дневной планировщик (emails:plan-day): за флагом, параллельно v1 top-up.
        'dayplan_enabled' => (bool) env('EMAILS_DAYPLAN_ENABLED', false),
        'dayplan_max_per_email' => (int) env('EMAILS_DAYPLAN_MAX_PER_EMAIL', 4),  // позиций в письме
        'dayplan_yandex' => (bool) env('EMAILS_DAYPLAN_YANDEX', true),            // Яндекс-релевантность
        // Кэш найденных Яндексом доменов НА ПОЗИЦИЮ (дней): все позиции прогоняются
        // через Яндекс на стадии планирования, но повторные утра берут выдачу из кэша
        // (матч с текущим пулом — всегда свежий). 0 = без кэша, каждый прогон заново.
        'dayplan_yandex_cache_days' => (int) env('EMAILS_DAYPLAN_YANDEX_CACHE_DAYS', 3),
        'dayplan_affinity_ai' => (bool) env('EMAILS_DAYPLAN_AFFINITY_AI', true),  // AI-аффинность
        // Модель аффинности: mini чрезмерно дробит (метит avoid обычные разнотипные
        // комплектующие), gpt-4o судит адекватно; вызовов мало (1 на домен-группу).
        'dayplan_affinity_model' => env('EMAILS_DAYPLAN_AFFINITY_MODEL', 'gpt-4o'),
        // Discovery новых доменов из Яндекс-выдачи plan-day (боевой прогон): анализ
        // сайта + авто-добавление поставщика; потолок диспатчей за прогон.
        'dayplan_discovery' => (bool) env('EMAILS_DAYPLAN_DISCOVERY', true),
        'dayplan_discovery_max' => (int) env('EMAILS_DAYPLAN_DISCOVERY_MAX', 30),
        // Режим top-up plan-render при включённом plan-day: рендерить только
        // поставщиков, созданных за последние N часов (новые из discovery).
        'dayplan_topup_new_hours' => (int) env('EMAILS_DAYPLAN_TOPUP_NEW_HOURS', 24),
    ],

    'email_generate' => [
        'enabled' => (bool) env('EMAILS_GENERATE_ENABLED', false),
        // Phase 1b: рекапенет-гейт генерации (первый кирпич backlog/outbox). НЕ рендерить
        // письмо получателю, у которого уже >= его дневного cap НЕ-held pending-писем
        // (outbox переполнен) — такие поставщики откладываются (reason='recipient_cap',
        // пин) и повторятся, когда outbox разгрузится. Применяется к волнам 1–2 (не к
        // held-волне 3). По умолчанию OFF — включать осознанно после проверки.
        'recipient_gate' => (bool) env('EMAILS_GENERATE_RECIPIENT_GATE', false),
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
        // Ротация ящиков: ОДИН ящик пишет одному поставщику не чаще, чем раз в N дней
        // (универсальный дилер попадает в разные батчи одного ящика → иначе пачка писем
        // с одного ящика). Поставщика покрывают ДРУГИЕ ящики. 0 = выключено.
        'sender_recipient_days' => (int) env('EMAILS_SENDER_RECIPIENT_DAYS', 7),
        // Фильтр свежести: не генерить заявки старше N дней (защита от «слива backlog'а» —
        // древние заявки, осевшие в active/new/draft, не загребаются массовой генерацией).
        // 0 = выключено. Точечный --request не ограничивается.
        'max_request_age_days' => (int) env('EMAILS_GENERATE_MAX_AGE_DAYS', 30),
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

    /*
     * Биллинг. api_reconcile.enabled — предохранитель для списания за выполненные
     * API-позиции в balance:check-completed-items. API-ветка была никогда не
     * бита: raw-SQL запись КП в request_item_responses не будит Eloquent-обсервер
     * offers_count → charge-обсервер не срабатывал, а сам chargeForItem падал на
     * NOT NULL request_id. После фикса (nullable request_id + разбор API-холдов в
     * команде) флаг по умолчанию OFF: сначала прогнать --dry-run и сверить сумму
     * бэклога, затем включить BILLING_API_RECONCILE_ENABLED=true. --dry-run и
     * --force игнорируют флаг.
     */
    'billing' => [
        'api_reconcile' => [
            'enabled' => (bool) env('BILLING_API_RECONCILE_ENABLED', false),
        ],
    ],

];
