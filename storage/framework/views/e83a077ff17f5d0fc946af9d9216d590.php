<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'type' => 'default',
    'size' => 'md',
    'dot' => false
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'type' => 'default',
    'size' => 'md',
    'dot' => false
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
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
?>

<span <?php echo e($attributes->merge(['class' => $classes])); ?>>
    <?php echo e($slot); ?>

</span>
<?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/components/badge.blade.php ENDPATH**/ ?>