@extends('layouts.cabinet')

@section('title', 'Submission sub_' . $submission->external_id)

@section('content')
<x-page-header
    :title="'sub_' . $submission->external_id"
    :description="'API-заявка пользователя ' . ($submission->client->user->email ?? '—')"
>
    <x-slot:actions>
        <a href="{{ route('admin.api-submissions.index') }}" class="btn">← К списку</a>
    </x-slot:actions>
</x-page-header>

<div style="max-width: 1200px;">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">{{ session('success') }}</div>
    @endif

    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-body">
            <dl style="display: grid; grid-template-columns: max-content 1fr; gap: var(--space-2) var(--space-4);">
                <dt><strong>Status / Stage</strong></dt>
                <dd>{{ $submission->status }} / {{ $submission->stage }}</dd>

                <dt><strong>client_ref</strong></dt>
                <dd>{{ $submission->client_ref ?: '—' }}</dd>

                <dt><strong>client_organization_id</strong></dt>
                <dd>{{ $submission->client_organization_id ?: '—' }}</dd>

                <dt><strong>sender</strong></dt>
                <dd>
                    @if($submission->sender)
                        user_sender #{{ $submission->sender->id }}, external={{ $submission->sender->external_sender_id ?: '—' }}
                    @else —
                    @endif
                </dd>

                <dt><strong>items_total / accepted / rejected</strong></dt>
                <dd>{{ $submission->items_total }} / {{ $submission->items_accepted }} / {{ $submission->items_rejected }}</dd>

                <dt><strong>deadline_at</strong></dt>
                <dd>{{ $submission->deadline_at?->format('Y-m-d H:i') ?: '—' }}</dd>

                <dt><strong>created_at</strong></dt>
                <dd>{{ $submission->created_at?->format('Y-m-d H:i') }}</dd>
            </dl>
        </div>
    </div>

    @if($submission->status === 'ready' || $submission->status === 'completed')
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">
            <strong>Модерация завершена.</strong> Дальнейшие изменения невозможны.
            @if($submission->rejected_summary)
                <br>Отклонённые позиции:
                <ul style="margin-top: var(--space-2);">
                    @foreach($submission->rejected_summary as $r)
                        <li>
                            <code>{{ $r['client_ref'] ?? '—' }}</code>
                            «{{ $r['name'] ?? '' }}» —
                            <strong>{{ $r['reason'] ?? '' }}</strong>:
                            {{ $r['message'] ?? '' }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        @php
            $greenCount = $items->where('item_status', 'classified')->where('trust_level', 'green')->count();
        @endphp
        @if($greenCount > 0)
            <div style="margin-bottom: var(--space-4);">
                <form method="POST" action="{{ route('admin.api-submissions.approve-batch', $submission) }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        Approve all green ({{ $greenCount }})
                    </button>
                </form>
            </div>
        @endif
    @endif

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Позиции</h2>
        </div>
        <div class="card-body">
            @if($items->isEmpty())
                <p style="color: var(--gray-600);">Нет staging-позиций (либо не обработано воркером, либо уже финализировано и rejected удалены).</p>
            @else
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Позиция</th>
                            <th>Классификация</th>
                            <th>Trust</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>{{ $item->position_number }}</td>
                                <td>
                                    <strong>{{ $item->name }}</strong><br>
                                    <small style="color: var(--gray-600);">
                                        {{ $item->brand ?: '' }} {{ $item->article ?: '' }}
                                        — {{ $item->quantity }} {{ $item->unit }}
                                    </small>
                                    @if($item->clientCategory)
                                        <div style="font-size: 0.85em; color: var(--gray-500);">
                                            {{ $item->clientCategory->full_path }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($item->product_type_id)
                                        pt: {{ $productTypes[$item->product_type_id]->name ?? ('#' . $item->product_type_id) }}<br>
                                    @endif
                                    @if($item->domain_id)
                                        dom: {{ $domains[$item->domain_id]->name ?? ('#' . $item->domain_id) }}<br>
                                    @endif
                                    <small style="color: var(--gray-500);">{{ $item->classification_source ?: '—' }}</small>
                                </td>
                                <td>
                                    @php $color = ['green' => 'var(--green-600)', 'yellow' => '#d97706', 'red' => 'var(--red-600)'][$item->trust_level] ?? 'var(--gray-500)'; @endphp
                                    <span style="color: {{ $color }}; font-weight: 600;">{{ $item->trust_level }}</span>
                                </td>
                                <td>{{ $item->item_status }}</td>
                                <td>
                                    @if($item->item_status === 'classified')
                                        <div class="api-actions">
                                            <form method="POST" action="{{ route('admin.api-submissions.approve-item', [$submission, $item]) }}" class="api-actions__form">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                                            </form>

                                            <details class="api-actions__details">
                                                <summary class="btn btn-sm btn-danger">Reject…</summary>
                                                <form method="POST"
                                                      action="{{ route('admin.api-submissions.reject-item', [$submission, $item]) }}"
                                                      class="api-actions__popup">
                                                    @csrf
                                                    <label class="api-actions__label">Причина</label>
                                                    <select name="reason" required class="api-actions__input">
                                                        @foreach($rejectReasons as $code => [$label, $retryable])
                                                            <option value="{{ $code }}">{{ $code }} — {{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <label class="api-actions__label">Комментарий (опционально)</label>
                                                    <input type="text" name="message" maxlength="2000" class="api-actions__input">
                                                    <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                                </form>
                                            </details>

                                            <details class="api-actions__details">
                                                <summary class="btn btn-sm">Reclassify…</summary>
                                                <form method="POST"
                                                      action="{{ route('admin.api-submissions.reclassify-item', [$submission, $item]) }}"
                                                      class="api-actions__popup">
                                                    @csrf
                                                    <label class="api-actions__label">Тип товара</label>
                                                    <input type="text"
                                                           name="product_type_id"
                                                           list="api-product-types-list"
                                                           value="{{ $item->product_type_id }}"
                                                           class="api-actions__input"
                                                           autocomplete="off"
                                                           placeholder="начните вводить…">
                                                    <label class="api-actions__label">Область применения (пусто = универсальный)</label>
                                                    <input type="text"
                                                           name="domain_id"
                                                           list="api-domains-list"
                                                           value="{{ $item->domain_id }}"
                                                           class="api-actions__input"
                                                           autocomplete="off"
                                                           placeholder="например, Лифты">
                                                    <small class="api-actions__hint">
                                                        При вводе появится подсказка. Для справки —
                                                        <a href="{{ route('admin.taxonomy.product-types') }}" target="_blank" rel="noopener">product types</a>
                                                        ·
                                                        <a href="{{ route('admin.taxonomy.domains') }}" target="_blank" rel="noopener">domains</a>
                                                    </small>
                                                    <button type="submit" class="btn btn-sm">Apply</button>
                                                </form>
                                            </details>
                                        </div>
                                    @else
                                        <span style="color: var(--gray-500);">—</span>
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

<datalist id="api-product-types-list">
    @foreach($productTypesAll as $pt)
        <option value="{{ $pt->id }}" label="{{ $pt->name }} · {{ $pt->slug }}"></option>
    @endforeach
</datalist>
<datalist id="api-domains-list">
    @foreach($domainsAll as $d)
        <option value="{{ $d->id }}" label="{{ $d->name }} · {{ $d->slug }}"></option>
    @endforeach
</datalist>

@push('styles')
<style>
.api-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: flex-start;
}
.api-actions__form { margin: 0; }

.api-actions__details {
    position: relative;
    display: inline-block;
}
/* <summary> превращаем в саму кнопку — без вложенного span */
.api-actions__details > summary {
    list-style: none;
    cursor: pointer;
    user-select: none;
}
.api-actions__details > summary::-webkit-details-marker { display: none; }
.api-actions__details > summary::marker { content: ''; }

/* Popup с формой — абсолютно позиционирован, не ломает строку таблицы */
.api-actions__popup {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: 20;
    min-width: 280px;
    margin: 0;
    padding: 12px;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.api-actions__label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #4b5563;
}
.api-actions__input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 14px;
}
.api-actions__popup button[type="submit"] {
    align-self: flex-start;
}
.api-actions__hint {
    font-size: 12px;
    color: #6b7280;
}
.api-actions__hint a { color: #2563eb; text-decoration: underline; }

/* Popup абсолютно позиционирован внутри <td> — предотвращаем клиппинг. */
.card, .card-body { overflow: visible !important; }
table td { position: relative; }
.api-actions__details[open] { z-index: 50; }
.api-actions__popup input,
.api-actions__popup select,
.api-actions__popup textarea { font: inherit; }
</style>
@endpush
@endsection
