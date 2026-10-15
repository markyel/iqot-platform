@extends('layouts.cabinet')

@section('title', 'Справочник методов API')

@section('content')
<x-page-header title="Справочник методов API" description="Все методы, параметры и схемы ответов — по спецификации OpenAPI" />

@include('cabinet.api_docs._nav')

<div class="card">
    <div class="card-body" style="padding: 0;">
        <redoc spec-url="{{ route('cabinet.api-docs.openapi') }}"
               hide-download-button="false" expand-responses="200,201,202" path-in-middle-panel="true"
               theme='{"typography":{"fontFamily":"inherit","headings":{"fontFamily":"inherit"}}}'></redoc>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/redoc@2.1.5/bundles/redoc.standalone.js"></script>
@endsection
