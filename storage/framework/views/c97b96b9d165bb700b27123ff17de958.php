<?php $__env->startSection('title', 'Пользователь: ' . $user->name); ?>

<?php $__env->startSection('content'); ?>
<?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => $user->name,'description' => $user->email]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user->name),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user->email)]); ?>
     <?php $__env->slot('actions', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['variant' => 'secondary','href' => route('admin.users.index'),'icon' => 'arrow-left']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'secondary','href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.users.index')),'icon' => 'arrow-left']); ?>
            Назад к списку
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
        <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['variant' => 'accent','type' => 'button','icon' => 'wallet','onclick' => 'openBalanceModal('.e($user->id).', \''.e(addslashes($user->name)).'\', '.e($user->balance ?? 0).')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'accent','type' => 'button','icon' => 'wallet','onclick' => 'openBalanceModal('.e($user->id).', \''.e(addslashes($user->name)).'\', '.e($user->balance ?? 0).')']); ?>
            Управление балансом
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
     <?php $__env->endSlot(); ?>
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

<!-- Анкета пользователя -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title">Информация о пользователе</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-6);">
            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Имя</div>
                <div style="font-size: var(--text-lg); font-weight: 600;"><?php echo e($user->name); ?></div>
            </div>

            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Email</div>
                <div style="font-size: var(--text-base);"><?php echo e($user->email); ?></div>
            </div>

            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Компания</div>
                <div style="font-size: var(--text-base);"><?php echo e($user->company ?? '—'); ?></div>
            </div>

            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Телефон</div>
                <div style="font-size: var(--text-base);"><?php echo e($user->phone ?? '—'); ?></div>
            </div>

            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Роль</div>
                <div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->is_admin): ?>
                        <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['variant' => 'info','size' => 'md']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'info','size' => 'md']); ?>Администратор <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                    <?php else: ?>
                        <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['variant' => 'secondary','size' => 'md']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'secondary','size' => 'md']); ?>Пользователь <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Sender ID</div>
                <div style="font-size: var(--text-base);">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->sender_id): ?>
                        <span style="color: var(--success-600); font-weight: 600;"><?php echo e($user->sender_id); ?></span>
                        <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['variant' => 'secondary','size' => 'sm','href' => route('admin.users.sender.show', $user),'icon' => 'external-link','style' => 'margin-left: var(--space-2);']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'secondary','size' => 'sm','href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.users.sender.show', $user)),'icon' => 'external-link','style' => 'margin-left: var(--space-2);']); ?>
                            Управление
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
                    <?php else: ?>
                        <span style="color: var(--neutral-400);">Не настроен</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Дата регистрации</div>
                <div style="font-size: var(--text-base);"><?php echo e($user->created_at->format('d.m.Y H:i')); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Баланс и Тариф -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6);">
    <!-- Баланс -->
    <div class="card">
        <div class="card-header">
            <i data-lucide="wallet" style="width: 1.25rem; height: 1.25rem;"></i>
            Баланс
        </div>
        <div class="card-body">
            <div style="display: grid; gap: var(--space-4);">
                <div>
                    <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-bottom: var(--space-1);">Доступный баланс</div>
                    <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--primary-600);">
                        <?php echo e(number_format($user->available_balance, 2)); ?> ₽
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div>
                        <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-bottom: var(--space-1);">Заморожено</div>
                        <div style="font-size: var(--text-lg); font-weight: 600; color: var(--warning-600);">
                            <?php echo e(number_format($user->held_balance, 2)); ?> ₽
                        </div>
                    </div>

                    <div>
                        <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-bottom: var(--space-1);">Всего</div>
                        <div style="font-size: var(--text-lg); font-weight: 600;">
                            <?php echo e(number_format($user->balance, 2)); ?> ₽
                        </div>
                    </div>
                </div>

                <div style="padding-top: var(--space-3); border-top: 1px solid var(--neutral-200);">
                    <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-2);">Потрачено всего</div>
                    <div style="font-size: var(--text-xl); font-weight: 600; color: var(--neutral-700);">
                        <?php echo e(number_format($user->purchases_sum, 2)); ?> ₽
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Тариф -->
    <div class="card">
        <div class="card-header">
            <i data-lucide="zap" style="width: 1.25rem; height: 1.25rem;"></i>
            Тариф
        </div>
        <div class="card-body">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tariff): ?>
                <div style="margin-bottom: var(--space-4);">
                    <div style="font-size: var(--text-2xl); font-weight: 700; color: var(--primary-600); margin-bottom: var(--space-2);">
                        <?php echo e($tariff->tariffPlan->name); ?>

                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tariff->expires_at): ?>
                        <div style="font-size: var(--text-sm); color: var(--neutral-600);">
                            Действует до <?php echo e($tariff->expires_at->format('d.m.Y')); ?>

                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($limitsInfo): ?>
                <div style="display: grid; gap: var(--space-4);">
                    <!-- Лимит позиций -->
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($limitsInfo['items_limit'] !== null): ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                            <span style="font-size: var(--text-sm); color: var(--neutral-600);">Позиции</span>
                            <span style="font-size: var(--text-sm); font-weight: 600;"><?php echo e($limitsInfo['items_used']); ?> / <?php echo e($limitsInfo['items_limit']); ?></span>
                        </div>
                        <div style="height: 8px; background: var(--neutral-200); border-radius: var(--radius-full); overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(90deg, var(--success-500), var(--primary-500)); width: <?php echo e(min(100, $limitsInfo['items_used_percentage'] ?? 0)); ?>%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <!-- Лимит отчетов -->
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($limitsInfo['reports_limit'] !== null): ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                            <span style="font-size: var(--text-sm); color: var(--neutral-600);">Отчеты</span>
                            <span style="font-size: var(--text-sm); font-weight: 600;"><?php echo e($limitsInfo['reports_used']); ?> / <?php echo e($limitsInfo['reports_limit']); ?></span>
                        </div>
                        <div style="height: 8px; background: var(--neutral-200); border-radius: var(--radius-full); overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(90deg, var(--accent-500), var(--warning-500)); width: <?php echo e(min(100, $limitsInfo['reports_used_percentage'] ?? 0)); ?>%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($limitsInfo['items_limit'] === null && $limitsInfo['reports_limit'] === null): ?>
                    <div style="padding: var(--space-4); background: var(--neutral-50); border-radius: var(--radius-md); text-align: center;">
                        <div style="font-size: var(--text-sm); color: var(--neutral-600);">Без включенных кредитов</div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php else: ?>
                <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'alert-circle','title' => 'Нет активного тарифа','description' => 'Пользователь не подключен к тарифному плану']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'alert-circle','title' => 'Нет активного тарифа','description' => 'Пользователь не подключен к тарифному плану']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $attributes = $__attributesOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__attributesOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $component = $__componentOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__componentOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div>

