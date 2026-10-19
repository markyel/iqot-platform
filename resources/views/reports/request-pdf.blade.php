<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Отчёт по заявке {{ $r->request_number }}</title>
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; color: #222; padding: 18px 22px; }
        h1 { font-size: 15pt; margin-bottom: 4px; }
        .muted { color: #666; }
        .summary { margin: 8px 0 14px; }
        .summary span { margin-right: 18px; }
        .item { margin-top: 12px; page-break-inside: avoid; }
        .item-head { background: #f1f3f5; padding: 5px 7px; font-weight: bold; }
        .item-head .meta { font-weight: normal; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 3px; }
        th { text-align: left; font-size: 8pt; color: #555; border-bottom: 1px solid #ccc; padding: 3px 5px; }
        td { border-bottom: 1px solid #eee; padding: 3px 5px; vertical-align: top; }
        td.num { text-align: right; white-space: nowrap; }
        tr.best td { background: #eaf7ee; }
        .none { color: #999; padding: 4px 7px; }
    </style>
</head>
<body>
    @php
        $items = $r->items;
        $withOffers = $items->filter(fn ($i) => $i->offers->isNotEmpty())->count();
        $suppliers = $items->flatMap->offers->pluck('supplier_id')->unique()->count();
        $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ');
    @endphp
    <h1>Отчёт по заявке {{ $r->request_number }}</h1>
    <div class="muted">{{ $customerName }} · заявка от {{ optional($r->created_at)->timezone('Europe/Moscow')->format('d.m.Y') }} · сформирован {{ $generatedAt->format('d.m.Y H:i') }} МСК</div>
    <div class="summary">
        <span>Позиций: <b>{{ $items->count() }}</b></span>
        <span>С предложениями: <b>{{ $withOffers }}</b></span>
        <span>Поставщиков ответили ценой: <b>{{ $suppliers }}</b></span>
    </div>

    @foreach($items as $item)
        <div class="item">
            <div class="item-head">
                {{ $item->position_number }}. {{ $item->name }}
                <span class="meta">
                    @if($item->brand) · {{ $item->brand }} @endif
                    @if($item->article) · арт. {{ $item->article }} @endif
                    · {{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', ''), '0'), ',') }} {{ $item->unit }}
                </span>
            </div>
            @if($item->offers->isEmpty())
                <div class="none">Предложений с ценой нет</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th style="width:26%">Поставщик</th>
                            <th style="width:24%">Контакты</th>
                            <th class="num">Цена за ед.</th>
                            <th class="num">Сумма</th>
                            <th>НДС</th>
                            <th>Срок</th>
                            <th style="width:18%">Условия / примечание</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($item->offers as $k => $offer)
                            @php $s = $offer->supplier; @endphp
                            <tr class="{{ $k === 0 ? 'best' : '' }}">
                                <td>{{ $s->name ?? '—' }}@if($s?->contact_person)<br><span class="muted">{{ $s->contact_person }}</span>@endif</td>
                                <td>{{ $s->email ?? '' }}@if($s?->phone)<br>{{ $s->phone }}@endif @if($s?->website)<br>{{ $s->website }}@endif</td>
                                <td class="num">{{ $money($offer->price_per_unit) }} {{ $offer->currency ?: 'RUB' }}</td>
                                <td class="num">{{ $money($offer->total_price) }}</td>
                                <td>{{ $offer->price_includes_vat === null ? '—' : ($offer->price_includes_vat ? 'с НДС' : 'без НДС') }}</td>
                                <td>{{ $offer->delivery_days ? $offer->delivery_days . ' дн.' : '—' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit(trim(($offer->payment_terms ? $offer->payment_terms . '. ' : '') . ($offer->notes ?? '')), 160) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
</body>
</html>
