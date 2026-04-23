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
