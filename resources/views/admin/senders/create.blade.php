@extends('layouts.cabinet')

@section('title', 'Групповое добавление отправителей')

@section('content')
@php
    $activeTab = $activeTab ?? ($errors->hasAny(['credentials', 'smtp', 'imap', 'smtp_enc', 'imap_enc', 'excel']) ? 'wizard' : 'manual');
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
</script>
@endpush
