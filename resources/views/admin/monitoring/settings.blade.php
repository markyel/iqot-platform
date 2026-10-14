@extends('layouts.cabinet')

@section('title', 'Мониторинг: агрессивность')

@php
    $fmt = fn ($n) => number_format((float) $n, 0, '.', ' ');
    $used = fn ($r) => (int) ($today[$r]['units'] ?? 0);
@endphp

@section('content')
<x-page-header
    title="Мониторинг цен: агрессивность"
    description="С какой скоростью проглатываем позиции и сколько на это тратим. Изменения действуют сразу, без деплоя."
/>
<div style="max-width: 980px; margin: 0 auto;">
    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">{{ session('success') }}</div>
    @endif
    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger" style="margin-bottom: var(--space-4);">{{ $errors->first() }}</div>
    @endif

    <div class="stats-grid" style="margin-bottom: var(--space-6);">
        <x-stat-card value="{{ $fmt($backlog) }}" label="Позиций ждут поиска" icon="hourglass" icon-type="primary" />
        <x-stat-card value="{{ $fmt($used('yandex_query')) }} / {{ $fmt($values['yandex_per_day']) }}" label="Запросов Яндекса сегодня" icon="search" icon-type="primary" />
        <x-stat-card value="{{ $fmt($used('fetch_http')) }} / {{ $fmt($values['fetch_per_day']) }}" label="Загрузок страниц сегодня" icon="download" icon-type="primary" />
        <x-stat-card value="${{ number_format((float) ($today['llm_call']['cost_usd'] ?? 0), 2) }} / ${{ $values['llm_usd_per_day'] }}" label="LLM сегодня" icon="cpu" icon-type="primary" />
    </div>
    @unless ($enabled)
        <div class="alert alert-info" style="margin-bottom: var(--space-4);">Мониторинг выключен (MONITORING_ENABLED=false): планировщик не раздаёт работу.</div>
    @endunless

    <form method="POST" action="{{ route('admin.monitoring.settings.update') }}" id="aggr-form">
        @csrf
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">Режим</h2></div>
            <div class="card-body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-3);">
                @foreach ($presets as $key => $p)
                    <label class="card" style="cursor: pointer; padding: var(--space-4); margin: 0; {{ $preset === $key ? 'outline: 2px solid var(--color-primary, #2563eb);' : '' }}">
                        <input type="radio" name="preset" value="{{ $key }}" @checked($preset === $key) data-preset='@json(array_intersect_key($p, \App\Support\Monitoring\MonitoringSettings::FIELDS))'>
                        <strong>{{ $p['label'] }}</strong>
                        <div style="color: var(--color-text-muted); font-size: var(--text-sm); margin-top: var(--space-1);">{{ $p['hint'] }}</div>
                        <div style="font-size: var(--text-sm); margin-top: var(--space-2);">{{ $p['search_queries'] }} запр. × {{ $p['search_candidates'] }} канд., {{ $fmt($p['yandex_per_day']) }} запр./сут</div>
                    </label>
                @endforeach
                <label class="card" style="cursor: pointer; padding: var(--space-4); margin: 0; {{ $preset === 'custom' ? 'outline: 2px solid var(--color-primary, #2563eb);' : '' }}">
                    <input type="radio" name="preset" value="custom" @checked($preset === 'custom')>
                    <strong>Свои значения</strong>
                    <div style="color: var(--color-text-muted); font-size: var(--text-sm); margin-top: var(--space-1);">поля ниже</div>
                </label>
            </div>
        </div>

        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">Параметры</h2></div>
            <div class="card-body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);">
                @php
                    $fields = [
                        'search_queries' => ['Запросов в Яндекс на позицию', 'Глубина поиска. Замер: 2 → 28%, 4 → 36%, 8 → 40% полноты против CE.', 1],
                        'search_candidates' => ['Кандидатов-страниц на позицию', 'Сколько страниц из выдачи проверяем (≤2 с домена).', 1],
                        'yandex_per_day' => ['Запросов Яндекса в сутки', 'Главный регулятор скорости и расходов на поиск.', 100],
                        'fetch_per_day' => ['Загрузок страниц в сутки', 'Проверка найденных страниц и переобход.', 1000],
                        'headless_per_day' => ['Рендеров браузером в сутки', 'Сайты с ценой за JS; дорого по памяти сервера.', 100],
                        'llm_usd_per_day' => ['LLM, $ в сутки', 'Составление запросов и извлечение цены.', 0.5],
                    ];
                @endphp
                @foreach ($fields as $name => [$label, $help, $step])
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="f-{{ $name }}">{{ $label }}</label>
                        <input type="number" class="input" id="f-{{ $name }}" name="{{ $name }}" value="{{ $values[$name] }}" step="{{ $step }}" min="0" required>
                        <small class="form-help">{{ $help }}</small>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">Расчёт</h2></div>
            <div class="card-body" id="estimate"
                 data-rub-per-query="{{ $estimate['rub_per_query'] }}" data-backlog="{{ $backlog }}" data-mode="{{ $estimate['mode'] }}">
                <div class="stats-grid">
                    <x-stat-card value="—" label="Позиций в сутки (первичный поиск)" icon="gauge" icon-type="primary" />
                    <x-stat-card value="—" label="₽ в сутки на Яндекс" icon="wallet" icon-type="primary" />
                    <x-stat-card value="—" label="₽ на позицию" icon="tag" icon-type="primary" />
                    <x-stat-card value="—" label="Дней на текущую очередь" icon="calendar" icon-type="primary" />
                </div>
                <p style="margin-top: var(--space-3); color: var(--color-text-muted); font-size: var(--text-sm);">
                    Режим Яндекса: <strong>{{ $estimate['mode'] === 'deferred' ? 'отложенный' : 'синхронный' }}</strong>
                    ({{ number_format($estimate['rub_per_query'] * 1000, 1, ',', ' ') }} ₽ / 1000 запросов). 30 тыс. позиций:
                    <strong id="est-30k">—</strong>. Поиск раздаётся равномерно по суткам.
                </p>
                <p id="est-fetch-warn" class="alert alert-info" style="display: none; margin-top: var(--space-3);"></p>
            </div>
        </div>

        <x-button variant="accent" type="submit" icon="check">Сохранить</x-button>
    </form>
