<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'value',
    'label',
    'icon' => null,
    'iconType' => 'primary'
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
    'value',
    'label',
    'icon' => null,
    'iconType' => 'primary'
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    $iconClass = match($iconType) {
        'accent' => 'stat-icon-accent',
        'success' => 'stat-icon-success',
        'warning' => 'stat-icon-warning',
        'error' => 'stat-icon-error',
        default => 'stat-icon-primary'
    };
?>

<div class="stat-card">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($icon): ?>
    <div class="stat-icon <?php echo e($iconClass); ?>">
        <i data-lucide="<?php echo e($icon); ?>" class="icon-lg"></i>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="stat-content">
        <div class="stat-value"><?php echo e($value); ?></div>
        <div class="stat-label"><?php echo e($label); ?></div>
    </div>
</div>
<?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/components/stat-card.blade.php ENDPATH**/ ?>