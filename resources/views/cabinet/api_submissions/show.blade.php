@extends('layouts.cabinet')

@section('title', 'API-заявка sub_' . $submission->external_id)

@section('content')
<x-page-header
    :title="'sub_' . $submission->external_id"
    :description="$submission->client_ref ?: 'API-заявка'">
    <x-slot:actions>
        <a href="{{ route('cabinet.api-submissions.index') }}" class="btn">← К списку</a>
    </x-slot:actions>
</x-page-header>

<div style="max-width: 1100px;">
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-body">
            <dl style="display: grid; grid-template-columns: max-content 1fr; gap: var(--space-2) var(--space-4);">
                <dt><strong>Status / Stage</strong></dt>
                <dd>{{ $submission->status }} / {{ $submission->stage }}</dd>

                <dt><strong>Client ref</strong></dt>
                <dd>{{ $submission->client_ref ?: '—' }}</dd>

                <dt><strong>Позиции</strong></dt>
                <dd>всего: {{ $submission->items_total }}, принято: {{ $submission->items_accepted }}, отклонено: {{ $submission->items_rejected }}</dd>

                <dt><strong>Deadline</strong></dt>
                <dd>{{ $submission->deadline_at?->format('Y-m-d H:i') ?: '—' }}</dd>

                <dt><strong>Создана</strong></dt>
                <dd>{{ $submission->created_at?->format('Y-m-d H:i') }}</dd>

                @if($isPromoted)
                    <dt><strong>reports.request</strong></dt>
                    <dd>#{{ $submission->internal_request_id }} (promoted at {{ $submission->promoted_at?->format('Y-m-d H:i') }})</dd>
                @endif

                @if($submission->cancelled_at)
                    <dt><strong>Отменена</strong></dt>
                    <dd>{{ $submission->cancelled_at->format('Y-m-d H:i') }} {{ $submission->cancel_reason ? '— ' . $submission->cancel_reason : '' }}</dd>
                @endif
            </dl>
        </div>
    </div>

    @if(!empty($submission->rejected_summary))
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header"><h2 class="card-title">Отклонённые позиции</h2></div>
            <div class="card-body">
                <ul>
                    @foreach($submission->rejected_summary as $r)
                        <li>
                            <code>{{ $r['client_ref'] ?? '—' }}</code>
                            «{{ $r['name'] ?? '' }}» —
                            <strong>{{ $r['reason'] ?? '' }}</strong>:
                            {{ $r['message'] ?? '' }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><h2 class="card-title">Позиции</h2></div>
        <div class="card-body">
            @if($items->isEmpty())
                <p style="color: var(--gray-600);">Позиций нет.</p>
            @else
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Позиция</th>
                            <th>Классификация</th>
                            <th>Status</th>
                            <th>КП</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $it)
                            <tr>
                                <td>{{ $it->position_number }}</td>
                                <td>
                                    <strong>{{ $it->name }}</strong><br>
                                    <small style="color: var(--gray-600);">
                                        {{ $it->brand ?? '' }} {{ $it->article ?? '' }}
                                        — {{ $it->quantity }} {{ $it->unit }}
                                    </small>
                                </td>
                                <td>
                                    @if($it->product_type_id)
                                        pt: {{ $productTypes[$it->product_type_id]->name ?? ('#' . $it->product_type_id) }}<br>
                                    @endif
                                    @if($it->domain_id)
                                        dom: {{ $domains[$it->domain_id]->name ?? ('#' . $it->domain_id) }}
                                    @endif
                                </td>
                                <td>
                                    @if($isPromoted)
                                        {{ $it->status }}
                                    @else
                                        {{ $it->item_status ?? '—' }}
                                    @endif
                                </td>
                                <td>
                                    @if($isPromoted)
                                        {{ $offerCounts[$it->id] ?? 0 }}
                                    @else
                                        —
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