</div>

<script>
(function () {
    const form = document.getElementById('aggr-form');
    const est = document.getElementById('estimate');
    const rubPerQuery = parseFloat(est.dataset.rubPerQuery) || 0;
    const backlog = parseInt(est.dataset.backlog, 10) || 0;
    const cards = est.querySelectorAll('.stats-grid > *');
    const nf = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 });
    const val = (n) => parseFloat(form.elements[n].value) || 0;
    const setCard = (i, text) => {
        const el = cards[i] && (cards[i].querySelector('.stat-value, .stat-card-value, [class*="value"]') || cards[i]);
        if (el) el.textContent = text;
    };

    function recalc() {
        const q = Math.max(1, val('search_queries'));
        const perDay = Math.floor(val('yandex_per_day') / q);
        setCard(0, nf.format(perDay));
        setCard(1, nf.format(val('yandex_per_day') * rubPerQuery) + ' ₽');
        setCard(2, (q * rubPerQuery).toFixed(2).replace('.', ',') + ' ₽');
        setCard(3, perDay > 0 ? nf.format(Math.ceil(backlog / perDay)) : '∞');
        document.getElementById('est-30k').textContent = perDay > 0
            ? nf.format(Math.ceil(30000 / perDay)) + ' дн., ≈' + nf.format(30000 * q * rubPerQuery) + ' ₽ на первичный поиск'
            : 'поиск выключен';
        const need = perDay * val('search_candidates');
        const warn = document.getElementById('est-fetch-warn');
        if (need > val('fetch_per_day')) {
            warn.style.display = '';
            warn.textContent = 'Первичный поиск даст до ' + nf.format(need) + ' загрузок в сутки — больше лимита загрузок ('
                + nf.format(val('fetch_per_day')) + '): проверки будут отставать от поиска.';
        } else {
            warn.style.display = 'none';
        }
    }

    form.querySelectorAll('input[name="preset"]').forEach((r) => r.addEventListener('change', () => {
        if (r.dataset.preset) {
            const p = JSON.parse(r.dataset.preset);
            Object.keys(p).forEach((k) => { if (form.elements[k]) form.elements[k].value = p[k]; });
        }
        recalc();
    }));
    form.querySelectorAll('input[type="number"]').forEach((i) => i.addEventListener('input', () => {
        form.querySelector('input[name="preset"][value="custom"]').checked = true;
        recalc();
    }));
    recalc();
})();
</script>
@endsection
