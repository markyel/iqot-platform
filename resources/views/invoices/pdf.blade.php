<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Счёт № {{ $invoice->number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #000;
            padding: 10mm;
        }

        /* === ШАПКА С ЛОГОТИПОМ === */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        .header-left {
            display: table-cell;
            width: 50%;
            vertical-align: middle;
        }
        .header-logo {
            float: left;
            width: 55px;
            height: 55px;
            margin-right: 8px;
        }
        .header-text {
            overflow: hidden;
        }
        .header-brand {
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .header-tagline {
            font-size: 7pt;
            color: #555;
            margin-top: 2px;
        }
        .header-contacts {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: middle;
            font-size: 7.5pt;
            line-height: 1.4;
        }
        .header-contacts .company-name {
            font-weight: bold;
            font-size: 8.5pt;
            margin-bottom: 2px;
        }

        /* === БАНКОВСКИЕ РЕКВИЗИТЫ === */
        .bank-details {
            border: 1px solid #000;
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 10px;
            font-size: 7.5pt;
        }
        .bank-details td {
            border: 1px solid #000;
            padding: 2px 4px;
            vertical-align: top;
        }
        .bank-details .label {
            background: #f5f5f5;
            width: 75px;
            font-size: 7pt;
        }
        .bank-details .value {
            font-weight: bold;
            font-size: 7.5pt;
            word-break: break-all;
        }
        .bank-details .bank-name {
            font-size: 7.5pt;
        }
        .bank-details .recipient {
            font-size: 8.5pt;
            font-weight: bold;
        }
        .bank-details .account {
            font-size: 6.5pt;
            font-weight: bold;
        }

        /* === ЗАГОЛОВОК === */
        .invoice-title {
            font-size: 13pt;
            font-weight: bold;
            text-align: left;
            margin: 12px 0 10px 0;
            padding-bottom: 6px;
            border-bottom: 2px solid #000;
        }

        /* === СТОРОНЫ === */
        .parties {
            margin-bottom: 10px;
            font-size: 7.5pt;
        }
        .parties table {
            width: 100%;
        }
        .parties td {
            padding: 2px 0;
            vertical-align: top;
        }
        .parties .label {
            width: 70px;
            font-weight: bold;
        }

        /* === ТАБЛИЦА ТОВАРОВ === */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 7.5pt;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            text-align: left;
        }
        .items-table th {
            background: #e8e8e8;
            font-weight: bold;
            text-align: center;
            font-size: 7pt;
        }
        .items-table .num { width: 20px; text-align: center; }
        .items-table .name { }
        .items-table .unit { width: 30px; text-align: center; }
        .items-table .qty { width: 35px; text-align: center; }
        .items-table .price { width: 70px; text-align: right; }
        .items-table .sum { width: 80px; text-align: right; }

        /* === ИТОГИ === */
        .totals {
            width: 100%;
            margin: 8px 0;
            font-size: 8pt;
        }
        .totals td {
            padding: 2px 4px;
            text-align: right;
        }
        .totals .label {
            font-weight: bold;
        }
        .totals .total-row td {
            font-size: 10pt;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 5px;
        }

        /* === СУММА ПРОПИСЬЮ === */
        .amount-words {
            margin: 10px 0;
            padding: 6px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            font-size: 7.5pt;
        }

        /* === ПРИМЕЧАНИЕ === */
        .note {
            margin-top: 10px;
            padding: 6px;
            background: #fffde7;
            border: 1px solid #ffd54f;
            font-size: 7pt;
            line-height: 1.3;
        }

        /* === ПОДПИСИ === */
        .signatures {
            margin-top: 15px;
            page-break-inside: avoid;
            font-size: 7.5pt;
        }
        .signatures table {
            width: 100%;
        }
        .signatures td {
            padding: 6px 0;
            vertical-align: bottom;
        }
        .signatures .sign-line {
            border-bottom: 1px solid #000;
            width: 130px;
            display: inline-block;
            margin: 0 6px;
        }
        .signatures .sign-label {
            font-size: 6.5pt;
            color: #666;
        }
        .signatures .signature-img {
            max-width: 150px;
            max-height: 50px;
            display: inline-block;
            vertical-align: middle;
        }
        .signatures .stamp-img {
            max-width: 160px;
            max-height: 160px;
            margin-top: 5px;
        }
    </style>
</head>
<body>

{{-- === ШАПКА === --}}
<div class="header">
    <div class="header-left">
        <img src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyBpZD0iX9Ch0LvQvtC5XzIiIGRhdGEtbmFtZT0i0KHQu9C+0LkgMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2aWV3Qm94PSIwIDAgMTE5LjgxIDExOS44MSI+CiAgPGcgaWQ9Il/QodC70L7QuV8xLTIiIGRhdGEtbmFtZT0i0KHQu9C+0LkgMSI+CiAgICA8cGF0aCBkPSJNNTkuOSwwQzI2LjgyLDAsMCwyNi44MiwwLDU5LjlzMjYuODIsNTkuOSw1OS45LDU5LjksNTkuOS0yNi44Miw1OS45LTU5LjlTOTIuOTksMCw1OS45LDBaTTIyLjkxLDYwLjVjMC0yMC40MywxNi41Ni0zNi45OSwzNi45OS0zNi45OXMzNi45OSwxNi41NiwzNi45OSwzNi45OWMwLDkuNy0zLjc0LDE4LjUyLTkuODQsMjUuMTJsLTE3LjA1LTE1LjM5LTMuMzYsMy43MywxNi43NSwxNS4xMmMtNi4zOSw1LjI2LTE0LjU3LDguNDEtMjMuNDksOC40MS0yMC40MywwLTM2Ljk5LTE2LjU2LTM2Ljk5LTM2Ljk5WiIvPgogIDwvZz4KPC9zdmc+Cg==" class="header-logo" alt="IQOT">
        <div class="header-text">
            <div class="header-brand">IQOT</div>
            <div class="header-tagline">Сервис сбора коммерческих предложений</div>
        </div>
    </div>
    <div class="header-contacts">
        <div class="company-name">{{ $seller->name }}</div>
        <div>ИНН {{ $seller->inn }}</div>
        <div>{{ $seller->address }}</div>
        <div style="margin-top: 4px;">
            iqot.ru
            @if($seller->email)
            | {{ $seller->email }}
            @endif
        </div>
    </div>
</div>

{{-- === БАНКОВСКИЕ РЕКВИЗИТЫ === --}}
<table class="bank-details">
    <tr>
        <td class="label" rowspan="2">Банк получателя</td>
        <td class="bank-name" colspan="3" rowspan="2">{{ $seller->bank_name }}</td>
        <td class="label">БИК</td>
        <td class="value">{{ $seller->bank_bik }}</td>
    </tr>
    <tr>
        <td class="label">Корр. счёт</td>
        <td class="value">{{ $seller->bank_corr_account }}</td>
    </tr>
    <tr>
        <td class="label">ИНН</td>
        <td class="value">{{ $seller->inn }}</td>
        <td class="label" rowspan="2">Получатель</td>
        <td class="recipient" rowspan="2">{{ $seller->name }}</td>
        <td class="label">Расч. счёт</td>
        <td class="account">{{ $seller->bank_account }}</td>
    </tr>
    <tr>
        <td class="label">{{ $seller->ogrnip ? 'ОГРНИП' : 'ОГРН' }}</td>
        <td class="value">{{ $seller->ogrnip ?? $seller->ogrn }}</td>
        <td colspan="2"></td>
    </tr>
</table>

{{-- === ЗАГОЛОВОК СЧЁТА === --}}
<div class="invoice-title">
    Счёт на оплату № {{ $invoice->number }} от {{ $invoice->invoice_date->format('d.m.Y') }} г.
</div>

{{-- === СТОРОНЫ === --}}
<div class="parties">
    <table>
        <tr>
            <td class="label">Поставщик:</td>
            <td>{{ $seller->name }}, ИНН {{ $seller->inn }}@if($seller->ogrnip), ОГРНИП {{ $seller->ogrnip }}@endif, {{ $seller->address }}</td>
        </tr>
        <tr>
            <td class="label">Покупатель:</td>
            <td>{{ $buyer->company_name ?? $buyer->name }}, ИНН {{ $buyer->inn ?? 'не указан' }}@if($buyer->kpp), КПП {{ $buyer->kpp }}@endif, {{ $buyer->address ?? 'адрес не указан' }}</td>
        </tr>
    </table>
</div>

{{-- === ТАБЛИЦА УСЛУГ === --}}
<table class="items-table">
    <thead>
        <tr>
            <th class="num">№</th>
            <th class="name">Наименование товара, работ, услуг</th>
            <th class="unit">Ед.</th>
            <th class="qty">Кол-во</th>
            <th class="price">Цена</th>
            <th class="sum">Сумма</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $index => $item)
        <tr>
            <td class="num">{{ $index + 1 }}</td>
            <td class="name">{{ $item->name }}</td>
            <td class="unit">{{ $item->unit }}</td>
            <td class="qty">{{ $item->quantity }}</td>
            <td class="price">{{ number_format($item->price, 2, ',', ' ') }}</td>
            <td class="sum">{{ number_format($item->sum, 2, ',', ' ') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- === ИТОГИ === --}}
