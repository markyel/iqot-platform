<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Акт к счёту № {{ $invoice->number }}</title>
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

        .header {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        .header-left {
            display: table-cell;
            width: 50%;
            vertical-align: middle;
        }
        .header-right {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: middle;
            font-size: 7.5pt;
        }
        .header-brand {
            font-size: 12pt;
            font-weight: bold;
        }
        .company-name {
            font-weight: bold;
            font-size: 8.5pt;
        }

        .doc-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin: 20px 0 15px 0;
        }

        .doc-info {
            margin-bottom: 15px;
            font-size: 9pt;
        }

        .parties {
            margin-bottom: 15px;
            font-size: 8.5pt;
            line-height: 1.5;
        }
        .party-block {
            margin-bottom: 8px;
        }
        .party-label {
            font-weight: bold;
        }

        .services-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 8pt;
        }
        .services-table th,
        .services-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
        }
        .services-table th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .services-table .num-col {
            width: 30px;
            text-align: center;
        }
        .services-table .date-col {
            width: 70px;
            text-align: center;
        }
        .services-table .amount-col {
            width: 80px;
            text-align: right;
        }

        .totals {
            text-align: right;
            font-size: 9pt;
            margin-bottom: 20px;
        }
        .totals-row {
            margin: 3px 0;
        }
        .totals-label {
            display: inline-block;
            width: 200px;
            font-weight: bold;
        }
        .total-final {
            font-size: 10pt;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 2px solid #000;
        }

        .signatures {
            margin-top: 30px;
            font-size: 8.5pt;
        }
        .signature-block {
            margin-bottom: 15px;
        }
        .signature-line {
            display: inline-block;
            width: 150px;
            border-bottom: 1px solid #000;
            margin: 0 10px;
        }
    </style>
</head>
<body>

<!-- ШАПКА -->
<div class="header">
    <div class="header-left">
        <div class="header-brand">IQOT</div>
        <div style="font-size: 7pt; color: #555;">Сервис мониторинга цен</div>
    </div>
    <div class="header-right">
        <div class="company-name">{{ $seller->company_name ?? 'ИП Иванов И.И.' }}</div>
        @if($seller->inn)
        <div>ИНН {{ $seller->inn }}</div>
        @endif
        @if($seller->phone)
        <div>{{ $seller->phone }}</div>
        @endif
        @if($seller->email)
        <div>{{ $seller->email }}</div>
        @endif
    </div>
</div>

<!-- НАЗВАНИЕ ДОКУМЕНТА -->
<div class="doc-title">
    АКТ ОКАЗАННЫХ УСЛУГ<br>
    к счёту № {{ $invoice->number }} от {{ $invoice->invoice_date->format('d.m.Y') }}
</div>

<div class="doc-info">
    г. {{ $seller->city ?? 'Москва' }}<br>
    Дата акта: {{ now()->format('d.m.Y') }}
</div>

<!-- СТОРОНЫ -->
<div class="parties">
    <div class="party-block">
        <span class="party-label">Исполнитель:</span>
        {{ $seller->company_name ?? 'ИП Иванов И.И.' }}
        @if($seller->inn), ИНН {{ $seller->inn }}@endif
        @if($seller->address), {{ $seller->address }}@endif
    </div>

    <div class="party-block">
        <span class="party-label">Заказчик:</span>
        {{ $buyer->company ?? $buyer->name }}
        @if($buyer->inn), ИНН {{ $buyer->inn }}@endif
    </div>
</div>

<div style="margin-bottom: 15px; font-size: 8.5pt; line-height: 1.6;">
    Исполнитель оказал, а Заказчик принял следующие услуги по предоставлению доступа к информационному сервису IQOT в период с {{ $invoice->paid_at->format('d.m.Y') }} по {{ now()->format('d.m.Y') }}:
</div>

<!-- ТАБЛИЦА УСЛУГ -->
<table class="services-table">
    <thead>
        <tr>
            <th class="num-col">№</th>
            <th class="date-col">Дата</th>
            <th>Наименование услуги</th>
            <th class="amount-col">Сумма, ₽</th>
        </tr>
    </thead>
    <tbody>
        @foreach($actTransactions as $index => $transaction)
        <tr>
            <td class="num-col">{{ $index + 1 }}</td>
            <td class="date-col">{{ $transaction['date']->format('d.m.Y') }}</td>
            <td>{{ $transaction['description'] }}</td>
            <td class="amount-col">{{ number_format($transaction['amount'], 2, ',', ' ') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<!-- ИТОГИ -->
<div class="totals">
    <div class="totals-row">
        <span class="totals-label">Итого:</span>
        <span>{{ number_format($totalSpent, 2, ',', ' ') }} ₽</span>
    </div>
    @php
        $vatAmount = $totalSpent * ($invoice->vat_rate / 100);
        $totalWithVat = $totalSpent + $vatAmount;
    @endphp
    @if($invoice->vat_rate > 0)
    <div class="totals-row">
        <span class="totals-label">НДС ({{ number_format($invoice->vat_rate, 0) }}%):</span>
        <span>{{ number_format($vatAmount, 2, ',', ' ') }} ₽</span>
    </div>
    <div class="totals-row total-final">
        <span class="totals-label">Всего к оплате:</span>
        <span>{{ number_format($totalWithVat, 2, ',', ' ') }} ₽</span>
    </div>
    @else
    <div class="totals-row">
        <span class="totals-label">Без НДС</span>
    </div>
    <div class="totals-row total-final">
        <span class="totals-label">Всего к оплате:</span>
        <span>{{ number_format($totalSpent, 2, ',', ' ') }} ₽</span>
    </div>
    @endif
</div>

<div style="margin-bottom: 20px; font-size: 8.5pt;">
    Услуги оказаны полностью и в срок. Заказчик претензий по объему, качеству и срокам оказания услуг не имеет.
</div>

<!-- ПОДПИСИ -->
<div class="signatures">
    <div class="signature-block">
        <strong>Исполнитель:</strong><br>
        {{ $seller->company_name ?? 'ИП Иванов И.И.' }}<br><br>
        <span class="signature-line"></span> / <span class="signature-line"></span><br>
        <span style="font-size: 7pt; color: #666;">&nbsp;&nbsp;&nbsp;&nbsp;(подпись)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(Ф.И.О.)</span>
    </div>

    <div class="signature-block">
        <strong>Заказчик:</strong><br>
        {{ $buyer->company ?? $buyer->name }}<br><br>
        <span class="signature-line"></span> / <span class="signature-line"></span><br>
        <span style="font-size: 7pt; color: #666;">&nbsp;&nbsp;&nbsp;&nbsp;(подпись)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(Ф.И.О.)</span>
    </div>
</div>

</body>
</html>
