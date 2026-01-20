

<?php $__env->startSection('title', 'Заявка ' . $externalRequest->request_number); ?>

<?php $__env->startSection('content'); ?>
<div style="max-width: 1600px; margin: 0 auto;">
    <div style="margin-bottom: var(--space-6);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
            <a href="<?php echo e(route('admin.manage.requests.show', $externalRequest->id)); ?>" style="color: var(--primary-600); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="arrow-left" style="width: 1rem; height: 1rem;"></i>
                Назад к заявке
            </a>

            <?php
                $pdfReport = \App\Models\Report::where('request_id', $externalRequest->id)
                    ->where('report_type', 'request')
                    ->orderBy('created_at', 'desc')
                    ->first();
            ?>
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pdfReport && $pdfReport->status === 'ready'): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pdfReport->pdf_expires_at && $pdfReport->pdf_expires_at->isPast()): ?>
                        <span style="color: #dc2626; font-size: 0.875rem;">PDF истек</span>
                        <form action="<?php echo e(route('admin.manage.requests.generate-pdf', $externalRequest->id)); ?>" method="POST" style="display: inline;">
                            <?php echo csrf_field(); ?>
                            <button type="submit" style="background: var(--primary-600); color: white; border: none; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i data-lucide="file-text" style="width: 1rem; height: 1rem;"></i>
                                Экспортировать в PDF
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?php echo e(route('admin.manage.requests.download-pdf', $externalRequest->id)); ?>" style="background: var(--success-600); color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="download" style="width: 1rem; height: 1rem;"></i>
                            Скачать PDF
                        </a>
                        <form action="<?php echo e(route('admin.manage.requests.generate-pdf', $externalRequest->id)); ?>" method="POST" style="display: inline;">
                            <?php echo csrf_field(); ?>
                            <button type="submit" style="background: var(--warning-600); color: white; border: none; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i data-lucide="refresh-cw" style="width: 1rem; height: 1rem;"></i>
                                Обновить PDF отчет
                            </button>
                        </form>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php elseif($pdfReport && $pdfReport->status === 'generating'): ?>
                    <span style="color: #f59e0b; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i data-lucide="loader-2" style="width: 1rem; height: 1rem; animation: spin 1s linear infinite;"></i>
                        Генерация PDF...
                    </span>
                <?php else: ?>
                    <form action="<?php echo e(route('admin.manage.requests.generate-pdf', $externalRequest->id)); ?>" method="POST" style="display: inline;">
                        <?php echo csrf_field(); ?>
                        <button type="submit" style="background: var(--primary-600); color: white; border: none; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="file-text" style="width: 1rem; height: 1rem;"></i>
                            Экспортировать в PDF
                        </button>
                    </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: start; margin-top: var(--space-4);">
            <div>
                <h1 style="font-size: 2rem; font-weight: 700; color: var(--neutral-900); margin-bottom: var(--space-2);">
                    Заявка <?php echo e($externalRequest->request_number); ?>

                </h1>
                <p style="color: var(--neutral-600);">
                    Создана <?php echo e($externalRequest->created_at ? $externalRequest->created_at->format('d.m.Y в H:i') : '—'); ?>

                </p>
            </div>
            <div>
                <?php
                    $statusMap = [
                        'draft' => 'secondary',
                        'new' => 'info',
                        'active' => 'success',
                        'collecting' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'emails_sent' => 'info',
                        'responses_received' => 'primary',
                        'queued_for_sending' => 'warning'
                    ];
                    $statusType = $statusMap[$externalRequest->status] ?? 'secondary';
                    $statusLabel = \App\Models\ExternalRequest::getStatusLabels()[$externalRequest->status] ?? $externalRequest->status;
                ?>
                <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['type' => ''.e($statusType).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => ''.e($statusType).'']); ?><?php echo e($statusLabel); ?> <?php echo $__env->renderComponent(); ?>
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
    </div>

    <!-- Основная информация о заявке -->
    <div class="card">
        <div class="card-header">
            <h2>Информация о заявке</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6);">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->title): ?>
                <div>
                    <div class="form-label" style="margin-bottom: var(--space-1);">Название</div>
                    <div style="color: var(--neutral-900); font-weight: 600;"><?php echo e($externalRequest->title); ?></div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->customer_company): ?>
                <div>
                    <div class="form-label" style="margin-bottom: var(--space-1);">Компания клиента</div>
                    <div style="color: var(--neutral-900); font-weight: 600;"><?php echo e($externalRequest->customer_company); ?></div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->customer_contact_person): ?>
                <div>
                    <div class="form-label" style="margin-bottom: var(--space-1);">Контактное лицо</div>
                    <div style="color: var(--neutral-900); font-weight: 600;"><?php echo e($externalRequest->customer_contact_person); ?></div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->customer_email): ?>
                <div>
                    <div class="form-label" style="margin-bottom: var(--space-1);">Email клиента</div>
                    <div style="color: var(--neutral-900); font-weight: 600;">
                        <a href="mailto:<?php echo e($externalRequest->customer_email); ?>" style="color: var(--primary-600); text-decoration: none;">
                            <?php echo e($externalRequest->customer_email); ?>

                        </a>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->customer_phone): ?>
                <div>
                    <div class="form-label" style="margin-bottom: var(--space-1);">Телефон клиента</div>
                    <div style="color: var(--neutral-900); font-weight: 600;"><?php echo e($externalRequest->customer_phone); ?></div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->collection_deadline): ?>
                <div>
                    <div class="form-label" style="margin-bottom: var(--space-1);">Срок сбора</div>
                    <div style="color: var(--neutral-900); font-weight: 600;"><?php echo e($externalRequest->collection_deadline->format('d.m.Y H:i')); ?></div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <!-- Прогресс выполнения -->
            <div>
                <div class="form-label" style="margin-bottom: var(--space-2);">Прогресс выполнения</div>
                <div style="display: flex; align-items: center; gap: var(--space-4);">
                    <div style="flex: 1;">
                        <div style="width: 100%; height: 8px; background: var(--neutral-200); border-radius: var(--radius-sm); overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(90deg, var(--primary-500), var(--primary-600)); width: <?php echo e($externalRequest->completion_percentage); ?>%; transition: width 0.3s;"></div>
                        </div>
                    </div>
                    <div style="color: var(--neutral-900); font-weight: 700; font-size: 1.125rem;">
                        <?php echo e(number_format($externalRequest->completion_percentage, 0)); ?>%
                    </div>
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->notes): ?>
            <div style="margin-top: var(--space-6); padding-top: var(--space-6); border-top: 1px solid var(--neutral-200);">
                <div class="form-label" style="margin-bottom: var(--space-2);">Заметки</div>
                <div style="color: var(--neutral-700); white-space: pre-wrap;"><?php echo e($externalRequest->notes); ?></div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <!-- Статистика -->
    <?php
        // Пересчитываем актуальное количество позиций с предложениями
        $actualItemsWithOffers = $externalRequest->items->filter(function($item) {
            return $item->offers->count() > 0;
        })->count();
        $actualTotalItems = $externalRequest->items->count();
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
        <?php if (isset($component)) { $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stat-card','data' => ['label' => 'Всего позиций','value' => $actualTotalItems]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Всего позиций','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($actualTotalItems)]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stat-card','data' => ['label' => 'С предложениями','value' => $actualItemsWithOffers,'variant' => 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'С предложениями','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($actualItemsWithOffers),'variant' => 'success']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stat-card','data' => ['label' => 'Процент закрытия','value' => ($actualTotalItems > 0 ? number_format(($actualItemsWithOffers / $actualTotalItems) * 100, 0) : 0) . '%','variant' => 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Процент закрытия','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($actualTotalItems > 0 ? number_format(($actualItemsWithOffers / $actualTotalItems) * 100, 0) : 0) . '%'),'variant' => 'success']); ?>
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

    <!-- Товарные позиции с предложениями -->
    <div class="card">
        <div class="card-header">
            <h2>Товарные позиции (<?php echo e($externalRequest->items->count()); ?>)</h2>
        </div>
        <div class="card-body">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $externalRequest->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div style="background: var(--neutral-50); border: 1px solid var(--neutral-200); border-radius: var(--radius-md); padding: var(--space-6); margin-bottom: var(--space-6);">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: var(--space-4); padding-bottom: var(--space-4); border-bottom: 1px solid var(--neutral-200);">
                    <div style="flex: 1;">
                        <div style="color: var(--neutral-600); font-size: 0.875rem; margin-bottom: var(--space-1);">
                            Позиция #<?php echo e($item->position_number); ?>

                        </div>
                        <div style="color: var(--neutral-900); font-weight: 600; font-size: 1.125rem; margin-bottom: var(--space-2);"><?php echo e($item->name); ?></div>
                        <div style="color: var(--neutral-600); font-size: 0.875rem;">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->brand): ?>
                                <span>Бренд: <strong style="color: var(--neutral-700);"><?php echo e($item->brand); ?></strong></span> •
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->article): ?>
                                <span>Артикул: <strong style="color: var(--neutral-700);"><?php echo e($item->article); ?></strong></span> •
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <span>Количество: <strong style="color: var(--neutral-700);"><?php echo e($item->quantity); ?> <?php echo e($item->unit); ?></strong></span>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->description): ?>
                        <div style="color: var(--neutral-600); font-size: 0.875rem; margin-top: var(--space-2);">
                            <?php echo e($item->description); ?>

                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <?php
                            $itemStatusMap = [
                                'draft' => 'secondary',
                                'new' => 'info',
                                'sent' => 'warning',
                                'responses_received' => 'primary',
                                'completed' => 'success',
                                'cancelled' => 'danger'
                            ];
                            $itemStatusType = $itemStatusMap[$item->status] ?? 'secondary';
                            $itemStatusLabel = \App\Models\ExternalRequestItem::getStatusLabels()[$item->status] ?? $item->status;
                        ?>
                        <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['type' => ''.e($itemStatusType).'','size' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => ''.e($itemStatusType).'','size' => 'sm']); ?><?php echo e($itemStatusLabel); ?> <?php echo $__env->renderComponent(); ?>
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

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->offers->count() > 0): ?>
                <!-- Статистика по позиции -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-4); margin-bottom: var(--space-4); padding: var(--space-4); background: white; border: 1px solid var(--neutral-200); border-radius: var(--radius-md);">
                    <div>
                        <div style="color: var(--neutral-600); font-size: 0.75rem;">Предложений</div>
                        <div style="color: var(--success-600); font-weight: 700; font-size: 1.125rem;"><?php echo e($item->offers->count()); ?></div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->min_price): ?>
                    <div>
                        <div style="color: var(--neutral-600); font-size: 0.75rem;">Мин. цена</div>
                        <div style="color: var(--neutral-900); font-weight: 700; font-size: 1.125rem;"><?php echo e(number_format($item->min_price, 2)); ?> ₽</div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->max_price): ?>
                    <div>
                        <div style="color: var(--neutral-600); font-size: 0.75rem;">Макс. цена</div>
                        <div style="color: var(--neutral-900); font-weight: 700; font-size: 1.125rem;"><?php echo e(number_format($item->max_price, 2)); ?> ₽</div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <!-- Таблица предложений -->
                <table class="table">
                    <thead>
                        <tr>
                            <th>Поставщик</th>
                            <th>Цена за ед.</th>
                            <th>Общая цена</th>
                            <th>Срок поставки</th>
                            <th>Условия оплаты</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $item->offers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $offer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td data-label="Поставщик">
                                <div style="font-weight: 600; color: var(--neutral-900);"><?php echo e($offer->supplier->name ?? 'Не указан'); ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->supplier && $offer->supplier->email): ?>
                                <div style="color: var(--neutral-500); font-size: 0.75rem; margin-top: var(--space-1);">
                                    <?php echo e($offer->supplier->email); ?>

                                </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td data-label="Цена за ед.">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->price_per_unit): ?>
                                <div <?php if($index === 0): ?> style="background: var(--success-50); border: 1px solid var(--success-200); border-radius: var(--radius-sm); padding: var(--space-2);" <?php endif; ?>>
                                    <span style="color: var(--success-600); font-weight: 700;"><?php echo e(number_format($offer->price_per_unit_in_rub, 2)); ?> ₽</span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->currency !== 'RUB'): ?>
                                        <div style="color: var(--neutral-600); font-size: 0.75rem;"><?php echo e(number_format($offer->price_per_unit, 2)); ?> <?php echo e($offer->currency); ?></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->price_includes_vat): ?>
                                    <div style="color: var(--neutral-600); font-size: 0.75rem;">с НДС</div>
                                    <?php else: ?>
                                    <div style="color: var(--neutral-600); font-size: 0.75rem;">без НДС</div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <?php else: ?>
                                <span style="color: var(--neutral-400);">—</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td data-label="Общая цена">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->total_price): ?>
                                <span style="font-weight: 600;"><?php echo e(number_format($offer->total_price_in_rub, 2)); ?> ₽</span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->currency !== 'RUB'): ?>
                                    <div style="color: var(--neutral-600); font-size: 0.75rem;"><?php echo e(number_format($offer->total_price, 2)); ?> <?php echo e($offer->currency); ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php else: ?>
                                <span style="color: var(--neutral-400);">—</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td data-label="Срок поставки">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->delivery_days): ?>
                                <span><?php echo e($offer->delivery_days); ?> дн.</span>
                                <?php else: ?>
                                <span style="color: var(--neutral-400);">—</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td data-label="Условия оплаты">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->payment_terms): ?>
                                <span><?php echo e($offer->payment_terms); ?></span>
                                <?php else: ?>
                                <span style="color: var(--neutral-400);">—</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td data-label="Статус">
                                <?php
                                    $offerStatusMap = [
                                        'pending' => 'warning',
                                        'accepted' => 'success',
                                        'rejected' => 'danger',
                                        'cancelled' => 'secondary'
                                    ];
                                    $offerStatusType = $offerStatusMap[$offer->status] ?? 'secondary';
                                    $offerStatusLabel = \App\Models\ExternalOffer::getStatusLabels()[$offer->status] ?? $offer->status;
                                ?>
                                <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['type' => ''.e($offerStatusType).'','size' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => ''.e($offerStatusType).'','size' => 'sm']); ?><?php echo e($offerStatusLabel); ?> <?php echo $__env->renderComponent(); ?>
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
                        </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->notes): ?>
                        <tr>
                            <td colspan="6" style="background: var(--neutral-50); padding: var(--space-3);">
                                <div style="color: var(--neutral-600); font-size: 0.75rem; margin-bottom: var(--space-1);">Примечание:</div>
                                <div style="color: var(--neutral-900); font-size: 0.875rem;"><?php echo e($offer->notes); ?></div>
                            </td>
                        </tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'package-x','title' => 'Нет предложений','description' => 'По данной позиции предложения от поставщиков отсутствуют']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'package-x','title' => 'Нет предложений','description' => 'По данной позиции предложения от поставщиков отсутствуют']); ?>
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
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'inbox','title' => 'Нет позиций','description' => 'Товарные позиции в заявке отсутствуют']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'inbox','title' => 'Нет позиций','description' => 'Товарные позиции в заявке отсутствуют']); ?>
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
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}

