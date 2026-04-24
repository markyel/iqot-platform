@extends('layouts.cabinet')

@section('title', 'Модерация API-заявок')

@section('content')
<x-page-header title="Модерация API-заявок" description="Submissions из публичного API, ожидающие модерации" />

<div style="max-width: 1200px;">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">{{ session('success') }}</div>
    @endif

    @php
        $tabs = [
            'pending' => ['Требует действий', 'var(--red-600)'],
            'ready' => ['Ready', 'var(--green-600)'],
            'cancelled' => ['Cancelled', 'var(--gray-500)'],
            'all' => ['Все', 'var(--gray-700)'],
        ];
    @endphp
    <div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-4); flex-wrap: wrap;">
        @foreach($tabs as $key => [$label, $color])
            @php $isActive = $filter === $key; @endphp
            <a href="{{ route('admin.api-submissions.index', ['filter' => $key]) }}"
               class="btn {{ $isActive ? 'btn-primary' : '' }} btn-sm"
               style="{{ $isActive ? '' : 'background: var(--gray-100); color: var(--gray-800); border: 1px solid var(--gray-300);' }}">
                {{ $label }}
                <span style="margin-left: 4px; opacity: 0.7;">({{ $counts[$key] ?? 0 }})</span>
            </a>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            @if($submissions->isEmpty())
                <p style="color: var(--gray-600);">
                    @switch($filter)
                        @case('pending') Нет заявок, требующих действий. @break
                        @case('ready') Нет готовых заявок. @break
                        @case('cancelled') Нет отменённых заявок. @break
                        @default Пока нет submission'ов.
                    @endswitch
                </p>
            @else
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Submission</th>
                            <th>Клиент</th>
                            <th>Status / Stage</th>
                            <th>Позиции</th>
                            <th>Badges</th>
                            <th>Создана</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($submissions as $s)
                            @php $b = $badges[$s->id] ?? []; @endphp
                            <tr>
                                <td>
                                    <code>sub_{{ $s->external_id }}</code><br>
                                    <small style="color: var(--gray-500);">{{ $s->client_ref ?: '—' }}</small>
                                </td>
                                <td>
                                    {{ $s->client->user->email ?? '—' }}<br>
                                    <small style="color: var(--gray-500);">user #{{ $s->client->user_id ?? '—' }}</small>
                                </td>
                                <td>
                                    <strong>{{ $s->status }}</strong><br>
                                    <small style="color: var(--gray-500);">{{ $s->stage }}</small>
                                </td>
                                <td>
                                    {{ $b['total'] ?? 0 }}
                                </td>
                                <td>
                                    @if(($b['green'] ?? 0) > 0)
                                        <span style="color: var(--green-600); font-weight: 600;">{{ $b['green'] }}&nbsp;green</span>
                                    @endif
                                    @if(($b['yellow'] ?? 0) > 0)
                                        <span style="color: #d97706; font-weight: 600;">{{ $b['yellow'] }}&nbsp;yellow</span>
                                    @endif
                                    @if(($b['red'] ?? 0) > 0)
                                        <span style="color: var(--red-600); font-weight: 600;">{{ $b['red'] }}&nbsp;red</span>
                                    @endif
                                    @if(($b['accepted'] ?? 0) > 0)
                                        <span style="color: var(--gray-600);">{{ $b['accepted'] }}&nbsp;ок</span>
                                    @endif
                                    @if(($b['rejected'] ?? 0) > 0)
                                        <span style="color: var(--gray-600);">{{ $b['rejected'] }}&nbsp;откл</span>
                                    @endif
                                </td>
                                <td>{{ $s->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.api-submissions.show', $s) }}" class="btn btn-sm">Открыть</a>
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
