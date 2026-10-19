@extends('layouts.cabinet')

@php
    $v = $data['value'] ?? [];
    $fmt = fn ($n) => number_format((float) $n, 0, '.', ' ');
    $statusRu = ['planned' => 'запланировано', 'queued' => 'в очереди писем', 'sent' => 'отправлено', 'answered' => 'ответ получен', 'expired' => 'без ответа', 'cancelled' => 'отменено'];
    $resRu = ['yandex_query' => 'Яндекс-запросы', 'fetch_http' => 'Загрузки страниц', 'fetch_headless' => 'Рендер Chrome', 'fetch_index' => 'Индекс каталогов', 'llm_call' => 'Вызовы ИИ', 'email' => 'Письма Гибрида'];
    $checkRu = ['ok' => 'цена получена', 'no_price' => 'цены нет («по запросу»)', 'mismatch' => 'не наш товар', 'failed' => 'сбой', 'suspicious' => 'подозрительно', 'gone' => 'страница пропала', 'blocked' => 'сайт закрыл доступ', 'soft404' => 'мягкий 404', 'unchanged' => 'без изменений'];
@endphp

@section('title', 'Мониторинг цен — ход выполнения')

@section('content')
    <x-page-header title="Мониторинг цен" description="Программы, поиск и проверки страниц, Гибрид (письма и КП), расход за сегодня">
        <x-slot name="actions"><a href="{{ route('admin.monitoring.settings') }}" class="btn btn-sm">Агрессивность</a></x-slot>
    </x-page-header>
    @include('admin.progress._nav')

    @if (! ($data['ok'] ?? false))
        <div class="card"><div class="card-body"><p>Не удалось получить данные.</p>
            <p style="color: var(--color-text-muted); font-size: var(--text-sm);">{{ $data['error'] ?? '' }}</p></div></div>
    @else
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h3 class="card-title">Программы</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Программа</th><th>Режим</th><th>Позиций</th><th>Сопоставлено</th><th>Покрыто ценой</th><th>Поиск: не искали / в работе / найдено / нет</th><th>Без цен</th><th>Прогноз первичного поиска</th></tr></thead>
                    <tbody>
                        @foreach ($v['programs'] as $row)
                            @php($p = $row['p']) @php($it = $row['it']) @php($st = $row['st'])
                            <tr>
                                <td><strong>{{ $p->name }}</strong> <span style="color: var(--color-text-muted);">#{{ $p->id }}</span></td>
                                <td><span class="badge {{ $p->price_mode === 'hybrid' ? 'badge-primary' : '' }}">{{ $p->price_mode }}</span> <span style="font-size: var(--text-sm); color: var(--color-text-muted);">{{ $p->status }}</span></td>
                                <td>{{ $fmt($it->active) }} <span style="color: var(--color-text-muted); font-size: var(--text-sm);">из {{ $fmt($it->total) }}</span></td>
                                <td>{{ $fmt($it->matched) }}@if ($it->amb > 0) <span style="color: var(--color-warning); font-size: var(--text-sm);">({{ $it->amb }} неоднозн.)</span>@endif</td>
                                <td><strong>{{ $fmt($it->covered) }}</strong> ({{ $it->active > 0 ? round(100 * $it->covered / $it->active) : 0 }}%)@if ($it->kp_only > 0) <span style="font-size: var(--text-sm);">· только КП {{ $it->kp_only }}</span>@endif</td>
                                <td>{{ $fmt($st->never) }} / {{ $fmt($st->inwork) }} / {{ $fmt($st->found) }} / {{ $fmt($st->notfound) }}</td>
                                <td>{{ $fmt($st->noprice) }}</td>
                                <td>{{ $row['eta_days'] > 0 ? '≈ ' . $row['eta_days'] . ' дн' : 'завершён' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding: var(--space-3) var(--space-4); color: var(--color-text-muted); font-size: var(--text-sm);">
                Прогноз — по агрессивности: ≈ {{ $fmt($v['per_product_per_day']) }} позиций в сутки. Сайтов с ценами: {{ $fmt($v['sites_priced']) }}.
                Индекс каталогов: {{ $fmt($v['index']->pages ?? 0) }} страниц с {{ $fmt($v['index']->sites ?? 0) }} сайтов, обработано {{ $fmt($v['index']->done ?? 0) }}.
            </div>
        </div>

        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h3 class="card-title">Подтверждение цен сайтов КП (Гибрид)</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Программа</th><th>Проверок: отпр. / ответ / ждут</th><th>Подтверждено</th><th>Расхождение</th><th>Нет в наличии</th><th>Прочее</th><th>Подтверждённых цен</th><th>КП (позиций)</th></tr></thead>
                    <tbody>
                        @foreach ($v['confirm'] ?? [] as $c)
                            @php($o = $c['outcomes']) @php($a = $c['asks'])
                            <tr>
                                <td style="min-width: 220px;"><strong>{{ \Illuminate\Support\Str::limit($c['p']->name, 40) }}</strong> <span style="color: var(--color-text-muted);">#{{ $c['p']->id }}</span></td>
                                <td>{{ $fmt($a->sent ?? 0) }} / {{ $fmt($a->answered ?? 0) }} / {{ $fmt($a->waiting ?? 0) }}</td>
                                <td><strong>{{ $fmt($o['match'] ?? 0) }}</strong></td>
                                <td>{{ $fmt($o['price_mismatch'] ?? 0) }}</td>
                                <td>{{ $fmt($o['availability'] ?? 0) }}</td>
                                <td title="не сравнить / другая база НДС / аналог">{{ $fmt(($o['inconclusive'] ?? 0) + ($o['basis_diff'] ?? 0) + ($o['match_error'] ?? 0)) }}</td>
                                <td>{{ $fmt($c['confirmed']) }} <span style="font-size: var(--text-sm); color: var(--color-text-muted);">из {{ $fmt($c['priced']) }}</span></td>
                                <td>{{ $fmt($c['kp']->offers ?? 0) }} ({{ $fmt($c['kp']->products ?? 0) }})</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding: var(--space-3) var(--space-4); color: var(--color-text-muted); font-size: var(--text-sm);">
                Сверка — КП поставщика против цены на его сайте: письмом-проверкой Гибрида и по КП обычных заявок на те же товары.
                «Подтверждено» — расхождение в пределах допуска (цена помечается на странице и повышает доверие к сайту); «Прочее» — не сравнить, другая база НДС, аналог; «Подтверждённых цен» — из всех цен на страницах товаров программы.
            </div>
        </div>

        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h3 class="card-title">Тарификация — покрытые позиции</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Программа</th><th>Последние сутки</th><th>Web / с КП / в плане</th><th>Начислено за сутки</th><th>Не списано</th><th>Последнее списание</th><th>Состояние</th></tr></thead>
                    <tbody>
                        @foreach ($v['billing'] ?? [] as $b)
                            @php($d = $b['day']) @php($s = $b['sum'])
                            <tr>
                                <td><strong>{{ $b['p']->name }}</strong> <span style="color: var(--color-text-muted);">#{{ $b['p']->id }}</span></td>
                                <td>{{ $d ? \Carbon\Carbon::parse($d->day)->format('d.m') : '—' }}</td>
                                <td>{{ $d ? $fmt($d->covered_web) . ' / ' . $fmt($d->covered_kp) . ' / ' . $fmt($d->included_web + $d->included_kp) : '—' }}
                                    @if ($d) <span style="font-size: var(--text-sm); color: var(--color-text-muted);">· {{ (float) $d->rate }} ₽, КП ×{{ (float) $d->kp_multiplier }}</span>@endif</td>
                                <td>{{ $d ? number_format((float) $d->amount, 2, ',', ' ') . ' ₽' : '—' }}</td>
                                <td>{{ number_format($s['accrued_unbilled'], 2, ',', ' ') }} ₽ <span style="font-size: var(--text-sm); color: var(--color-text-muted);">({{ $s['unbilled_days'] }} дн.)</span></td>
                                <td>{{ $s['last_charge'] ? number_format($s['last_charge']['amount'], 2, ',', ' ') . ' ₽ · ' . \Carbon\Carbon::parse($s['last_charge']['charged_at'])->setTimezone('Europe/Moscow')->format('d.m') : '—' }}</td>
                                <td>
                                    @if ($b['p']->paused_reason === 'billing') <span class="badge badge-error">пауза: нет средств</span>
                                    @elseif ($s['warning']) <span class="badge badge-warning">не хватает {{ number_format($s['warning']['due'], 0, ',', ' ') }} ₽, пауза после {{ \Carbon\Carbon::parse($s['warning']['pause_after'])->setTimezone('Europe/Moscow')->format('d.m') }}</span>
                                    @else <span class="badge badge-success">в порядке</span> @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding: var(--space-3) var(--space-4); color: var(--color-text-muted); font-size: var(--text-sm);">
                Начисление за сутки = (web × ставка + с КП × ставка × множитель) / 30 за вычетом позиций, включённых в план (сперва web). Списание — раз в 7 дней и в конце периода подписки; нет средств — предупреждение, через 3 дня — пауза.
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6);">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Расход сегодня</h3></div>
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>Ресурс</th><th>Использовано</th><th>Лимит</th></tr></thead>
                        <tbody>
                            @foreach ($v['limits'] as $res => $lim)
                                @php($b = $v['budget'][$res] ?? null)
                                <tr>
                                    <td>{{ $resRu[$res] ?? $res }}</td>
                                    <td>{{ $fmt($b->u ?? 0) }}@if (($b->rub ?? 0) > 0) · {{ $fmt($b->rub) }} ₽@endif @if (($b->usd ?? 0) > 0) · ${{ number_format($b->usd, 2) }}@endif</td>
                                    <td style="color: var(--color-text-muted);">{{ $res === 'llm_call' ? '$' . $v['llm_usd_limit'] : ($lim !== null ? $fmt($lim) : '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Проверки страниц за 24 ч</h3></div>
                <div class="table-container">
                    <table class="table">
                        <tbody>
                            @foreach ($v['checks'] as $st => $n)
                                <tr><td>{{ $checkRu[$st] ?? $st }}</td><td>{{ $fmt($n) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (count($v['fails']))
                    <div style="padding: var(--space-3) var(--space-4); font-size: var(--text-sm);">
                        <strong>Сбои по причинам:</strong>
                        @foreach ($v['fails'] as $f){{ $f->reason }} — {{ $fmt($f->c) }}@if (!$loop->last) · @endif @endforeach
                    </div>
                @endif
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Страницы поставщиков</h3></div>
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>Статус</th><th>Страниц</th><th>С ценой</th></tr></thead>
                        <tbody>
                            @foreach ($v['listings'] as $l)
                                <tr><td>{{ $l->status }}</td><td>{{ $fmt($l->c) }}</td><td>{{ $fmt($l->priced) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Гибрид: письма поставщикам и КП</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Вид запроса</th>@foreach ($statusRu as $s => $ru)<th>{{ $ru }}</th>@endforeach<th>Писем сегодня</th></tr></thead>
                    <tbody>
                        @forelse ($v['asks'] as $kind => $byStatus)
                            <tr>
                                <td><strong>{{ $kind }}</strong></td>
                                @foreach ($statusRu as $s => $ru)<td>{{ $fmt($byStatus[$s] ?? 0) }}</td>@endforeach
                                <td>{{ $fmt($v['letters_today'][$kind] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" style="color: var(--color-text-muted);">Запросов ещё не было.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="padding: var(--space-3) var(--space-4); font-size: var(--text-sm);">
                <strong>КП получено:</strong> @forelse ($v['kp'] as $src => $n){{ ['verify' => 'на проверку цены', 'on_request' => '«по запросу»', 'pool' => 'пул'][$src] ?? $src }} — {{ $n }}@if (!$loop->last) · @endif @empty нет @endforelse
                &nbsp;|&nbsp; <strong>Сверки с ценой сайта:</strong> @forelse ($v['verifications'] as $o => $n){{ ['match' => 'совпала', 'price_mismatch' => 'расхождение', 'availability' => 'нет в наличии', 'basis_diff' => 'другая база НДС', 'inconclusive' => 'не сравнить', 'match_error' => 'аналог'][$o] ?? $o }} — {{ $n }}@if (!$loop->last) · @endif @empty нет @endforelse
                &nbsp;|&nbsp; <strong>Доверие к сайтам:</strong> @foreach ($v['trust'] as $lvl => $n)ур. {{ $lvl }} — {{ $fmt($n) }}@if (!$loop->last) · @endif @endforeach
            </div>
        </div>
    @endif
@endsection
