<?php

namespace App\Support\Monitoring;

use App\Models\SystemSetting;

/**
 * Агрессивность мониторинга: с какой скоростью проглатываем позиции и сколько тратим.
 * Значения — в system_settings (ключи monitoring.*, правка в /manage/monitoring/settings,
 * действуют без деплоя и рестарта воркеров); без записи — дефолты config/monitoring.php.
 *
 * Глубина поиска (замер 14.10.2026 на 20 позициях против CE): полнота 11,5% при 2 простых
 * запросах и 8 кандидатах → 36% при 4 LLM-запросах и 25 кандидатах → 40% при 8.
 */
final class MonitoringSettings
{
    /** ключ настройки => [путь дефолта в config, тип] */
    public const FIELDS = [
        'search_queries' => ['monitoring.search.queries_per_product', 'int'],
        'search_candidates' => ['monitoring.search.max_candidates', 'int'],
        'yandex_per_day' => ['monitoring.quotas.yandex_query', 'int'],
        'fetch_per_day' => ['monitoring.quotas.fetch_http', 'int'],
        'headless_per_day' => ['monitoring.quotas.fetch_headless', 'int'],
        'llm_usd_per_day' => ['monitoring.quotas.llm_usd', 'float'],
        'index_per_day' => ['monitoring.quotas.fetch_index', 'int'],
        'verify_letters_per_day' => ['monitoring.quotas.email', 'int'],
    ];

    /** Квота MonitoringGovernor (ресурс ledger) → ключ настройки. */
    public const QUOTA_KEYS = [
        'yandex_query' => 'yandex_per_day',
        'fetch_http' => 'fetch_per_day',
        'fetch_headless' => 'headless_per_day',
        'llm_usd' => 'llm_usd_per_day',
        'fetch_index' => 'index_per_day',
        'email' => 'verify_letters_per_day',
    ];

    public const PRESETS = [
        'economy' => [
            'label' => 'Эконом', 'hint' => 'минимум расходов, поиск неглубокий',
            'search_queries' => 2, 'search_candidates' => 12, 'yandex_per_day' => 1000,
            'fetch_per_day' => 15000, 'headless_per_day' => 1000, 'llm_usd_per_day' => 3, 'index_per_day' => 20000, 'verify_letters_per_day' => 50,
        ],
        'standard' => [
            'label' => 'Стандарт', 'hint' => 'оптимум по замеру: 4 запроса дают ~90% полноты от 8',
            'search_queries' => 4, 'search_candidates' => 25, 'yandex_per_day' => 2500,
            'fetch_per_day' => 30000, 'headless_per_day' => 2000, 'llm_usd_per_day' => 8, 'index_per_day' => 60000, 'verify_letters_per_day' => 100,
        ],
        'fast' => [
            'label' => 'Быстрый', 'hint' => 'та же глубина, втрое быстрее проглатываем позиции',
            'search_queries' => 4, 'search_candidates' => 25, 'yandex_per_day' => 7500,
            'fetch_per_day' => 70000, 'headless_per_day' => 4000, 'llm_usd_per_day' => 20, 'index_per_day' => 120000, 'verify_letters_per_day' => 200,
        ],
        'max' => [
            'label' => 'Максимум', 'hint' => 'глубина как у Content Engine, дорого',
            'search_queries' => 8, 'search_candidates' => 25, 'yandex_per_day' => 10000,
            'fetch_per_day' => 80000, 'headless_per_day' => 4000, 'llm_usd_per_day' => 25, 'index_per_day' => 150000, 'verify_letters_per_day' => 300,
        ],
    ];

    public static function get(string $key): int|float
    {
        [$path, $type] = self::FIELDS[$key];
        $raw = SystemSetting::get('monitoring.' . $key);
        $value = ($raw === null || $raw === '') ? config($path) : $raw;

        return $type === 'int' ? (int) $value : (float) $value;
    }

    /** Суточная квота ресурса регулятора; null — ресурс не настраивается здесь (берётся из config). */
    public static function quota(string $resource): int|float|null
    {
        $key = self::QUOTA_KEYS[$resource] ?? null;

        return $key !== null ? self::get($key) : null;
    }

    /** @return array<string, int|float> */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::FIELDS) as $k) {
            $out[$k] = self::get($k);
        }

        return $out;
    }

    /** Выбранный пресет (или custom — значения правились вручную). */
    public static function preset(): string
    {
        $p = (string) SystemSetting::get('monitoring.preset', '');
        if ($p !== '' && $p !== 'custom' && isset(self::PRESETS[$p])) {
            return self::matches($p, self::all()) ? $p : 'custom';
        }

        return $p === 'custom' ? 'custom' : (self::detect(self::all()) ?? 'custom');
    }

    /** @param array<string, int|float> $values */
    public static function save(array $values, string $preset): void
    {
        foreach (array_keys(self::FIELDS) as $k) {
            if (array_key_exists($k, $values)) {
                SystemSetting::set('monitoring.' . $k, (string) $values[$k]);
            }
        }
        SystemSetting::set('monitoring.preset', $preset);
    }

    /**
     * Оценка темпа и расходов при значениях $v.
     *
     * @param array<string, int|float> $v
     * @return array{positions_per_day: int, rub_per_day: float, rub_per_position: float, fetch_needed: int, fetch_ok: bool, mode: string, rub_per_query: float}
     */
    public static function estimate(array $v): array
    {
        $mode = (string) config('monitoring.search.mode', 'sync');
        $rubPerQuery = (float) (config("monitoring.search.rub_per_1000.{$mode}") ?? 0) / 1000;
        $q = max(1, (int) $v['search_queries']);
        $perDay = intdiv(max(0, (int) $v['yandex_per_day']), $q);
        // Первичный поиск: на позицию ~кандидатов загрузок (часть отсеется до загрузки — оценка сверху).
        $fetchNeeded = $perDay * (int) $v['search_candidates'];

        return [
            'positions_per_day' => $perDay,
            'rub_per_day' => round((int) $v['yandex_per_day'] * $rubPerQuery, 0),
            'rub_per_position' => round($q * $rubPerQuery, 2),
            'fetch_needed' => $fetchNeeded,
            'fetch_ok' => $fetchNeeded <= (int) $v['fetch_per_day'],
            'mode' => $mode,
            'rub_per_query' => $rubPerQuery,
        ];
    }

    /** @param array<string, int|float> $values */
    private static function matches(string $preset, array $values): bool
    {
        foreach (array_keys(self::FIELDS) as $k) {
            if ((float) self::PRESETS[$preset][$k] !== (float) $values[$k]) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, int|float> $values */
    private static function detect(array $values): ?string
    {
        foreach (array_keys(self::PRESETS) as $p) {
            if (self::matches($p, $values)) {
                return $p;
            }
        }

        return null;
    }
}
