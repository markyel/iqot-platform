@extends('layouts.cabinet')

@section('title', 'Discovery run #' . $run->id)

@section('content')
<x-page-header :title="'Discovery run #' . $run->id"
               :description="$productType?->name ?? ('product_type #' . $run->product_type_id)">
    <x-slot:actions>
        <a href="{{ route('admin.supplier-discovery.index') }}" class="btn">← К списку</a>
    </x-slot:actions>
</x-page-header>

<div style="max-width: 1100px;">
    @php
        $covPct = $coverage['threshold'] > 0
            ? min(100, (int) round($coverage['available'] / $coverage['threshold'] * 100))
            : 0;
        $covColor = $coverage['is_sufficient']
            ? 'var(--green-600)'
            : ($covPct >= 50 ? '#d97706' : 'var(--red-600)');
    @endphp
    <div class="card" style="margin-bottom: var(--space-4); border-left: 4px solid {{ $covColor }};">
        <div class="card-body">
            <div style="display: flex; align-items: center; gap: var(--space-4); flex-wrap: wrap;">
                <div>
                    <div style="font-size: 0.85em; color: var(--gray-600); text-transform: uppercase; letter-spacing: 0.5px;">Текущее покрытие</div>
                    <div style="font-size: 1.6em; font-weight: 700; color: {{ $covColor }};">
                        {{ $coverage['available'] }} / {{ $coverage['threshold'] }}
                    </div>
                    <div style="font-size: 0.9em; color: var(--gray-600);">
                        @if($coverage['is_sufficient'])
                            ✓ достаточно поставщиков для broadcast
                        @else
                            не хватает {{ $coverage['threshold'] - $coverage['available'] }} — discovery будет перезапускаться
                        @endif
                    </div>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <div style="background: var(--gray-200); border-radius: 999px; height: 14px; overflow: hidden;">
                        <div style="width: {{ $covPct }}%; height: 100%; background: {{ $covColor }}; transition: width 0.3s;"></div>
                    </div>
                    <div style="font-size: 0.8em; color: var(--gray-500); margin-top: var(--space-1);">
                        {{ $covPct }}% от порога
                        @if($coverage['is_sufficient']) · условие покрытия выполнено @endif
                    </div>
                </div>
            </div>
            <div style="margin-top: var(--space-2); font-size: 0.85em; color: var(--gray-500);">
                Фильтр: <code>is_active=1 AND notify_email=1 AND profile_confidence≥0.3</code> +
                совпадение scope по domain / product_type. Threshold берётся из
                <code>domain_product_types.min_suppliers_threshold</code>, затем <code>product_types.min_suppliers_threshold</code>,
                иначе default = {{ \App\Services\Api\SupplierCoverageService::DEFAULT_THRESHOLD }}.
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-body">
            <dl style="display: grid; grid-template-columns: max-content 1fr; gap: var(--space-2) var(--space-4);">
                <dt><strong>product_type</strong></dt>
                <dd>
                    #{{ $run->product_type_id }}
                    @if($productType)
                        — {{ $productType->name }} ({{ $productType->slug }})
                    @endif
                </dd>

                <dt><strong>domain</strong></dt>
                <dd>
                    @if($domain)
                        #{{ $domain->id }} — {{ $domain->name }} ({{ $domain->slug }})
                    @else
                        <span style="color: var(--gray-500);">универсальный</span>
                    @endif
                </dd>

                <dt><strong>Status</strong></dt>
                <dd><strong>{{ $run->status }}</strong></dd>

                <dt><strong>Итераций использовано</strong></dt>
                <dd>{{ $run->iterations_used }} / 5</dd>

                <dt><strong>Найдено новых поставщиков</strong></dt>
                <dd>{{ $run->suppliers_found }}</dd>

                <dt><strong>Trigger</strong></dt>
                <dd>
                    {{ $run->trigger_source }}
                    @if($run->triggering_submission_external_id)
                        — submission <code>sub_{{ $run->triggering_submission_external_id }}</code>
                    @endif
                </dd>

                <dt><strong>Время</strong></dt>
                <dd>
                    created: {{ $run->created_at?->format('Y-m-d H:i:s') }}<br>
                    @if($run->started_at) started: {{ $run->started_at->format('Y-m-d H:i:s') }}<br> @endif
                    @if($run->finished_at) finished: {{ $run->finished_at->format('Y-m-d H:i:s') }} @endif
                </dd>

                @if($run->error)
                    <dt style="color: var(--red-600);"><strong>Error</strong></dt>
                    <dd><pre style="white-space: pre-wrap; font-size: 0.85em;">{{ $run->error }}</pre></dd>
                @endif
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Новые поставщики, созданные за этот run ({{ $createdSuppliers->count() }})</h2>
        </div>
        <div class="card-body">
            @if($createdSuppliers->isEmpty())
                <p style="color: var(--gray-600);">Новых поставщиков в этом run не добавлено.</p>
            @else
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Название</th>
                            <th>Email</th>
                            <th>Телефон</th>
                            <th>Сайт</th>
                            <th>Confidence</th>
                            <th>Создан</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($createdSuppliers as $s)
                            <tr>
                                <td>{{ $s->id }}</td>
                                <td>{{ $s->name }}</td>
                                <td>
                                    @if($s->email)
                                        <a href="mailto:{{ $s->email }}">{{ $s->email }}</a>
                                    @else —
                                    @endif
                                </td>
                                <td>{{ $s->phone ?: '—' }}</td>
                                <td>
                                    @if($s->website)
                                        <a href="{{ $s->website }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($s->website, 40) }}</a>
                                    @else —
                                    @endif
                                </td>
                                <td>{{ number_format((float) $s->profile_confidence, 2) }}</td>
                                <td>{{ \Carbon\Carbon::parse($s->created_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