<!-- Статистика -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--primary-600); margin-bottom: var(--space-2);">
                <?php echo e($requestsStats['total']); ?>

            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Всего заявок</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--success-600); margin-bottom: var(--space-2);">
                <?php echo e($requestsStats['completed']); ?>

            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Завершено</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--warning-600); margin-bottom: var(--space-2);">
                <?php echo e($requestsStats['pending']); ?>

            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">В обработке</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--accent-600); margin-bottom: var(--space-2);">
                <?php echo e($reportAccessCount); ?>

            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Отчетов открыто</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--info-600); margin-bottom: var(--space-2);">
                <?php echo e($itemPurchasesCount); ?>

            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Позиций куплено</div>
        </div>
    </div>
</div>

<!-- Последние заявки -->
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->requests->count() > 0): ?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Последние заявки</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Номер</th>
                    <th>Название</th>
                    <th>Статус</th>
                    <th>Позиций</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $user->requests->take(10); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td style="font-family: var(--font-mono); font-weight: 600;"><?php echo e($request->request_number ?? $request->code); ?></td>
                    <td><?php echo e($request->title ?? 'Без названия'); ?></td>
                    <td>
                        <?php
                            $statusVariant = match($request->status) {
                                'draft' => 'secondary',
                                'pending' => 'warning',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'info'
                            };
                            $statusText = \App\Models\Request::statuses()[$request->status] ?? $request->status;
                        ?>
                        <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['variant' => $statusVariant,'size' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statusVariant),'size' => 'sm']); ?><?php echo e($statusText); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                    </td>
                    <td><?php echo e($request->items_count ?? 0); ?></td>
                    <td style="color: var(--neutral-600); font-size: var(--text-sm);"><?php echo e($request->created_at->format('d.m.Y H:i')); ?></td>
                    <td>
                        <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['variant' => 'primary','size' => 'sm','href' => route('admin.requests.show', $request),'icon' => 'eye']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'primary','size' => 'sm','href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.requests.show', $request)),'icon' => 'eye']); ?>
                            Открыть
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
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<!-- Balance Modal -->
<div id="balanceModal" class="modal-overlay" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-container">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: var(--space-3);">
                    <div style="width: 40px; height: 40px; border-radius: var(--radius-lg); background: var(--accent-100); display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="wallet" style="width: 1.25rem; height: 1.25rem; color: var(--accent-600);"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">Управление балансом</h3>
                        <p style="margin: 0; font-size: var(--text-sm); color: var(--neutral-600);" id="modalUserName"></p>
                    </div>
                </div>
                <button type="button" onclick="closeBalanceModal()" style="background: none; border: none; cursor: pointer; padding: var(--space-2); color: var(--neutral-600);">
                    <i data-lucide="x" style="width: 1.25rem; height: 1.25rem;"></i>
                </button>
            </div>

            <form id="balanceForm" method="POST">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                    <div style="padding: var(--space-4); background: var(--neutral-50); border-radius: var(--radius-md); margin-bottom: var(--space-4);">
                        <div style="font-size: var(--text-sm); color: var(--neutral-600);">Текущий баланс</div>
                        <div style="font-size: var(--text-2xl); font-weight: 700; color: var(--accent-600);" id="modalCurrentBalance"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Действие</label>
                        <select name="action" class="select" required>
                            <option value="add">Пополнить баланс</option>
                            <option value="subtract">Списать с баланса</option>
                            <option value="set">Установить баланс</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Сумма (₽)</label>
                        <input type="number" name="amount" step="0.01" min="0" class="input" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['type' => 'button','variant' => 'secondary','size' => 'md','onclick' => 'closeBalanceModal()']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','size' => 'md','onclick' => 'closeBalanceModal()']); ?>
                        Отмена
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
                    <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['type' => 'submit','variant' => 'accent','size' => 'md']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','variant' => 'accent','size' => 'md']); ?>
                        Сохранить
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
</div>

