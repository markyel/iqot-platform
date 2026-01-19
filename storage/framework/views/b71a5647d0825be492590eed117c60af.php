

<?php $__env->startSection('title', 'Настройки системы'); ?>

<?php $__env->startSection('content'); ?>
<?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Настройки системы','description' => 'Управление параметрами работы системы мониторинга позиций']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Настройки системы','description' => 'Управление параметрами работы системы мониторинга позиций']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<div style="max-width: 900px; margin: 0 auto;">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="alert alert-info" style="margin-bottom: var(--space-6);">
        <div style="display: flex; align-items: start; gap: var(--space-3);">
            <i data-lucide="info" class="icon-md"></i>
            <div>
                <strong>Информация</strong>
                <p style="margin-top: var(--space-1); margin-bottom: 0;">
                    Здесь вы можете настроить основные параметры работы системы мониторинга позиций.
                    Изменения вступают в силу немедленно для всех пользователей.
                </p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="settings" class="icon-md"></i>
                Настройки ценообразования
            </h2>
        </div>
        <div class="card-body">
        <form method="POST" action="<?php echo e(route('admin.settings.update')); ?>">
            <?php echo csrf_field(); ?>

            <h3 style="margin: 0 0 var(--space-4) 0; font-size: var(--text-base); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="dollar-sign" class="icon-sm"></i>
                Базовые настройки системы
            </h3>

            <div class="form-group">
                <label class="form-label">
                    Стоимость разблокировки отчета по позиции (₽)
                </label>
                <input
                    type="number"
                    name="item_unlock_price"
                    value="<?php echo e($unlockPrice); ?>"
                    step="0.01"
                    min="0"
                    class="input"
                    style="max-width: 400px;"
                    required
                >
                <small class="form-help">
                    Эта сумма будет списываться с баланса пользователя при получении полного доступа к отчету по позиции
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Стоимость мониторинга одной позиции в заявке (₽)
                </label>
                <input
                    type="number"
                    name="price_per_item"
                    value="<?php echo e($pricePerItem); ?>"
                    step="0.01"
                    min="0"
                    class="input"
                    style="max-width: 400px;"
                    required
                >
                <small class="form-help">
                    Эта сумма замораживается на балансе при создании заявки (за каждую позицию). После обработки заявки средства списываются
                </small>
            </div>

            <h3 style="margin: var(--space-8) 0 var(--space-4) 0; font-size: var(--text-base); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="tag" class="icon-sm"></i>
                Тарифы для лендинга
            </h3>

            <div class="form-group">
                <label class="form-label">
                    Стоимость мониторинга позиции в заявке (₽)
                </label>
                <input
                    type="number"
                    name="pricing_monitoring"
                    value="<?php echo e($pricingMonitoring); ?>"
                    step="0.01"
                    min="0"
                    class="input"
                    style="max-width: 400px;"
                    required
                >
                <small class="form-help">
                    Цена за мониторинг одной позиции для разовых операций (отображается на лендинге)
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Стоимость разблокировки отчета (₽)
                </label>
                <input
                    type="number"
                    name="pricing_report_unlock"
                    value="<?php echo e($pricingReportUnlock); ?>"
                    step="0.01"
                    min="0"
                    class="input"
                    style="max-width: 400px;"
                    required
                >
                <small class="form-help">
                    Цена за разблокировку отчета для разовых операций (отображается на лендинге)
                </small>
            </div>

            <h3 style="margin: var(--space-8) 0 var(--space-4) 0; font-size: var(--text-base); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="zap" class="icon-sm"></i>
                Тариф «Базовый»
            </h3>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-4);">
                <div class="form-group">
                    <label class="form-label">Стоимость подписки (₽/мес)</label>
                    <input type="number" name="subscription_basic_price" value="<?php echo e($subscriptionBasicPrice); ?>" step="0.01" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Позиций в месяц (шт)</label>
                    <input type="number" name="subscription_basic_positions" value="<?php echo e($subscriptionBasicPositions); ?>" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Отчетов в месяц (шт)</label>
                    <input type="number" name="subscription_basic_reports" value="<?php echo e($subscriptionBasicReports); ?>" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Сверх лимита: позиция (₽)</label>
                    <input type="number" name="subscription_basic_overlimit_position" value="<?php echo e($subscriptionBasicOverlimitPosition); ?>" step="0.01" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Сверх лимита: отчет (₽)</label>
                    <input type="number" name="subscription_basic_overlimit_report" value="<?php echo e($subscriptionBasicOverlimitReport); ?>" step="0.01" min="0" class="input" required>
                </div>
            </div>

            <h3 style="margin: var(--space-8) 0 var(--space-4) 0; font-size: var(--text-base); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="trending-up" class="icon-sm"></i>
                Тариф «Расширенный»
            </h3>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-4);">
                <div class="form-group">
                    <label class="form-label">Стоимость подписки (₽/мес)</label>
                    <input type="number" name="subscription_advanced_price" value="<?php echo e($subscriptionAdvancedPrice); ?>" step="0.01" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Позиций в месяц (шт)</label>
                    <input type="number" name="subscription_advanced_positions" value="<?php echo e($subscriptionAdvancedPositions); ?>" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Отчетов в месяц (шт)</label>
                    <input type="number" name="subscription_advanced_reports" value="<?php echo e($subscriptionAdvancedReports); ?>" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Сверх лимита: позиция (₽)</label>
                    <input type="number" name="subscription_advanced_overlimit_position" value="<?php echo e($subscriptionAdvancedOverlimitPosition); ?>" step="0.01" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Сверх лимита: отчет (₽)</label>
                    <input type="number" name="subscription_advanced_overlimit_report" value="<?php echo e($subscriptionAdvancedOverlimitReport); ?>" step="0.01" min="0" class="input" required>
                </div>
            </div>

            <h3 style="margin: var(--space-8) 0 var(--space-4) 0; font-size: var(--text-base); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="star" class="icon-sm"></i>
                Тариф «Профессиональный»
            </h3>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-4);">
                <div class="form-group">
                    <label class="form-label">Стоимость подписки (₽/мес)</label>
                    <input type="number" name="subscription_pro_price" value="<?php echo e($subscriptionProPrice); ?>" step="0.01" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Позиций в месяц (шт)</label>
                    <input type="number" name="subscription_pro_positions" value="<?php echo e($subscriptionProPositions); ?>" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Отчетов в месяц (шт)</label>
                    <input type="number" name="subscription_pro_reports" value="<?php echo e($subscriptionProReports); ?>" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Сверх лимита: позиция (₽)</label>
                    <input type="number" name="subscription_pro_overlimit_position" value="<?php echo e($subscriptionProOverlimitPosition); ?>" step="0.01" min="0" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Сверх лимита: отчет (₽)</label>
                    <input type="number" name="subscription_pro_overlimit_report" value="<?php echo e($subscriptionProOverlimitReport); ?>" step="0.01" min="0" class="input" required>
                </div>
            </div>

            <div style="margin-top: var(--space-8);">
                <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['variant' => 'accent','type' => 'submit','icon' => 'check']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'accent','type' => 'submit','icon' => 'check']); ?>
                    Сохранить настройки
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
            </div>
        </form>
        </div>
    </div>

    <div class="card" style="margin-top: var(--space-6);">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="bar-chart-3" class="icon-md"></i>
                Статистика системы
            </h2>
        </div>
        <div class="card-body">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4);">
            <?php if (isset($component)) { $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stat-card','data' => ['icon' => 'dollar-sign','iconType' => 'success','value' => number_format($unlockPrice, 0) . ' ₽','label' => 'Цена разблокировки']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'dollar-sign','icon-type' => 'success','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(number_format($unlockPrice, 0) . ' ₽'),'label' => 'Цена разблокировки']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $attributes = $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $component = $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stat-card','data' => ['icon' => 'package','iconType' => 'success','value' => number_format($pricePerItem, 0) . ' ₽','label' => 'Цена за позицию']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'package','icon-type' => 'success','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(number_format($pricePerItem, 0) . ' ₽'),'label' => 'Цена за позицию']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $attributes = $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $component = $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stat-card','data' => ['icon' => 'users','iconType' => 'primary','value' => \App\Models\User::count(),'label' => 'Всего пользователей']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'users','icon-type' => 'primary','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\App\Models\User::count()),'label' => 'Всего пользователей']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $attributes = $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $component = $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stat-card','data' => ['icon' => 'shopping-cart','iconType' => 'primary','value' => \App\Models\ItemPurchase::count(),'label' => 'Всего покупок']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'shopping-cart','icon-type' => 'primary','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\App\Models\ItemPurchase::count()),'label' => 'Всего покупок']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $attributes = $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $component = $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stat-card','data' => ['icon' => 'trending-up','iconType' => 'accent','value' => number_format(\App\Models\ItemPurchase::sum('amount'), 2) . ' ₽','label' => 'Общая выручка']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'trending-up','icon-type' => 'accent','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(number_format(\App\Models\ItemPurchase::sum('amount'), 2) . ' ₽'),'label' => 'Общая выручка']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $attributes = $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $component = $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
        </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    lucide.createIcons();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/admin/settings/index.blade.php ENDPATH**/ ?>