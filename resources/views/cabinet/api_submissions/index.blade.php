@extends('layouts.cabinet')

@section('title', 'Мои API-заявки')

@section('content')
<x-page-header title="Мои API-заявки" description="Заявки, созданные через публичное API" />

<div class="api-sub-nav" style="margin-bottom: var(--space-4); display: flex; gap: var(--space-3);">
    <a href="{{ route('cabinet.api-keys.index') }}" class="btn btn-sm">Ключи</a>
    <a href="{{ route('cabinet.senders.index') }}" class="btn btn-sm">Отправители</a>
    <a href="{{ route('cabinet.api-submissions.index') }}" class="btn btn-sm btn-primary">Заявки</a>
</div>

<div class="card">
    <div class="card-body">
        @if($submissions->isEmpty())
            <p style="color: var(--gray-600);">Заявки через API пока не создавались.</p>
        @else
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Submission</th>
                        <th>Client ref</th>
                        <th>Status / Stage</th>
                        <th>Позиции</th>
                        <th>КП</th>
                        <th>Создана</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($submissions as $s)
                        <tr>
                            <td>
                                <code>sub_{{ $s->external_id }}</code>
                                @if($s->internal_request_id)
                                    <br><small style="color: var(--gray-500);">reports.request #{{ $s->internal_request_id }}</small>
                                @endif
                            </td>
                            <td>{{ $s->client_ref ?: '—' }}</td>
                            <td>
                                <strong>{{ $s->status }}</strong><br>
                                <small style="color: var(--gray-500);">{{ $s->stage }}</small>
                            </td>
                            <td>
                                {{ $itemsCount[$s->id] ?? $s->items_total }}
                                @if($s->items_rejected > 0)
                                    <br><small style="color: var(--red-600);">откл.: {{ $s->items_rejected }}</small>
                                @endif
                            </td>
                            <td>{{ $offersCount[$s->id] ?? 0 }}</td>
                            <td>{{ $s->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                <a href="{{ route('cabinet.api-submissions.show', $s) }}" class="btn btn-sm">Открыть</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
