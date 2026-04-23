@extends('layouts.cabinet')

@section('title', 'API-ключи')

@section('content')
<x-page-header title="API-ключи" description="Управление ключами доступа к публичному IQOT API" />

<div style="margin-bottom: var(--space-4); display: flex; gap: var(--space-3);">
    <a href="{{ route('cabinet.api-keys.index') }}" class="btn btn-sm btn-primary">Ключи</a>
    <a href="{{ route('cabinet.senders.index') }}" class="btn btn-sm">Отправители</a>
    <a href="{{ route('cabinet.api-submissions.index') }}" class="btn btn-sm">Заявки</a>
</div>

<div style="max-width: 900px;">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="margin-bottom: var(--space-4);">{{ session('error') }}</div>
    @endif

    @if($plainKey)
        <div class="card" style="margin-bottom: var(--space-6); border: 2px solid var(--red-600);">
            <div class="card-header">
                <h2 class="card-title" style="color: var(--red-600);">
                    <i data-lucide="key" style="width: 20px; height: 20px;"></i>
                    Ключ создан — сохраните его сейчас
                </h2>
            </div>
            <div class="card-body">
                <p style="margin-bottom: var(--space-3);">
                    <strong>Имя:</strong> {{ $plainKey['name'] }}
                </p>
                <p style="margin-bottom: var(--space-3); color: var(--red-600);">
                    Ключ больше не будет показан. Скопируйте его прямо сейчас.
                </p>
                <div style="display: flex; gap: var(--space-2);">
                    <input
                        type="text"
                        value="{{ $plainKey['plain_key'] }}"
                        readonly
                        class="input"
                        style="flex: 1; font-family: monospace;"
                        id="new-api-key"
                        onclick="this.select()"
                    >
                    <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('new-api-key').value); this.textContent='Скопировано';">
                        Копировать
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-header">
            <h2 class="card-title">Создать новый ключ</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('cabinet.api-keys.store') }}">
                @csrf
                <div class="form-group" style="margin-bottom: var(--space-4);">
                    <label class="form-label">Имя ключа <span style="color: var(--red-600);">*</span></label>
                    <input
                        type="text"
                        name="name"
                        class="input @error('name') is-invalid @enderror"
                        value="{{ old('name') }}"
                        placeholder="ERP Production"
                        maxlength="100"
                        required
                    >
                    @error('name')
                        <div style="color: var(--red-600); font-size: 0.875rem;">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="margin-bottom: var(--space-4);">
                    <label class="form-label">IP whitelist (опционально)</label>
                    <textarea
                        name="ip_whitelist"
                        class="input"
                        rows="3"
                        placeholder="1.2.3.4&#10;10.0.0.0/24"
                    >{{ old('ip_whitelist') }}</textarea>
                    <div style="font-size: 0.875rem; color: var(--gray-600); margin-top: var(--space-1);">
                        По одному IP или CIDR-блоку на строку. Пусто = любой IP.
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 0.875rem; color: var(--gray-600);">
                        Активных ключей: {{ $keys->whereNull('revoked_at')->count() }} / {{ $maxActiveKeys }}
                    </div>
                    <button type="submit" class="btn btn-primary">Создать ключ</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Существующие ключи</h2>
        </div>
        <div class="card-body">
            @if($keys->isEmpty())
                <p style="color: var(--gray-600);">Ключей пока нет.</p>
            @else
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Имя</th>
                            <th>Префикс</th>
                            <th>IP whitelist</th>
                            <th>Последнее использование</th>
                            <th>Запросов</th>
                            <th>Статус</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($keys as $key)
                            <tr>
                                <td>{{ $key->name }}</td>
                                <td><code>{{ $key->key_prefix }}…{{ $key->key_last4 }}</code></td>
                                <td>
                                    @if($key->ip_whitelist)
                                        @foreach((array)$key->ip_whitelist as $ip)
                                            <div><code>{{ $ip }}</code></div>
                                        @endforeach
                                    @else
                                        <span style="color: var(--gray-500);">любой</span>
                                    @endif
                                </td>
                                <td>
                                    @if($key->last_used_at)
                                        {{ $key->last_used_at->format('Y-m-d H:i') }}
                                        <div style="font-size: 0.8em; color: var(--gray-500);">{{ $key->last_used_ip }}</div>
                                    @else
                                        <span style="color: var(--gray-500);">—</span>
                                    @endif
                                </td>
                                <td>{{ number_format($key->request_count) }}</td>
                                <td>
                                    @if($key->revoked_at === null)
                                        <span style="color: var(--green-600);">активен</span>
                                    @else
                                        <span style="color: var(--gray-500);">отозван {{ $key->revoked_at->format('Y-m-d') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($key->revoked_at === null)
                                        <form method="POST" action="{{ route('cabinet.api-keys.destroy', $key) }}"
                                              onsubmit="return confirm('Отозвать ключ {{ $key->name }}?');"
                                              style="margin: 0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Отозвать</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
