@extends('layouts.cabinet')

@section('title', 'Групповое добавление отправителей')

@section('content')
@php
    $activeTab = $activeTab ?? match (true) {
        $errors->has('domains') || (old('domains') && $errors->has('excel')) => 'generator',
        $errors->hasAny(['credentials', 'smtp', 'imap', 'smtp_enc', 'imap_enc', 'excel']) => 'wizard',
        default => 'manual',
    };
@endphp
<div style="max-width: 900px; margin: 0 auto;">
    <x-page-header
        title="Групповое добавление отправителей"
        description="Массовое добавление отправителей рассылки (таблица senders). Аналог Telegram-команды /addmail, но пачкой."
    />

    @if(isset($summary))
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="list-checks" class="icon-md"></i>
                    Результат импорта
                </h2>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-wrap: wrap; gap: var(--space-3); margin-bottom: var(--space-4);">
                    <span class="badge" style="background: var(--success-bg, #e6f4ea); color: var(--success, #1e7e34); padding: 0.4rem 0.8rem; border-radius: 6px;">
                        Создано: {{ $summary['created'] }}
                    </span>
                    <span class="badge" style="background: var(--surface-secondary); color: var(--text-secondary); padding: 0.4rem 0.8rem; border-radius: 6px;">
                        Пропущено: {{ $summary['skipped'] }}
                    </span>
                    <span class="badge" style="background: var(--danger-bg, #fde8e8); color: var(--danger, #b02a37); padding: 0.4rem 0.8rem; border-radius: 6px;">
                        Ошибок: {{ $summary['failed'] }}
                    </span>
                </div>

                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 1px solid var(--border);">
                                <th style="padding: 0.5rem;">#</th>
                                <th style="padding: 0.5rem;">Статус</th>
                                <th style="padding: 0.5rem;">Email</th>
                                <th style="padding: 0.5rem;">Организация</th>
                                <th style="padding: 0.5rem;">Сообщение</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($summary['rows'] as $row)
                                @php
                                    $color = match($row['status']) {
                                        'created' => '#1e7e34',
                                        'skipped' => 'var(--text-secondary)',
                                        default => '#b02a37',
                                    };
                                    $label = match($row['status']) {
                                        'created' => '✓ Создан',
                                        'skipped' => '— Пропущен',
                                        default => '✗ Ошибка',
                                    };
                                @endphp
                                <tr style="border-bottom: 1px solid var(--border-light, #eee);">
                                    <td style="padding: 0.5rem;">{{ $row['index'] }}</td>
                                    <td style="padding: 0.5rem; color: {{ $color }}; font-weight: 600; white-space: nowrap;">{{ $label }}</td>
                                    <td style="padding: 0.5rem; font-family: monospace;">{{ $row['email'] ?: '—' }}</td>
                                    <td style="padding: 0.5rem;">{{ $row['organization'] ?? '—' }}</td>
                                    <td style="padding: 0.5rem; color: var(--text-secondary);">{{ $row['message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Переключатель режимов --}}
    <div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-4); border-bottom: 1px solid var(--border);">
        <button type="button" class="senders-tab-btn" data-tab="wizard"
            style="padding: 0.6rem 1rem; border: none; background: none; cursor: pointer; font-weight: 600; border-bottom: 2px solid transparent; color: var(--text-secondary);">
            <i data-lucide="sparkles" class="icon-sm" style="vertical-align: -2px;"></i> Помощник
        </button>
        <button type="button" class="senders-tab-btn" data-tab="generator"
            style="padding: 0.6rem 1rem; border: none; background: none; cursor: pointer; font-weight: 600; border-bottom: 2px solid transparent; color: var(--text-secondary);">
            <i data-lucide="wand-2" class="icon-sm" style="vertical-align: -2px;"></i> Генератор
        </button>
        <button type="button" class="senders-tab-btn" data-tab="manual"
            style="padding: 0.6rem 1rem; border: none; background: none; cursor: pointer; font-weight: 600; border-bottom: 2px solid transparent; color: var(--text-secondary);">
            <i data-lucide="keyboard" class="icon-sm" style="vertical-align: -2px;"></i> Вручную
        </button>
    </div>

    {{-- ───────── Режим «Помощник» ───────── --}}
    <div class="senders-tab-pane" data-pane="wizard" style="{{ $activeTab === 'wizard' ? '' : 'display: none;' }}">
        <div class="alert alert-info" style="margin-bottom: var(--space-6);">
            <div style="display: flex; align-items: start; gap: var(--space-3);">
                <i data-lucide="info" class="icon-md"></i>
                <div>
                    <strong>Как это работает</strong>
                    <p style="margin-top: var(--space-1); margin-bottom: 0;">
                        Вставьте список учёток построчно в формате <code>email password</code> или
                        <code>email:password</code> и укажите
                        общие <code>SMTP</code>/<code>IMAP</code> на всю пачку. Загрузите выгрузку организаций
                        ExportBase (<code>.xlsx</code>). Для каждой учётки берётся следующая
                        <strong>неиспользованная</strong> организация из файла (ИНН которой ещё нет в базе),
                        а ФИО-контакт, отдел, стиль письма и личный телефон дописывает AI.
                        @if(!is_null($totalSenders))
                            <br>Сейчас в базе отправителей: <strong>{{ $totalSenders }}</strong>.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="sparkles" class="icon-md"></i>
                    Помощник создания отправителей
                </h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.senders.import.wizard') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="form-group">
                        <label class="form-label" for="credentials">Учётки (email password или email:password построчно)</label>
                        <textarea
                            id="credentials"
                            name="credentials"
                            rows="10"
                            class="input @error('credentials') is-invalid @enderror"
                            style="font-family: monospace; font-size: 0.875rem;"
                            placeholder="evgen@inmailbox.ru PdNOz5HCV2!q&#10;orion@inmailbox.ru S3cr3t!pass&#10;hermes@inmailbox.ru Qwerty987#"
                            required
                        >{{ old('credentials', $wizardInput['credentials'] ?? '') }}</textarea>
                        @error('credentials')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-help">
                            Одна строка — один ящик: сначала email, затем через пробел пароль.
                            <code>user</code> = email.
                        </small>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        <div class="form-group">
                            <label class="form-label" for="smtp">SMTP (host:port)</label>
                            <input type="text" id="smtp" name="smtp"
                                class="input @error('smtp') is-invalid @enderror"
                                value="{{ old('smtp', $wizardInput['smtp'] ?? 'smtp.beget.com:465') }}"
                                placeholder="smtp.beget.com:465" required>
                            @error('smtp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="imap">IMAP (host:port)</label>
                            <input type="text" id="imap" name="imap"
                                class="input @error('imap') is-invalid @enderror"
                                value="{{ old('imap', $wizardInput['imap'] ?? 'imap.beget.com:993') }}"
                                placeholder="imap.beget.com:993" required>
                            @error('imap')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        <div class="form-group">
                            <label class="form-label" for="smtp_enc">Шифрование SMTP</label>
                            <select id="smtp_enc" name="smtp_enc" class="input">
                                <option value="">авто (по порту)</option>
                                @foreach(['ssl', 'tls', 'none'] as $enc)
                                    <option value="{{ $enc }}" @selected(old('smtp_enc', $wizardInput['smtp_enc'] ?? '') === $enc)>{{ $enc }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="imap_enc">Шифрование IMAP</label>
                            <select id="imap_enc" name="imap_enc" class="input">
                                <option value="">авто (по порту)</option>
                                @foreach(['ssl', 'tls', 'none'] as $enc)
                                    <option value="{{ $enc }}" @selected(old('imap_enc', $wizardInput['imap_enc'] ?? '') === $enc)>{{ $enc }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="excel">Выгрузка организаций (.xlsx, ExportBase)</label>
                        <input type="file" id="excel" name="excel" accept=".xlsx"
                            class="input @error('excel') is-invalid @enderror" required>
                        @error('excel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="form-help">
                            Формат ExportBase: заголовки в первой строке, реклама пропускается,
                            берутся строки с корректным ИНН. До 10 МБ.
                        </small>
                    </div>

                    <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
                        <x-button type="submit" variant="accent" icon="sparkles">
                            Создать отправителей
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ───────── Режим «Генератор» ───────── --}}
    <div class="senders-tab-pane" data-pane="generator" style="{{ $activeTab === 'generator' ? '' : 'display: none;' }}">
        <div class="alert alert-info" style="margin-bottom: var(--space-6);">
            <div style="display: flex; align-items: start; gap: var(--space-3);">
                <i data-lucide="info" class="icon-md"></i>
                <div>
                    <strong>Как это работает</strong>
                    <p style="margin-top: var(--space-1); margin-bottom: 0;">
                        Загрузите выгрузку организаций ExportBase (<code>.xlsx</code>) и укажите список
                        доступных доменов почты. На каждую <strong>неиспользованную</strong> организацию
                        система придумает адрес (логин из ФИО директора / названия компании / отдела),
                        пароль и назначит домен по кругу. В превью отметьте галочками, какие адреса завести —
                        они добавятся в систему (ФИО-контакт и стиль письма допишет AI), а внизу появится
                        список <code>email&nbsp;пароль</code> по доменам, чтобы вы создали ящики на хостинге.
                        SMTP/IMAP по умолчанию выводятся из домена (<code>smtp.&lt;домен&gt;</code> /
                        <code>mail.&lt;домен&gt;</code>). Если почтовый сервер домена другой (напр. домен на beget) —
                        укажите его в строке через <code>|</code>:
                        <code>домен&nbsp;|&nbsp;smtp.host:port&nbsp;|&nbsp;imap.host:port</code>.
                        Назначенный сервер виден в колонке SMTP/IMAP превью. Добавление идёт в фоне —
                        страница статуса покажет прогресс и список ящиков.
                    </p>
                </div>
            </div>
        </div>

        @isset($genStatusMissing)
            <div class="alert alert-warning" style="margin-bottom: var(--space-6);">
                Сессия фонового импорта не найдена или истекла. Сгенерируйте адреса заново.
            </div>
        @endisset

        @isset($genStatus)
            @php
                $gs = $genStatus;
                $pending = max(0, $gs['total'] - $gs['processed']);
                $pct = $gs['total'] > 0 ? (int) round($gs['processed'] / $gs['total'] * 100) : 100;
            @endphp
            <div class="card" style="margin-bottom: var(--space-6);" data-gen-status data-finished="{{ $gs['finished'] ? '1' : '0' }}">
                <div class="card-header">
                    <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                        <i data-lucide="{{ $gs['finished'] ? 'check-circle-2' : 'loader' }}" class="icon-md"></i>
                        {{ $gs['finished'] ? 'Импорт завершён' : 'Идёт добавление в фоне…' }}
                    </h2>
                </div>
                <div class="card-body">
                    <div style="display: flex; flex-wrap: wrap; gap: var(--space-3); margin-bottom: var(--space-4);">
                        <span class="badge" style="background: var(--success-bg, #e6f4ea); color: var(--success, #1e7e34); padding: 0.4rem 0.8rem; border-radius: 6px;">
                            Создано: {{ $gs['created'] }}
                        </span>
                        <span class="badge" style="background: var(--surface-secondary); color: var(--text-secondary); padding: 0.4rem 0.8rem; border-radius: 6px;">
                            Пропущено: {{ $gs['skipped'] }}
                        </span>
                        <span class="badge" style="background: var(--danger-bg, #fde8e8); color: var(--danger, #b02a37); padding: 0.4rem 0.8rem; border-radius: 6px;">
                            Ошибок: {{ $gs['failed'] }}
                        </span>
                        <span class="badge" style="background: var(--surface-secondary); color: var(--text-secondary); padding: 0.4rem 0.8rem; border-radius: 6px;">
                            В очереди: {{ $pending }}
                        </span>
                    </div>

                    <div style="background: var(--surface-secondary, #eee); border-radius: 6px; height: 10px; overflow: hidden;">
                        <div style="width: {{ $pct }}%; height: 100%; background: var(--accent, #2563eb); transition: width .3s;"></div>
                    </div>
                    <p style="margin: var(--space-2) 0 0; color: var(--text-secondary);">
                        {{ $gs['processed'] }} из {{ $gs['total'] }} ({{ $pct }}%).
                        @unless($gs['finished'])
                            Обновляется автоматически…
                        @endunless
                    </p>

                    @if(count($gs['rows']) > 0)
                        <div style="overflow-x: auto; margin-top: var(--space-4);">
                            <table class="table" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="text-align: left; border-bottom: 1px solid var(--border);">
                                        <th style="padding: 0.5rem;">Статус</th>
                                        <th style="padding: 0.5rem;">Email</th>
                                        <th style="padding: 0.5rem;">Сообщение</th>
                                        <th style="padding: 0.5rem;">SMTP/IMAP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($gs['rows'] as $row)
                                        @php
                                            $isCreated = ($row['status'] ?? '') === 'created';
                                            $color = match($row['status'] ?? '') {
                                                'created' => '#1e7e34',
                                                'skipped' => 'var(--text-secondary)',
                                                default => '#b02a37',
                                            };
                                            $label = match($row['status'] ?? '') {
                                                'created' => '✓ Создан',
                                                'skipped' => '— Пропущен',
                                                default => '✗ Ошибка',
                                            };
                                        @endphp
                                        <tr style="border-bottom: 1px solid var(--border-light, #eee);" @if($isCreated && !empty($row['sender_id'])) data-sender-id="{{ $row['sender_id'] }}" @endif>
                                            <td style="padding: 0.5rem; color: {{ $color }}; font-weight: 600; white-space: nowrap;">{{ $label }}</td>
                                            <td style="padding: 0.5rem; font-family: monospace;">{{ $row['email'] ?: '—' }}</td>
                                            <td style="padding: 0.5rem; color: var(--text-secondary);">{{ $row['message'] ?? '' }}</td>
                                            <td data-conn style="padding: 0.5rem; white-space: nowrap; color: var(--text-secondary);">{{ $isCreated ? '—' : '·' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if($gs['finished'] && $gs['created'] > 0)
                        <div style="margin-top: var(--space-4); display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap;">
                            <button type="button" id="checkConnBtn"
                                    data-url="{{ route('admin.senders.import.check-connectivity') }}"
                                    data-csrf="{{ csrf_token() }}"
                                    class="btn btn-secondary" style="padding: 0.5rem 1rem; border: 1px solid var(--border); border-radius: 6px; cursor: pointer; background: var(--surface-secondary); font-weight: 600;">
                                <i data-lucide="plug-zap" class="icon-sm"></i> Проверить подключаемость
                            </button>
                            <span id="checkConnSummary" style="color: var(--text-secondary);"></span>
                        </div>
                        <script>
                            (function () {
                                var btn = document.getElementById('checkConnBtn');
                                if (!btn || btn.dataset.bound) { return; }
                                btn.dataset.bound = '1';
                                var summary = document.getElementById('checkConnSummary');
                                btn.addEventListener('click', async function () {
                                    var rows = Array.prototype.slice.call(document.querySelectorAll('tr[data-sender-id]'));
                                    if (!rows.length) { return; }
                                    btn.disabled = true;
                                    var orig = btn.innerHTML;
                                    btn.textContent = 'Проверяю…';
                                    var ok = 0, bad = 0;
                                    for (var i = 0; i < rows.length; i++) {
                                        var tr = rows[i];
                                        var cell = tr.querySelector('[data-conn]');
                                        if (cell) { cell.innerHTML = '<span style="color:var(--text-secondary)">⏳</span>'; }
                                        try {
                                            var resp = await fetch(btn.dataset.url, {
                                                method: 'POST',
                                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': btn.dataset.csrf, 'Accept': 'application/json' },
                                                body: JSON.stringify({ sender_id: tr.dataset.senderId })
                                            });
                                            var data = await resp.json();
                                            if (data && data.ok) {
                                                ok++;
                                                if (cell) { cell.innerHTML = '<span style="color:#1e7e34;font-weight:600">✓ ок</span>'; }
                                            } else {
                                                bad++;
                                                var err = (data && data.error) ? data.error : 'ошибка';
                                                if (cell) { cell.innerHTML = '<span style="color:#b02a37;font-weight:600" title="' + err.replace(/"/g, '&quot;') + '">✗ ' + err + '</span>'; }
                                            }
                                        } catch (e) {
                                            bad++;
                                            if (cell) { cell.innerHTML = '<span style="color:#b02a37;font-weight:600">✗ сеть</span>'; }
                                        }
                                        if (summary) { summary.textContent = 'Рабочих: ' + ok + ' · Проблемных: ' + bad + ' · осталось ' + (rows.length - i - 1); }
                                    }
                                    btn.innerHTML = orig;
                                    btn.disabled = false;
                                    if (window.lucide) { window.lucide.createIcons(); }
                                    if (summary) { summary.textContent = 'Готово. Рабочих: ' + ok + ' · Проблемных: ' + bad; }
                                });
                            })();
                        </script>
                    @endif
                </div>
            </div>
        @endisset

        <div class="card">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="wand-2" class="icon-md"></i>
                    Генерация адресов
                </h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.senders.import.generate') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="form-group">
                        <label class="form-label" for="domains">Домены почты (по одному в строке)</label>
                        <textarea
                            id="domains"
                            name="domains"
                            rows="4"
                            class="input @error('domains') is-invalid @enderror"
                            style="font-family: monospace; font-size: 0.875rem;"
                            placeholder="wwwsend.ru&#10;tomailbox.ru, ooomail.ru&#10;mydomain.ru | smtp.beget.com:465 | imap.beget.com:993"
                            required
                        >{{ old('domains', $generatorInput['domains'] ?? '') }}</textarea>
                        @error('domains')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-help">
                            Простые домены — через перенос строки, запятую или пробел; SMTP/IMAP выведутся
                            автоматически (<code>smtp.&lt;домен&gt;:465</code> / <code>mail.&lt;домен&gt;:993</code>).
                            Чтобы задать сервер вручную — отдельной строкой
                            <code>домен | smtp.host:port | imap.host:port</code> (порт необязателен).
                            Домены чередуются по кругу.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="excel_gen">Выгрузка организаций (.xlsx, ExportBase)</label>
                        <input type="file" id="excel_gen" name="excel" accept=".xlsx"
                            class="input @error('excel') is-invalid @enderror" required>
                        @error('excel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="form-help">
                            На каждую организацию (ИНН которой ещё нет в базе) — один адрес. До 10 МБ.
                        </small>
                    </div>

                    <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
                        <x-button type="submit" variant="accent" icon="wand-2">
                            Сгенерировать
                        </x-button>
                    </div>
                </form>
            </div>
        </div>

        @isset($generated)
            @if(count($generated) === 0)
                <div class="alert alert-warning" style="margin-top: var(--space-4);">
                    Нечего генерировать: нет свободных организаций в файле или не распознаны домены.
                </div>
            @else
                <div class="card" style="margin-top: var(--space-6);">
                    <div class="card-header">
                        <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                            <i data-lucide="list-checks" class="icon-md"></i>
                            Кандидаты ({{ count($generated) }})
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.senders.import.generate.add') }}">
                            @csrf
                            <div style="overflow-x: auto;">
                                <table class="table" style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="text-align: left; border-bottom: 1px solid var(--border);">
                                            <th style="padding: 0.5rem;"><input type="checkbox" id="gen-all" checked></th>
                                            <th style="padding: 0.5rem;">Email</th>
                                            <th style="padding: 0.5rem;">Пароль</th>
                                            <th style="padding: 0.5rem;">SMTP / IMAP</th>
                                            <th style="padding: 0.5rem;">Организация</th>
                                            <th style="padding: 0.5rem;">ИНН</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($generated as $i => $g)
                                            <tr style="border-bottom: 1px solid var(--border-light, #eee);">
                                                <td style="padding: 0.5rem;">
                                                    <input type="checkbox" class="gen-row" name="selected[]" value="{{ $i }}" checked>
                                                    <input type="hidden" name="blocks[{{ $i }}]" value="{{ json_encode($g, JSON_UNESCAPED_UNICODE) }}">
                                                </td>
                                                <td style="padding: 0.5rem; font-family: monospace;">{{ $g['email'] }}</td>
                                                <td style="padding: 0.5rem; font-family: monospace;">{{ $g['password'] }}</td>
                                                <td style="padding: 0.5rem; font-family: monospace; font-size: 0.8rem; color: var(--text-secondary); white-space: nowrap;">
                                                    {{ $g['smtp'] ?? '—' }}<br>{{ $g['imap'] ?? '—' }}
                                                </td>
                                                <td style="padding: 0.5rem;">{{ $g['company'] ?? '—' }}</td>
                                                <td style="padding: 0.5rem; font-family: monospace;">{{ $g['inn'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
                                <x-button type="submit" variant="accent" icon="user-plus">
                                    Добавить выбранные
                                </x-button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endisset

        @isset($credentialsByDomain)
            @if(count($credentialsByDomain) > 0)
                <div class="card" style="margin-top: var(--space-6);">
                    <div class="card-header">
                        <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                            <i data-lucide="mail-plus" class="icon-md"></i>
                            Заведите ящики на хостинге
                        </h2>
                    </div>
                    <div class="card-body">
                        <p style="margin-top: 0; color: var(--text-secondary);">
                            Создайте эти ящики у почтового провайдера. Список сгруппирован по домену —
                            формат <code>email&nbsp;пароль</code>, можно выделить и скопировать.
                        </p>
                        @foreach($credentialsByDomain as $domain => $items)
                            <div class="form-group">
                                <label class="form-label">{{ $domain }} ({{ count($items) }})</label>
                                <textarea readonly rows="{{ min(count($items) + 1, 12) }}"
                                    class="input"
                                    style="font-family: monospace; font-size: 0.875rem;"
                                    onclick="this.select()">@foreach($items as $it){{ $it['email'] }} {{ $it['password'] }}
@endforeach</textarea>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endisset
    </div>

    {{-- ───────── Режим «Вручную» ───────── --}}
    <div class="senders-tab-pane" data-pane="manual" style="{{ $activeTab === 'manual' ? '' : 'display: none;' }}">
        <div class="alert alert-info" style="margin-bottom: var(--space-6);">
            <div style="display: flex; align-items: start; gap: var(--space-3);">
                <i data-lucide="info" class="icon-md"></i>
                <div>
                    <strong>Как это работает</strong>
                    <p style="margin-top: var(--space-1); margin-bottom: 0;">
                        Каждый отправитель — это блок строк <code>ключ: значение</code>. Блоки разделяются
                        пустой строкой (или строкой <code>---</code>). Обязательные поля:
                        <code>email</code>, <code>smtp</code>, <code>imap</code>, <code>user</code>, <code>password</code>.
                        Недостающие поля (ФИО, телефон, стиль письма, реквизиты организации) дописываются автоматически через AI,
                        организация ищется/создаётся по <code>company</code>+<code>inn</code>.
                    </p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="users" class="icon-md"></i>
                    Список отправителей
                </h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.senders.import.store') }}">
                    @csrf

                    <div class="form-group">
                        <label class="form-label" for="senders">
                            Блоки отправителей
                        </label>
                        <textarea
                            id="senders"
                            name="senders"
                            rows="18"
                            class="input @error('senders') is-invalid @enderror"
                            style="font-family: monospace; font-size: 0.875rem;"
                            placeholder="email: glavlift@email.ru&#10;smtp: smtp.yandex.ru:465&#10;imap: imap.yandex.ru:993&#10;user: glavlift@email.ru&#10;password: qwerty12345&#10;name: Отдел закупок&#10;company: ООО Лифтмонтаж&#10;inn: 5501234567&#10;&#10;email: second@mail.ru&#10;smtp: smtp.mail.ru:465&#10;imap: imap.mail.ru:993&#10;user: second@mail.ru&#10;password: secret123"
                            required
                        >{{ old('senders', $rawInput ?? '') }}</textarea>
                        @error('senders')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-help">
                            Минимум на отправителя: <code>email</code>, <code>smtp</code> (host:port),
                            <code>imap</code> (host:port), <code>user</code>, <code>password</code>.
                            Опционально: <code>name</code>, <code>fullname</code>, <code>phone</code>,
                            <code>company</code>, <code>inn</code>, <code>kpp</code>, <code>address</code>,
                            <code>smtp_enc</code>/<code>imap_enc</code> (none/ssl/tls).
                        </small>
                    </div>

                    <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
                        <x-button type="submit" variant="accent" icon="upload">
                            Добавить отправителей
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    lucide.createIcons();

    (function () {
        const buttons = document.querySelectorAll('.senders-tab-btn');
        const panes = document.querySelectorAll('.senders-tab-pane');
        const activeColor = 'var(--accent, #2563eb)';

        function activate(tab) {
            panes.forEach(p => p.style.display = (p.dataset.pane === tab) ? '' : 'none');
            buttons.forEach(b => {
                const on = b.dataset.tab === tab;
                b.style.borderBottomColor = on ? activeColor : 'transparent';
                b.style.color = on ? activeColor : 'var(--text-secondary)';
            });
        }

        buttons.forEach(b => b.addEventListener('click', () => activate(b.dataset.tab)));
        activate(@json($activeTab));
    })();

    (function () {
        const all = document.getElementById('gen-all');
        if (!all) return;
        const rows = () => document.querySelectorAll('.gen-row');
        all.addEventListener('change', () => rows().forEach(r => r.checked = all.checked));
        rows().forEach(r => r.addEventListener('change', () => {
            all.checked = [...rows()].every(x => x.checked);
        }));
    })();

    (function () {
        const status = document.querySelector('[data-gen-status]');
        if (!status || status.dataset.finished === '1') return;
        setTimeout(() => window.location.reload(), 3000);
    })();
</script>
@endpush
