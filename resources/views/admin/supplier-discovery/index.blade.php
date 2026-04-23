@extends('layouts.cabinet')

@section('title', 'Подбор поставщиков')

@section('content')
<x-page-header title="Подбор поставщиков" description="История автопоиска новых поставщиков через Yandex Search + AI" />

<div style="max-width: 1400px;">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert" style="margin-bottom: var(--space-4); background: #fef3c7; color: #92400e; padding: var(--space-3); border-radius: 6px;">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="margin-bottom: var(--space-4);">{{ session('error') }}</div>
    @endif

    <div style="margin-bottom: var(--space-4); display: flex; gap: var(--space-2); flex-wrap: wrap;">
        <a href="{{ route('admin.supplier-discovery.index') }}"
           class="btn btn-sm {{ !$statusFilter ? 'btn-primary' : '' }}">
            Все ({{ array_sum($counts) }})
        </a>
        @foreach(['queued' => 'В очереди', 'running' => 'Выполняется', 'success_covered' => 'OK', 'success_partial' => 'Частично', 'exhausted' => 'Не хватает', 'failed' => 'Ошибка'] as $s => $label)
            @if(!empty($counts[$s]))
                <a href="{{ route('admin.supplier-discovery.index', ['status' => $s]) }}"
                   class="btn btn-sm {{ $statusFilter === $s ? 'btn-primary' : '' }}">
                    {{ $label }} ({{ $counts[$s] }})
                </a>
            @endif
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            @if($runs->isEmpty())
                <p style="color: var(--gray-600);">Запусков сборщика пока не было.</p>
            @else
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>product_type</th>
                            <th>domain</th>
                            <th>Status</th>
                            <th>Итераций</th>
                            <th>Найдено</th>
                            <th>Источник</th>
                            <th>Время</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($runs as $r)
                            @php
                                $statusColor = match ($r->status) {
                                    'success_covered' => 'var(--green-600)',
                                    'success_partial' => '#d97706',
                                    'running' => '#2563eb',
                                    'queued' => '#6b7280',
                                    'exhausted' => 'var(--red-600)',
                                    'failed' => 'var(--red-600)',
                                    default => 'var(--gray-600)',
                                };
                            @endphp
                            <tr>
                                <td>{{ $r->id }}</td>
                                <td>
                                    @if(isset($productTypes[$r->product_type_id]))
                                        {{ $productTypes[$r->product_type_id]->name }}
                                        <div style="font-size: 0.8em; color: var(--gray-500);">#{{ $r->product_type_id }}</div>
                                    @else
                                        #{{ $r->product_type_id }}
                                    @endif
                                </td>
                                <td>
                                    @if($r->domain_id)
                                        {{ $domains[$r->domain_id]->name ?? '#' . $r->domain_id }}
                                    @else
                                        <span style="color: var(--gray-500);">универсальный</span>
                                    @endif
                                </td>
                                <td><span style="color: {{ $statusColor }}; font-weight: 600;">{{ $r->status }}</span></td>
                                <td>{{ $r->iterations_used }}</td>
                                <td>
                                    @if($r->suppliers_found > 0)
                                        <strong style="color: var(--green-600);">+{{ $r->suppliers_found }}</strong>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td>
                                    <small style="color: var(--gray-500);">{{ $r->trigger_source }}</small>
                                    @if($r->triggering_submission_external_id)
                                        <div style="font-size: 0.75em;"><code>sub_{{ \Illuminate\Support\Str::limit($r->triggering_submission_external_id, 10, '…') }}</code></div>
                                    @endif
                                </td>
                                <td>
                                    @if($r->started_at)
                                        <div>start: {{ $r->started_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                    @if($r->finished_at)
                                        <div>end: {{ $r->finished_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                    @if(!$r->started_at && !$r->finished_at)
                                        <small style="color: var(--gray-500);">created {{ $r->created_at?->format('Y-m-d H:i') }}</small>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.supplier-discovery.show', $r) }}" class="btn btn-sm">Открыть</a>
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
