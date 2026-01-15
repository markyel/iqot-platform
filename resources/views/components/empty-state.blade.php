@props([
    'icon' => 'inbox',
    'title',
    'description' => null
])

<div class="empty-state">
    <div class="empty-state-icon">
        <i data-lucide="{{ $icon }}" class="icon-xl"></i>
    </div>
    <h3 class="empty-state-title">{{ $title }}</h3>
    @if($description)
        <p class="empty-state-description">{{ $description }}</p>
    @endif
    @if(isset($action))
        <div style="margin-top: var(--space-6);">
            {{ $action }}
        </div>
    @endif
</div>
