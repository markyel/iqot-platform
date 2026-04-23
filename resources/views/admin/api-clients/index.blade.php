@extends('layouts.cabinet')

@section('title', 'API-клиенты')

@section('content')
<x-page-header title="API-клиенты"
               description="Пользователи с публичным API-доступом и их настройки" />

<div style="max-width: 1300px;">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            @if($clients->isEmpty())
                <p style="color: var(--gray-600);">Ни один пользователь ещё не активировал API.</p>
            @else
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Пользователь</th>
                            <th>Активен</th>
                            <th>Авто-приём green</th>
                            <th>Overdraft, %</th>
                            <th>Submissions</th>
                            <th>Создан</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clients as $client)
                            @php
                                $user = $users[$client->user_id] ?? null;
                                $stats = $submissionStats[$client->id] ?? null;
                            @endphp
                            <tr>
                                <td>{{ $client->id }}</td>
                                <td>
                                    @if($user)
                                        <strong>{{ $user->name }}</strong>
                                        <div style="font-size: 0.85em; color: var(--gray-600);">{{ $user->email }}</div>
                                        <div style="font-size: 0.75em; color: var(--gray-500);">user #{{ $user->id }}</div>
                                    @else
                                        <span style="color: var(--gray-500);">user #{{ $client->user_id }} (удалён?)</span>
                                    @endif
                                </td>
                                <td>
                                    @if($client->is_active)
                                        <span style="color: var(--green-600); font-weight: 600;">да</span>
                                    @else
                                        <span style="color: var(--red-600); font-weight: 600;">нет</span>
                                    @endif
                                </td>
                                <td>
                                    @if($client->auto_approve_green)
                                        <span style="color: var(--green-600); font-weight: 600;">включён</span>
                                    @else
                                        <span style="color: var(--gray-500);">выключен</span>
                                    @endif
                                </td>
                                <td>{{ number_format((float) $client->overdraft_percent, 2) }}</td>
                                <td>
                                    @if($stats)
                                        <strong>{{ $stats->total }}</strong>
                                        <small style="color: var(--gray-500);">({{ (int) $stats->items_total }} поз.)</small>
                                    @else
                                        <span style="color: var(--gray-500);">—</span>
                                    @endif
                                </td>
                                <td>
                                    <small style="color: var(--gray-500);">{{ $client->created_at?->format('Y-m-d') }}</small>
                                </td>
                                <td>
                                    <details>
                                        <summary class="btn btn-sm">Изменить…</summary>
                                        <form method="POST" action="{{ route('admin.api-clients.update', $client) }}"
                                              style="margin-top: var(--space-2); padding: var(--space-3); border: 1px solid var(--gray-300); border-radius: 6px; background: var(--gray-50); min-width: 280px;">
                                            @csrf
                                            @method('PATCH')
                                            <label style="display: flex; gap: var(--space-2); align-items: flex-start; margin-bottom: var(--space-3);">
                                                <input type="checkbox" name="is_active" value="1"
                                                       {{ $client->is_active ? 'checked' : '' }}>
                                                <span><strong>API-доступ активен</strong></span>
                                            </label>
                                            <label style="display: flex; gap: var(--space-2); align-items: flex-start; margin-bottom: var(--space-3);">
                                                <input type="checkbox" name="auto_approve_green" value="1"
                                                       {{ $client->auto_approve_green ? 'checked' : '' }}>
                                                <span>
                                                    <strong>Авто-приём green</strong>
                                                    <div style="font-size: 0.8em; color: var(--gray-600);">
                                                        Позиции с trust=green сразу accepted, без ручной модерации
                                                    </div>
                                                </span>
                                            </label>
                                            <button type="submit" class="btn btn-sm btn-primary">Сохранить</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div style="margin-top: var(--space-4); font-size: 0.85em; color: var(--gray-600);">
        <strong>Авто-приём green:</strong> когда включён, после inbox-классификации все позиции submission
        с <code>trust_level=green</code> (manual candidate или ai_suggested с hit_count ≥ 20) сразу
        переводятся в <code>accepted</code> без участия модератора. Yellow и red продолжают идти
        на модерацию как обычно.
    </div>
</div>
@endsection