// Проверяем статус генерации PDF и обновляем страницу когда готово
document.addEventListener('DOMContentLoaded', function() {
    console.log('PDF auto-reload script loaded');

    // Проверяем, есть ли на странице элемент со статусом "Генерация PDF..."
    const checkGeneratingStatus = () => {
        // Ищем именно span с цветом #f59e0b (оранжевый), который используется для статуса генерации
        const generatingSpans = document.querySelectorAll('span[style*="color: #f59e0b"], span[style*="color:#f59e0b"]');
        for (let span of generatingSpans) {
            if (span.textContent.trim().includes('Генерация PDF')) {
                return true;
            }
        }
        return false;
    };

    // Проверяем, есть ли кнопка скачать PDF
    const checkDownloadButton = () => {
        const links = document.querySelectorAll('a');
        for (let link of links) {
            if (link.textContent.trim().includes('Скачать PDF')) {
                return true;
            }
        }
        return false;
    };

    const isGenerating = checkGeneratingStatus();
    const hasDownload = checkDownloadButton();

    console.log('Статус на странице: генерация=' + isGenerating + ', скачать=' + hasDownload);

    if (isGenerating && !hasDownload) {
        console.log('Обнаружена генерация PDF, запускаем автообновление...');

        let checkCount = 0;
        let pdfCheckInterval = setInterval(function() {
            checkCount++;
            console.log('Проверяем статус PDF... (попытка ' + checkCount + ')');

            fetch(window.location.href, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                // Проверяем наличие ссылки "Скачать PDF" в HTML
                const hasDownloadButton = html.includes('Скачать PDF') && html.includes('download-pdf');

                console.log('Ответ от сервера: кнопка скачать=' + hasDownloadButton);

                // Если появилась кнопка скачать, перезагружаем страницу
                if (hasDownloadButton) {
                    console.log('PDF готов, перезагружаем страницу...');
                    clearInterval(pdfCheckInterval);
                    window.location.reload();
                }

                // Ограничиваем количество попыток (макс 60 попыток = 3 минуты)
                if (checkCount >= 60) {
                    console.warn('Превышено максимальное время ожидания генерации PDF');
                    clearInterval(pdfCheckInterval);
                }
            })
            .catch(err => {
                console.error('Ошибка проверки статуса PDF:', err);
            });
        }, 3000); // Проверяем каждые 3 секунды
    } else {
        console.log('Генерация PDF не обнаружена или PDF уже готов, автообновление не требуется');
    }
});
</script>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('styles'); ?>
<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/admin/manage/requests/report.blade.php ENDPATH**/ ?>