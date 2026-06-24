@extends('layouts.cabinet')

@section('title', 'Групповое добавление отправителей')

@section('content')
<div style="max-width: 900px; margin: 0 auto;">
    <x-page-header
        title="Групповое добавление отправителей"
        description="Массовое добавление отправителей рассылки (таблица senders). Аналог Telegram-команды /addmail, но пачкой."
    />

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
                    @if(!is_null($totalSenders))
                        <br>Сейчас в базе отправителей: <strong>{{ $totalSenders }}</strong>.
                    @endif
                </p>
            </div>
        </div>
    </div>

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
@endsection

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
