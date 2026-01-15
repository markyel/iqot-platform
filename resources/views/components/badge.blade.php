@props([
    'type' => 'default',
    'size' => 'md',
    'dot' => false
])

@php
    $classes = 'badge';

    // Size classes
    $classes .= match($size) {
        'sm' => ' badge-sm',
        'lg' => ' badge-lg',
        default => ''
    };

    // Type classes
    $classes .= match($type) {
        'draft' => ' badge-draft',
        'pending' => ' badge-pending',
        'in-progress', 'in_progress' => ' badge-in-progress',
        'completed' => ' badge-completed',
        'cancelled' => ' badge-cancelled',
        'question-pending' => ' badge-question-pending',
        'question-answered' => ' badge-question-answered',
        'question-skipped' => ' badge-question-skipped',
        'new' => ' badge-in-progress',
        'active' => ' badge-in-progress',
        'queued_for_sending' => ' badge-pending',
        'emails_sent' => ' badge-in-progress',
        'collecting' => ' badge-in-progress',
        'responses_received' => ' badge-in-progress',
        'customer' => ' badge-in-progress',
        'anonymous' => ' badge-draft',
        default => ' badge-draft'
    };

    if ($dot) {
        $classes .= ' badge-dot';
    }
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
