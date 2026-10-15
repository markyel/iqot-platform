@extends('layouts.cabinet')

@section('title', 'Документация API')

@section('content')
<x-page-header title="Документация API" description="Заявки на закупку и мониторинг цен через публичный API IQOT" />

@include('cabinet.api_docs._nav')

<div class="card">
    <div class="card-body api-doc">
        <p style="margin-top: 0;">
            Спецификация OpenAPI 3.1: <a href="{{ route('cabinet.api-docs.openapi') }}" target="_blank" rel="noopener">openapi.yaml</a>
            · интерактивный <a href="{{ route('cabinet.api-docs.reference') }}">справочник методов</a>.
        </p>
        {!! $html !!}
    </div>
</div>

<style>
    .api-doc { max-width: 980px; line-height: 1.6; }
    .api-doc h1 { font-size: var(--text-2xl, 1.5rem); margin: var(--space-6, 1.5rem) 0 var(--space-3, .75rem); }
    .api-doc h2 { font-size: var(--text-xl, 1.25rem); margin: var(--space-6, 1.5rem) 0 var(--space-3, .75rem); padding-top: var(--space-3, .75rem); border-top: 1px solid var(--color-border, #e5e7eb); }
    .api-doc h3 { font-size: var(--text-lg, 1.1rem); margin: var(--space-4, 1rem) 0 var(--space-2, .5rem); }
    .api-doc pre { background: var(--color-bg-subtle, #f6f8fa); padding: var(--space-3, .75rem); border-radius: 6px; overflow-x: auto; font-size: 13px; }
    .api-doc code { font-size: 13px; }
    .api-doc :not(pre) > code { background: var(--color-bg-subtle, #f6f8fa); padding: 1px 5px; border-radius: 4px; }
    .api-doc table { border-collapse: collapse; width: 100%; margin: var(--space-3, .75rem) 0; font-size: 14px; display: block; overflow-x: auto; }
    .api-doc th, .api-doc td { border: 1px solid var(--color-border, #e5e7eb); padding: 6px 10px; text-align: left; vertical-align: top; }
    .api-doc th { background: var(--color-bg-subtle, #f6f8fa); }
    .api-doc hr { display: none; }
</style>
@endsection
