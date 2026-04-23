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
                                        <form method="POST" action="{{ route('admin.api-submissions.approve-item', [$submission, $item]) }}" style="display: inline; margin: 0;">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                                        </form>

                                        <details style="display: inline-block; margin-left: var(--space-2);">
                                            <summary style="cursor: pointer; display: inline-block;">
                                                <span class="btn btn-sm btn-danger" style="display: inline-block;">Reject…</span>
                                            </summary>
                                            <form method="POST" action="{{ route('admin.api-submissions.reject-item', [$submission, $item]) }}"
                                                  style="margin-top: var(--space-2); padding: var(--space-3); background: var(--gray-50); border-radius: 4px;">
                                                @csrf
                                                <select name="reason" required style="margin-bottom: var(--space-2);">
                                                    @foreach($rejectReasons as $code => [$label, $retryable])
                                                        <option value="{{ $code }}">{{ $code }} — {{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="message" placeholder="Комментарий (опционально)" maxlength="2000" style="width: 100%; margin-bottom: var(--space-2);">
                                                <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                            </form>
                                        </details>

                                        <details style="display: inline-block; margin-left: var(--space-2);">
                                            <summary style="cursor: pointer; display: inline-block;">
                                                <span class="btn btn-sm" style="display: inline-block;">Reclassify…</span>
                                            </summary>
                                            <form method="POST" action="{{ route('admin.api-submissions.reclassify-item', [$submission, $item]) }}"
                                                  style="margin-top: var(--space-2); padding: var(--space-3); background: var(--gray-50); border-radius: 4px;">
                                                @csrf
                                                <label style="display: block;">product_type_id:
                                                    <input type="number" name="product_type_id" min="1" value="{{ $item->product_type_id }}" style="width: 100px;">
                                                </label>
                                                <label style="display: block; margin-top: var(--space-2);">domain_id (пусто = NULL):
                                                    <input type="number" name="domain_id" min="1" value="{{ $item->domain_id }}" style="width: 100px;">
                                                </label>
                                                <button type="submit" class="btn btn-sm" style="margin-top: var(--space-2);">Apply</button>
                                            </form>
                                        </details>
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
@endsection
