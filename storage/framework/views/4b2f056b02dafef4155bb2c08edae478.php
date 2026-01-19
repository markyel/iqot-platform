

<?php $__env->startSection('title', 'Заявка ' . $request->code); ?>

<?php $__env->startSection('content'); ?>
<div style="margin-bottom: var(--space-4);">
    <a href="<?php echo e(route('cabinet.requests')); ?>" class="text-muted" style="text-decoration: none; display: inline-flex; align-items: center; gap: var(--space-2);">
        <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
        Назад к списку
    </a>
</div>

<div style="display: grid; gap: var(--space-4);">
    <!-- Основная информация -->
    <div class="card">
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-3);">
                <div>
                    <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: var(--space-2);"><?php echo e($request->code); ?></h1>
                    <p class="text-muted"><?php echo e($request->title ?? 'Без названия'); ?></p>
                </div>
                <div style="display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap;">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest): ?>
                        <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['href' => ''.e(route('cabinet.my.requests.report', $request->id)).'','variant' => 'purple','icon' => 'bar-chart-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(route('cabinet.my.requests.report', $request->id)).'','variant' => 'purple','icon' => 'bar-chart-2']); ?>
                            Отчет
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
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['href' => ''.e(route('cabinet.my.requests.questions', $request->id)).'','variant' => 'info','icon' => 'message-circle']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(route('cabinet.my.requests.questions', $request->id)).'','variant' => 'info','icon' => 'message-circle']); ?>
                        Вопросы
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
                    <?php
                        $displayStatus = $externalRequest ? $externalRequest->status : $request->status;
                        $displayStatusLabel = $externalRequest
                            ? (\App\Models\ExternalRequest::getStatusLabels()[$displayStatus] ?? $displayStatus)
                            : (\App\Models\Request::statuses()[$displayStatus] ?? $displayStatus);

                        $statusVariant = match($displayStatus) {
                            'draft' => 'neutral',
                            'pending', 'queued_for_sending' => 'warning',
                            'sending', 'new', 'emails_sent' => 'info',
                            'collecting', 'active', 'responses_received' => 'primary',
                            'completed' => 'success',
                            'cancelled' => 'danger',
                            default => 'neutral'
                        };
                    ?>
                    <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['variant' => $statusVariant,'size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statusVariant),'size' => 'lg']); ?>
                        <?php echo e($displayStatusLabel); ?>

                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                </div>
            </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest): ?>
            <div class="alert alert-success" style="margin-bottom: var(--space-4);">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                    <div>
                        <strong>Заявка отправлена поставщикам</strong>
                        <p style="margin: var(--space-1) 0 0 0;">Номер заявки в системе: <?php echo e($externalRequest->request_number); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->description): ?>
        <div style="padding: var(--space-3); background: var(--neutral-50); border-radius: var(--radius-md); margin-bottom: var(--space-4);">
            <strong style="display: block; margin-bottom: var(--space-2);">Описание:</strong>
            <p style="color: var(--neutral-700); margin: 0;"><?php echo e($request->description); ?></p>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-3);">
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Создана</div>
                    <div style="font-weight: 600;"><?php echo e($request->created_at->format('d.m.Y H:i')); ?></div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Позиций</div>
                    <div style="font-weight: 600;"><?php echo e($externalRequest ? $externalRequest->items->count() : $request->items_count); ?></div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Поставщиков</div>
                    <div style="font-weight: 600;"><?php echo e($externalRequest ? $externalRequest->suppliers_count : $request->suppliers_count); ?></div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Предложений</div>
                    <div style="font-weight: 600;"><?php echo e($externalRequest ? $externalRequest->offers_count : $request->offers_count); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Контактная информация -->
    <div class="card">
        <div class="card-body">
            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: var(--space-4);">Контактная информация</h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);">
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Организация</div>
                    <div style="font-weight: 500;">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest && $externalRequest->clientOrganization): ?>
                            <?php echo e($externalRequest->clientOrganization->name); ?>

                        <?php else: ?>
                            <?php echo e($request->company_name ?? '—'); ?>

                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Контактное лицо</div>
                    <div style="font-weight: 500;">
                        <?php echo e($externalRequest->contact_name ?? $request->contact_person ?? '—'); ?>

                    </div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Email</div>
                    <div style="font-weight: 500;">
                        <?php echo e($externalRequest->contact_email ?? '—'); ?>

                    </div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Телефон</div>
                    <div style="font-weight: 500;">
                        <?php echo e($externalRequest->contact_phone ?? $request->contact_phone ?? '—'); ?>

                    </div>
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$externalRequest && !$request->canBeSent()): ?>
                <div class="alert alert-warning" style="margin-top: var(--space-4);">
                    <strong>Заявка не готова к отправке</strong>
                    <ul style="margin-top: var(--space-2); padding-left: var(--space-5);">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $request->getMissingRequiredFields(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li><?php echo e($field); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <!-- Позиции заявки -->
    <div class="card">
        <div class="card-body">
            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: var(--space-4);">Позиции заявки</h2>

            <?php
                $itemsToDisplay = $externalRequest ? $externalRequest->items : $request->items;
            ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($itemsToDisplay->count() > 0): ?>
            <div style="display: grid; gap: var(--space-3);">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $itemsToDisplay; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div style="padding: var(--space-4); background: var(--neutral-50); border-radius: var(--radius-md); border: 1px solid var(--neutral-200);">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: var(--space-3); flex-wrap: wrap; gap: var(--space-2);">
                        <h3 style="font-weight: 600; font-size: 1.125rem; margin: 0;"><?php echo e($item->name ?? $item->item_name); ?></h3>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest): ?>
                            <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['variant' => 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'success']); ?>
                                <i data-lucide="check" style="width: 12px; height: 12px;"></i>
                                В работе
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                        <?php elseif(method_exists($item, 'isValid') && !$item->isValid()): ?>
                            <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['variant' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'danger']); ?>
                                Неполные данные
                             <?php echo $__env->renderComponent(); ?>
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

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-3);">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest): ?>
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Артикул</div>
                                <div style="font-weight: 500; font-family: 'JetBrains Mono', monospace;"><?php echo e($item->article ?? '—'); ?></div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Бренд</div>
                                <div style="font-weight: 500;"><?php echo e($item->brand ?? '—'); ?></div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Количество</div>
                                <div style="font-weight: 500;"><?php echo e($item->quantity ?? '—'); ?></div>
                            </div>
                        <?php else: ?>
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Тип оборудования</div>
                                <div style="font-weight: 500;">
                                    <?php echo e($item->equipment_type ? \App\Models\RequestItem::equipmentTypes()[$item->equipment_type] : '—'); ?>

                                </div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Марка оборудования</div>
                                <div style="font-weight: 500;"><?php echo e($item->equipment_brand ?? '—'); ?></div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Артикул производителя</div>
                                <div style="font-weight: 500; font-family: 'JetBrains Mono', monospace;"><?php echo e($item->manufacturer_article ?? '—'); ?></div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-1);">Количество</div>
                                <div style="font-weight: 500;"><?php echo e($item->quantity ?? '—'); ?></div>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$externalRequest && method_exists($item, 'isValid') && !$item->isValid()): ?>
                        <div style="margin-top: var(--space-3); padding: var(--space-3); background: var(--red-50); border-radius: var(--radius-sm);">
                            <div style="color: var(--red-900); font-size: 0.875rem; font-weight: 600; margin-bottom: var(--space-1);">Не заполнены обязательные поля:</div>
                            <ul style="margin: 0; padding-left: var(--space-5); color: var(--red-900); font-size: 0.875rem;">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $item->getMissingRequiredFields(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li><?php echo e($field); ?></li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php else: ?>
                <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'package','title' => 'Нет позиций','description' => '']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'package','title' => 'Нет позиций','description' => '']); ?>
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

<?php $__env->startPush('scripts'); ?>
<script>
    lucide.createIcons();
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/cabinet/requests/show.blade.php ENDPATH**/ ?>