<?php $__env->startPush('scripts'); ?>
<script>
function openBalanceModal(userId, userName, currentBalance) {
    document.getElementById('modalUserName').textContent = userName;
    document.getElementById('modalCurrentBalance').textContent = parseFloat(currentBalance).toFixed(2) + ' ₽';
    document.getElementById('balanceForm').action = '/manage/users/' + userId + '/balance';
    document.getElementById('balanceModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    setTimeout(() => lucide.createIcons(), 100);
}

function closeBalanceModal() {
    document.getElementById('balanceModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('click', function(event) {
    const modal = document.getElementById('balanceModal');
    if (event.target === modal) {
        closeBalanceModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeBalanceModal();
    }
});

lucide.createIcons();
</script>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('styles'); ?>
<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(2px);
    z-index: 9999;
    overflow-y: auto;
}

.modal-dialog {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-6);
}

.modal-container {
    background: var(--neutral-0);
    border-radius: var(--radius-xl);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    max-width: 540px;
    width: 100%;
    position: relative;
}

.modal-header {
    padding: var(--space-6);
    border-bottom: 1px solid var(--neutral-200);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
}

.modal-body {
    padding: var(--space-6);
}

.modal-footer {
    padding: var(--space-6);
    border-top: 1px solid var(--neutral-200);
    display: flex;
    justify-content: flex-end;
    gap: var(--space-3);
    background: var(--neutral-50);
    border-bottom-left-radius: var(--radius-xl);
    border-bottom-right-radius: var(--radius-xl);
}
</style>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/admin/users/show.blade.php ENDPATH**/ ?>