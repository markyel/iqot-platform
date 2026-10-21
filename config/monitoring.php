<?php

/*
 * Мониторинг цен (IQOT-Lite / Гибрид). Дизайн: docs/monitoring-design.md, Ф0: docs/monitoring-f0-spec.md.
 * Без MONITORING_ENABLED плановые команды молчат (ручной прогон — --force).
 */
return [

    'enabled' => (bool) env('MONITORING_ENABLED', false),

    // Решение владельца (2026-10-14): вежливый обход (robots.txt, лимит на домен) с UA браузера.
    'user_agent' => env('MONITORING_UA', env('EMAILS_ANALYZE_UA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36')),

    'fetch' => [
        'timeout' => (int) env('MONITORING_FETCH_TIMEOUT', 20),
        'connect_timeout' => 10,
        'max_bytes' => 5_000_000,
        'respect_robots' => true,
        'robots_cache_hours' => 12,
        // Пилот 14.10: 1 запрос/с к небольшому B2B-сайту (lift-snab, Bitrix «Метрика») → бан IP сервера.
        'default_rate_ms' => (int) env('MONITORING_DOMAIN_RATE_MS', 4000),
        // Текста меньше порога → вероятная JS-заглушка → headless-фолбэк.
        'http_min_chars' => 300,
        'block_days' => 7,
        'headless' => (bool) env('MONITORING_HEADLESS', true),
    ],

    // Исходящие каналы (EgressPool): JSON-список {"name","proxy"?,"fallback"?}. Пусто → только
    // «direct» (IP сервера IQOT). proxy — http://host:3128 (tinyproxy на наших хостах, доступ
    // по IP прода, deploy/relay/provision-egress-proxy.sh). fallback — канал только на случай,
    // когда основные забанены сайтом (хост, который сам обходит те же сайты или несёт почту).
    'egress' => [
        'channels' => json_decode((string) env('MONITORING_EGRESS', '[]'), true) ?: [],
        // Бан канала сайтом (401/403/капча) — на столько часов этот домен уходит на другой канал.
        'ban_hours' => (int) env('MONITORING_EGRESS_BAN_HOURS', 72),
        // Забанил столько каналов → сайт блокируется целиком (антибот против IP дата-центров:
        // перебор IP бесполезен и светит резервные каналы). 0 — перебирать все.
        'max_bans_per_site' => (int) env('MONITORING_EGRESS_MAX_BANS_PER_SITE', 2),
        // Отказ самого прокси — канал в хвост очереди на столько секунд (0 — выкл.).
        'down_seconds' => (int) env('MONITORING_EGRESS_DOWN_SECONDS', 300),
    ],

    // Ф3 «Гибрид»: проверочные КП к ценам сайтов. Отправка реальных писем поставщикам — только
    // при send_enabled (выбор позиций, сбор ответов и «бесплатные» сверки работают и без него).
    // Тарификация покрытых позиций (docs/monitoring-design.md §11a): начисление за сутки, списание с баланса
    // еженедельно и в конце периода подписки; при нехватке средств — предупреждение, через grace_days — пауза.
    'billing' => [
        'enabled' => (bool) env('MONITORING_BILLING_ENABLED', true),
        'start_date' => env('MONITORING_BILLING_FROM', '2026-10-15'), // решение владельца 16.10.2026: «со вчерашней»
        'charge_every_days' => (int) env('MONITORING_BILLING_EVERY_DAYS', 7),
        'grace_days' => (int) env('MONITORING_BILLING_GRACE_DAYS', 3),
        'timezone' => 'Europe/Moscow',
    ],

    'hybrid' => [
        'send_enabled' => (bool) env('MONITORING_HYBRID_SEND', false),
        // Запросы КП профильному пулу (RfqPlanner::pool). Выключено 16.10.2026: подборщик отдавал
        // одних и тех же 8–9 «универсалов» по возрастанию id (в т.ч. наш liftway.ru) на все товары —
        // 3 783 запроса. С 19.10.2026 пул ранжируется (RfqPlanner::rankPool) и выравнивается по нагрузке.
        'pool_enabled' => (bool) env('MONITORING_HYBRID_POOL', false),
        // Не спрашивать пул: наши и тестовые адреса (e-mail целиком или домен). Свои ящики владельца — в .env прода.
        // Однотипные позиции пула (HomogeneousKey: тип + бренд + название без цифр) — в одно письмо, до N штук
        // (решение владельца 19.10.2026); разнотипные — по одной, как в обычной рассылке.
        'pool_max_per_letter' => (int) env('MONITORING_HYBRID_POOL_PER_LETTER', 4),
        'pool_exclude' => array_values(array_filter(array_map('trim', explode(',', (string) env('MONITORING_HYBRID_POOL_EXCLUDE', 'liftway.ru'))))),
    ],

    // Суточные квоты (MonitoringGovernor). Общие на все программы.
    'quotas' => [
        'yandex_query' => (int) env('MONITORING_YANDEX_PER_DAY', 2000),
        'fetch_http' => (int) env('MONITORING_FETCHES_PER_DAY', 20000),
        'fetch_headless' => (int) env('MONITORING_HEADLESS_PER_DAY', 1500),
        // Обход карточек сайтов по sitemap (индекс заголовков/артикулов) — отдельно от проверок цен.
        'fetch_index' => (int) env('MONITORING_INDEX_PER_DAY', 60000),
        'llm_usd' => (float) env('MONITORING_LLM_USD_PER_DAY', 5),
        // Проверочные письма Гибрида (Ф3) в сутки — делят ящики и капы получателей с заявками.
        'email' => (int) env('MONITORING_EMAILS_PER_DAY', 100),
    ],

    'llm' => [
        'model' => env('MONITORING_LLM_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('MONITORING_LLM_TIMEOUT', 60),
        // Предел вызовов ИИ мониторингом в минуту — половина общего лимита прокси (300/мин), остальное письмам/ответам.
        'per_minute' => (int) env('MONITORING_LLM_PER_MINUTE', 150),
        'max_tokens' => 400,
        // Окно текста вокруг упоминаний артикула/названия и общий потолок (не «первые 7000»).
        'window_chars' => 2500,
        'max_chars' => 8000,
    ],

    // $ за 1M токенов [вход, выход]. Поиск модели — точное имя, затем самый длинный префикс
    // (урок CE: «gpt-4o» перехватывал «gpt-4o-mini-2024-07-18» → учёт ×5).
    'llm_prices' => [
        'gpt-4o-mini' => [0.15, 0.60],
        'gpt-4o' => [2.50, 10.00],
        'gpt-4.1-mini' => [0.40, 1.60],
        'gpt-4.1-nano' => [0.10, 0.40],
        'gpt-5-nano' => [0.05, 0.40],
        'gpt-5-mini' => [0.25, 2.00],
    ],

    'sanity' => [
        'jump_ratio' => 3.0,           // скачок к текущей цене — только с повтором
        'jump_recheck_hours' => 36,    // повтор через 1–2 дня, не через цикл
        'jump_confirm_tolerance' => 0.05,
        'outlier_ratio' => 4.0,        // ×4 от медианы
        'outlier_min_prices' => 4,
    ],

    'retry' => [
        'backoff_minutes' => [60, 360, 1440],
        'max_fails' => 5,
    ],

    // Каденция переобхода листинга, если товар не входит ни в одну активную программу.
    'default_refresh_days' => 14,

    'schedule' => [
        'jitter_pct' => 10, // разброс next_check_at, чтобы не было пиков по домену
        'dispatch_batch' => (int) env('MONITORING_DISPATCH_BATCH', 500),
    ],

    'import' => [
        'max_rows_per_call' => 5000,
        'chunk' => 500,
    ],

    // Решение владельца (2026-10-14): маркетплейсы и агрегаторы игнорируем.
    // Совпадение по подстроке хоста (как SupplierTargetingService::BLOCKED).
    // Поиск листингов (Ф1). mode: sync — синхронный Search API (≈488 ₽/1000 днём), deferred —
    // отложенный (≈30,5 ₽/1000), требует квоты отложенного режима в Yandex Cloud (без неё 429).
    'search' => [
        'mode' => env('MONITORING_SEARCH_MODE', 'sync'),
        'results_per_query' => (int) env('MONITORING_SEARCH_RESULTS', 10),
        // Дефолты агрессивности (пресет «Стандарт»); рабочие значения — MonitoringSettings
        // (/manage/monitoring/settings). Замер 14.10: 4 LLM-запроса/25 кандидатов ≈ 90% полноты от 8.
        'queries_per_product' => (int) env('MONITORING_SEARCH_QUERIES', 4),
        'max_candidates' => (int) env('MONITORING_SEARCH_MAX_CANDIDATES', 25),
        'max_per_domain' => 2,
        'deferred_poll_seconds' => 5,
        'deferred_max_wait_seconds' => 180,
        // Повтор поиска: найдено — раз в N дней (новые продавцы); не найдено — 30/60/90 дней.
        'research_found_days' => 30,
        'research_not_found_days' => [30, 60, 90],
        // Цена Яндекса за 1000 запросов, ₽ (для учёта расходов).
        'rub_per_1000' => ['sync' => 488.0, 'deferred' => 30.5],
    ],

    'blocked_domains' => [
        'ozon.', 'wildberries.', 'joom.', 'dns-shop.ru', 'onlinetrade.ru', 'vseinstrumenti.ru', 'lemanapro.ru',
        'megastroy.com', 'livemaster.ru', 'autopiter.ru', 'euroauto.ru', 'citilink.ru', 'mvideo.ru', 'eldorado.ru',
        'ozon.ru', 'wildberries.ru', 'market.yandex.ru', 'avito.ru', 'sbermegamarket.ru', 'megamarket.ru',
        'kazanexpress.ru', 'aliexpress.', 'alibaba.', 'made-in-china.', 'amazon.', 'ebay.', 'youla.ru',
        'tiu.ru', 'pulscen.ru', 'satom.ru', 'blizko.ru', 'all.biz', 'prom.ua',
        'sportmaster.ru', 'lamoda.ru', 'ostin.com', 'farpost.ru', 'olx.', 'emex.ru',
        // Собственные ресурсы (LazyLift) — не рынок: их цены не мониторим и в покрытие не считаем.
        'liftway.ru',
        'yandex.ru', 'google.', 'youtube.', 'rutube.ru', 'dzen.ru', 'wikipedia.', 'vk.com', 'ok.ru',
        'facebook.', 'instagram.', 'twitter.', 'x.com', 'tiktok.', 'pinterest.', 'linkedin.',
        'hh.ru', 'vc.ru', 'pikabu.ru', 'otvet.mail.ru', 'forum.',
        '2gis.ru', 'yell.ru', 'zoon.ru', 'cataloxy.ru', 'flamp.ru', 'orgpage.ru', 'spr.ru',
        'rusprofile.ru', 'list-org.com', 'checko.ru', 'b2b-center.ru',
        'tr-page.', 'translate.', 'turbopages.org', 'manualslib.',
    ],

    // Внутренние коды учётных систем клиента — не артикул (урок LazyLift: КСУ НСИ, УПП, МТР, ЕИСЗ).
    'internal_code_patterns' => [
        '/\b(КСУ\s*НСИ|НСИ|УПП|МТР|ЕИСЗ|ЕНС|SAP)\b/u',
    ],

    // Токены «серий/линеек» из названий — не артикул (урок LazyLift: ECO3000 привязал чужой товар).
    'series_tokens' => [
        'ECO3000', 'GEN2', 'GEN3', 'MRL', 'MONOSPACE', 'MINISPACE', 'SYNERGY', 'EVOLUTION',
    ],

    // Синонимы брендов → канон (нормализуется BrandNormalizer).
    'brand_aliases' => [
        'THYSSENKRUPP' => 'TKE', 'THYSSEN' => 'TKE', 'TKELEVATOR' => 'TKE', 'TK' => 'TKE',
        'OTISELEVATOR' => 'OTIS',
        'KONECORPORATION' => 'KONE',
        'SCHINDLERGROUP' => 'SCHINDLER',
        'МЛЗ' => 'MLZ', 'MOGILEVLIFTMASH' => 'MLZ', 'МОГИЛЕВЛИФТМАШ' => 'MLZ',
        'ЩЛЗ' => 'SHLZ', 'ЩЕРБИНСКИЙЛИФТОСТРОИТЕЛЬНЫЙЗАВОД' => 'SHLZ',
        'КМЗ' => 'KMZ', 'КАРАЧАРОВСКИЙМЕХАНИЧЕСКИЙЗАВОД' => 'KMZ',
    ],
];
