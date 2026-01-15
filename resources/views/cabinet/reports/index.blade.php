@extends('layouts.cabinet')

@section('title', 'Отчёты')

@section('content')
<x-empty-state
    icon="bar-chart"
    title="Отчёты по заявкам"
    description="Раздел в разработке"
/>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});
</script>
@endpush
@endsection
