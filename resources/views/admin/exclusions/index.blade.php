@extends('layouts.cabinet')

@section('title', 'Стоп-лист рассылки')

@section('content')
<div style="max-width: 900px; margin: 0 auto;">
    <x-page-header
        title="Стоп-лист рассылки"
        description="Ручное исключение из рассылки: домены (не шлём никому на домене) и отдельные ящики получателей. Домены проверяются на этапе генерации, ящики — и на генерации, и на отправке."
    />

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="margin-bottom: var(--space-4);">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom: var(--space-4);">{{ $errors->first() }}</div>
    @endif

    {{-- ───────── Домены ───────── --}}
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="globe-lock" class="icon-md"></i>
                Заблокированные домены ({{ count($domains) }})
            </h2>
        </div>
        <div class="card-body">
            <p style="margin-top: 0; color: var(--text-secondary);">
                Поставщики с такими доменами email исключаются из подбора при генерации рассылки.
            </p>

            <form method="POST" action="{{ route('admin.exclusions.domains.store') }}"
                style="display: flex; gap: var(--space-3); flex-wrap: wrap; align-items: end; margin-bottom: var(--space-4);">
                @csrf
                <div class="form-group" style="flex: 1; min-width: 200px; margin: 0;">
                    <label class="form-label" for="domain">Домен</label>
                    <input type="text" id="domain" name="domain" class="input" placeholder="example.ru" required>
                </div>
                <div class="form-group" style="flex: 1; min-width: 200px; margin: 0;">
                    <label class="form-label" for="domain_reason">Причина (необязательно)</label>
                    <input type="text" id="domain_reason" name="reason" class="input" placeholder="жалоба на спам">
                </div>
                <x-button type="submit" variant="danger" icon="ban">Заблокировать</x-button>
            </form>

            @if(count($domains) === 0)
                <p style="color: var(--text-secondary); margin: 0;">Список пуст.</p>
            @else
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 1px solid var(--border);">
                                <th style="padding: 0.5rem;">Домен</th>
                                <th style="padding: 0.5rem;">Причина</th>
                                <th style="padding: 0.5rem;">Добавлен</th>
                                <th style="padding: 0.5rem;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($domains as $d)
                                <tr style="border-bottom: 1px solid var(--border-light, #eee);">
                                    <td style="padding: 0.5rem; font-family: monospace;">{{ $d->domain }}</td>
                                    <td style="padding: 0.5rem; color: var(--text-secondary);">{{ $d->reason ?: '—' }}</td>
                                    <td style="padding: 0.5rem; color: var(--text-secondary); white-space: nowrap;">{{ $d->created_at ?: '—' }}</td>
                                    <td style="padding: 0.5rem; text-align: right;">
                                        <form method="POST" action="{{ route('admin.exclusions.domains.destroy') }}"
                                            onsubmit="return confirm('Убрать {{ $d->domain }} из стоп-листа? Рассылка на него снова станет возможной.');">
                                            @csrf
                                            <input type="hidden" name="domain" value="{{ $d->domain }}">
                                            <button type="submit" class="btn btn-sm" style="background: none; border: none; color: var(--accent, #2563eb); cursor: pointer;">
                                                Разблокировать
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ───────── Ящики ───────── --}}
    <div class="card">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="mail-x" class="icon-md"></i>
                Заблокированные ящики ({{ count($mailboxes) }})
            </h2>
        </div>
        <div class="card-body">
            <p style="margin-top: 0; color: var(--text-secondary);">
                Блокировка ящика: адрес не получает писем (и на генерации, и на отправке), поставщик с этим
                адресом деактивируется, а его pending-письма отменяются.
            </p>

            <form method="POST" action="{{ route('admin.exclusions.mailboxes.store') }}"
                style="display: flex; gap: var(--space-3); flex-wrap: wrap; align-items: end; margin-bottom: var(--space-4);">
                @csrf
                <div class="form-group" style="flex: 1; min-width: 200px; margin: 0;">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="input" placeholder="info@example.ru" required>
                </div>
                <div class="form-group" style="flex: 1; min-width: 200px; margin: 0;">
                    <label class="form-label" for="mailbox_reason">Причина (необязательно)</label>
                    <input type="text" id="mailbox_reason" name="reason" class="input" placeholder="жалоба / просьба отписать">
                </div>
                <x-button type="submit" variant="danger" icon="ban">Заблокировать</x-button>
            </form>

            @if(count($mailboxes) === 0)
                <p style="color: var(--text-secondary); margin: 0;">Список пуст.</p>
            @else
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 1px solid var(--border);">
                                <th style="padding: 0.5rem;">Email</th>
                                <th style="padding: 0.5rem;">Поставщик</th>
                                <th style="padding: 0.5rem;">Причина</th>
                                <th style="padding: 0.5rem;">Заблокирован</th>
                                <th style="padding: 0.5rem;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($mailboxes as $m)
                                <tr style="border-bottom: 1px solid var(--border-light, #eee);">
                                    <td style="padding: 0.5rem; font-family: monospace;">{{ $m->email }}</td>
                                    <td style="padding: 0.5rem;">
                                        @if($m->supplier_id)
                                            {{ $m->supplier_name }}
                                            <span style="color: {{ $m->supplier_active ? '#b02a37' : 'var(--text-secondary)' }};">
                                                ({{ $m->supplier_active ? 'ещё активен!' : 'деактивирован' }})
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td style="padding: 0.5rem; color: var(--text-secondary);">{{ $m->last_error_message ?: '—' }}</td>
                                    <td style="padding: 0.5rem; color: var(--text-secondary); white-space: nowrap;">{{ $m->blocked_at ?: '—' }}</td>
                                    <td style="padding: 0.5rem; text-align: right;">
                                        <form method="POST" action="{{ route('admin.exclusions.mailboxes.destroy') }}"
                                            onsubmit="return confirm('Разблокировать {{ $m->email }} и реактивировать поставщика?');">
                                            @csrf
                                            <input type="hidden" name="email" value="{{ $m->email }}">
                                            <button type="submit" class="btn btn-sm" style="background: none; border: none; color: var(--accent, #2563eb); cursor: pointer;">
                                                Разблокировать
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>lucide.createIcons();</script>
@endpush
