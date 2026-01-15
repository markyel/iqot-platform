@props([
    'value',
    'label',
    'icon' => null,
    'iconType' => 'primary'
])

@php
    $iconClass = match($iconType) {
        'accent' => 'stat-icon-accent',
        'success' => 'stat-icon-success',
        'warning' => 'stat-icon-warning',
        'error' => 'stat-icon-error',
        default => 'stat-icon-primary'
    };
@endphp

<div class="stat-card">
    @if($icon)
    <div class="stat-icon {{ $iconClass }}">
        <i data-lucide="{{ $icon }}" class="icon-lg"></i>
    </div>
    @endif
    <div class="stat-content">
        <div class="stat-value">{{ $value }}</div>
        <div class="stat-label">{{ $label }}</div>
    </div>
</div>