<table class="totals">
    <tr>
        <td></td>
        <td class="label" style="width: 130px;">Итого:</td>
        <td style="width: 130px;">{{ number_format($invoice->subtotal, 2, ',', ' ') }}</td>
    </tr>
    @if($invoice->vat_rate > 0)
    <tr>
        <td></td>
        <td class="label">В том числе НДС ({{ number_format($invoice->vat_rate, 0) }}%):</td>
        <td>{{ number_format($invoice->vat_amount, 2, ',', ' ') }}</td>
    </tr>
    @else
    <tr>
        <td></td>
        <td class="label">Без НДС</td>
        <td>—</td>
    </tr>
    @endif
    <tr class="total-row">
        <td></td>
        <td class="label">Всего к оплате:</td>
        <td>{{ number_format($invoice->total, 2, ',', ' ') }}</td>
    </tr>
</table>

{{-- === СУММА ПРОПИСЬЮ === --}}
<div class="amount-words">
    Всего наименований {{ $invoice->items->count() }}, на сумму <strong>{{ number_format($invoice->total, 2, ',', ' ') }} руб.</strong>
</div>

{{-- === ПРИМЕЧАНИЕ === --}}
<div class="note">
    <strong>Назначение платежа:</strong> {{ $invoice->description }}. Счёт № {{ $invoice->number }} от {{ $invoice->invoice_date->format('d.m.Y') }}.
    @if($invoice->vat_rate > 0)
        В том числе НДС ({{ number_format($invoice->vat_rate, 0) }}%) {{ number_format($invoice->vat_amount, 2, ',', ' ') }} руб.
    @else
        Без НДС.
    @endif
</div>

{{-- === ПОДПИСИ === --}}
<div class="signatures">
    <div style="margin-bottom: 15px;">
        <strong>Руководитель</strong><br><br>
        @if($seller->signature_image)
            <img src="{{ storage_path('app/public/' . $seller->signature_image) }}" class="signature-img" alt="Подпись">
        @else
            <span class="sign-line"></span>
        @endif
        / {{ $seller->director_short ?? '_________' }} /<br>
        <span class="sign-label">подпись</span>
    </div>
    <div style="margin-top: 12px; font-size: 7.5pt; color: #666; display: flex; justify-content: space-between; align-items: center;">
        <span>М.П. (при наличии)</span>
        @if($seller->stamp_image)
            <img src="{{ storage_path('app/public/' . $seller->stamp_image) }}" class="stamp-img" alt="Печать">
        @endif
    </div>
</div>

</body>
</html>
