@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconPosition' => 'left',
    'href' => null,
    'type' => 'button'
])

@php
    $classes = 'btn';

    // Variant classes
    $classes .= match($variant) {
        'accent' => ' btn-accent',
        'secondary' => ' btn-secondary',
        'ghost' => ' btn-ghost',
        'danger' => ' btn-danger',
        'success' => ' btn-success',
        default => ' btn-primary'
    };

    // Size classes
    $classes .= match($size) {
        'sm' => ' btn-sm',
        'lg' => ' btn-lg',
        default => ' btn-md'
    };
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon && $iconPosition === 'left')
            <i data-lucide="{{ $icon }}" class="icon-sm"></i>
        @endif
        {{ $slot }}
        @if($icon && $iconPosition === 'right')
            <i data-lucide="{{ $icon }}" class="icon-sm"></i>
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon && $iconPosition === 'left')
            <i data-lucide="{{ $icon }}" class="icon-sm"></i>
        @endif
        {{ $slot }}
        @if($icon && $iconPosition === 'right')
            <i data-lucide="{{ $icon }}" class="icon-sm"></i>
        @endif
    </button>
@endif
