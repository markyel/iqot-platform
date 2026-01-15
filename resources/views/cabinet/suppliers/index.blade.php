@extends('layouts.cabinet')

@section('title', 'Поставщики')

@section('content')
<x-empty-state
    icon="building"
    title="База поставщиков"
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
