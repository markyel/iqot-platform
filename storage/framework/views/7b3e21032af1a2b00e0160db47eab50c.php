

<?php $__env->startSection('title', 'Заявки из базы отчетов'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    /* Light theme for admin */
    .admin-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .admin-table {
        width: 100%;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
    }

    .admin-table thead {
        background: #f9fafb;
    }

    .admin-table th {
        text-align: left;
        padding: 1rem 1.5rem;
        color: #6b7280;
        font-weight: 600;
        font-size: 0.875rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .admin-table td {
        padding: 1rem 1.5rem;
        border-top: 1px solid #f3f4f6;
    }

    .admin-table tbody tr:hover {
        background: #f9fafb;
    }

    .status-badge {
        display: inline-block;
        padding: 0.375rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-draft { background: #f3f4f6; color: #6b7280; }
    .status-new { background: #dbeafe; color: #1e40af; }
    .status-active { background: #d1fae5; color: #065f46; }
    .status-collecting { background: #fef3c7; color: #92400e; }
    .status-completed { background: #d1fae5; color: #065f46; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }
    .status-emails-sent { background: #e0e7ff; color: #3730a3; }
    .status-responses-received { background: #ddd6fe; color: #5b21b6; }
    .status-queued-for-sending { background: #fef3c7; color: #78350f; }

    .form-select {
        background: #ffffff;
        border: 1px solid #d1d5db;
        color: #111827;
        padding: 0.625rem 1rem;
        border-radius: 8px;
        outline: none;
    }

    .form-select:focus {
        border-color: #10b981;
    }

    .btn-green {
        background: #10b981;
        color: white;
        padding: 0.625rem 1.5rem;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-green:hover {
        background: #059669;
    }

    .progress-bar {
        width: 120px;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 0.25rem;
    }

    .progress-fill {
        height: 100%;
        background: #10b981;
        transition: width 0.3s;
    }

    .status-progress-col {
        min-width: 180px;
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div style="max-width: 1600px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.875rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem;">Заявки</h1>
        <p style="color: #9ca3af;">Заявки из системы ценовых котировок</p>
    </div>

    <!-- Фильтры -->
    <div class="admin-card">
        <form method="GET" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <select name="status" class="form-select">
                <option value="">Все статусы</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Models\ExternalRequest::getStatusLabels(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($value); ?>" <?php echo e(request('status') === $value ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
            
            <select name="is_customer_request" class="form-select">
                <option value="">Все типы</option>
                <option value="1" <?php echo e(request('is_customer_request') === '1' ? 'selected' : ''); ?>>Клиентские заявки</option>
                <option value="0" <?php echo e(request('is_customer_request') === '0' ? 'selected' : ''); ?>>Внутренние заявки</option>
            </select>
            
            <button type="submit" class="btn-green">Применить</button>
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request()->hasAny(['status', 'is_customer_request'])): ?>
                <a href="<?php echo e(route('admin.external-requests.index')); ?>" style="color: #9ca3af; text-decoration: none; padding: 0.625rem 1rem;">
                    Сбросить
                </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </form>
    </div>

    <!-- Список заявок -->
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width: 150px;">Номер</th>
                <th>Заголовок / Позиции</th>
                <th style="width: 200px;">Статус / Прогресс</th>
                <th style="width: 140px;">Дата создания</th>
                <th style="width: 120px;">Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
                <td>
                    <div style="color: #111827; font-family: monospace; font-weight: 600;"><?php echo e($request->request_number); ?></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->is_customer_request): ?>
                        <span style="color: #10b981; font-size: 0.75rem; display: block; margin-top: 0.25rem;">👤 Клиентская</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td>
                    <div style="color: #111827; font-weight: 500; margin-bottom: 0.5rem;"><?php echo e($request->title ?: '—'); ?></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->items->count() > 0): ?>
                        <div style="background: #f9fafb; border-radius: 4px; padding: 0.5rem; margin-top: 0.5rem;">
                            <div style="color: #6b7280; font-size: 0.75rem; margin-bottom: 0.25rem;">Позиции (<?php echo e($request->total_items); ?>):</div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $request->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div style="color: #374151; font-size: 0.75rem; padding: 0.125rem 0;">
                                    <?php echo e($item->position_number); ?>. <?php echo e(Str::limit($item->name, 50)); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->offers_count > 0): ?>
                                        <span style="color: #059669; font-weight: 600;">(<?php echo e($item->offers_count); ?>)</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->total_items > $request->items->count()): ?>
                                <div style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem; font-style: italic;">
                                    и ещё <?php echo e($request->total_items - $request->items->count()); ?>...
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td class="status-progress-col">
                    <?php
                        $statusClass = 'status-' . str_replace('_', '-', $request->status);
                        $statusLabel = \App\Models\ExternalRequest::getStatusLabels()[$request->status] ?? $request->status;
                    ?>
                    <div style="margin-bottom: 0.75rem;">
                        <span class="status-badge <?php echo e($statusClass); ?>"><?php echo e($statusLabel); ?></span>
                    </div>
                    <div style="color: #111827; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem;">
                        <?php echo e(number_format($request->completion_percentage, 0)); ?>% • <?php echo e($request->items_with_offers); ?>/<?php echo e($request->total_items); ?>

                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo e($request->completion_percentage); ?>%"></div>
                    </div>
                </td>
                <td style="color: #6b7280; font-size: 0.875rem;">
                    <div style="color: #111827; font-weight: 500;"><?php echo e($request->created_at ? $request->created_at->format('d.m.Y') : '—'); ?></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->collection_deadline): ?>
                        <div style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem;">
                            До: <?php echo e($request->collection_deadline->format('d.m.Y')); ?>

                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo e(route('admin.external-requests.show', $request)); ?>" class="btn-green" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none; display: inline-block;">
                        Открыть
                    </a>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
                <td colspan="5" style="text-align: center; padding: 3rem; color: #9ca3af;">
                    Заявок не найдено
                </td>
            </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table>

    <!-- Пагинация -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($requests->hasPages()): ?>
        <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
            <div style="color: #6b7280; font-size: 0.875rem;">
                Показано <?php echo e($requests->firstItem()); ?>–<?php echo e($requests->lastItem()); ?> из <?php echo e($requests->total()); ?>

            </div>
            <div style="display: flex; gap: 0.5rem;">
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($requests->onFirstPage()): ?>
                    <span style="background: #f3f4f6; border: 1px solid #e5e7eb; padding: 0.5rem 1rem; border-radius: 6px; color: #9ca3af; cursor: not-allowed;">
                        ← Назад
                    </span>
                <?php else: ?>
                    <a href="<?php echo e($requests->previousPageUrl()); ?>" style="background: #ffffff; border: 1px solid #e5e7eb; padding: 0.5rem 1rem; border-radius: 6px; color: #10b981; text-decoration: none; transition: all 0.2s;">
                        ← Назад
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $requests->getUrlRange(max(1, $requests->currentPage() - 2), min($requests->lastPage(), $requests->currentPage() + 2)); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($page == $requests->currentPage()): ?>
                        <span style="background: #10b981; border: 1px solid #10b981; padding: 0.5rem 0.875rem; border-radius: 6px; color: #fff; font-weight: 600; min-width: 40px; text-align: center;">
                            <?php echo e($page); ?>

                        </span>
                    <?php else: ?>
                        <a href="<?php echo e($url); ?>" style="background: #ffffff; border: 1px solid #e5e7eb; padding: 0.5rem 0.875rem; border-radius: 6px; color: #374151; text-decoration: none; transition: all 0.2s; min-width: 40px; text-align: center; display: inline-block;">
                            <?php echo e($page); ?>

                        </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($requests->hasMorePages()): ?>
                    <a href="<?php echo e($requests->nextPageUrl()); ?>" style="background: #ffffff; border: 1px solid #e5e7eb; padding: 0.5rem 1rem; border-radius: 6px; color: #10b981; text-decoration: none; transition: all 0.2s;">
                        Вперёд →
                    </a>
                <?php else: ?>
                    <span style="background: #f3f4f6; border: 1px solid #e5e7eb; padding: 0.5rem 1rem; border-radius: 6px; color: #9ca3af; cursor: not-allowed;">
                        Вперёд →
                    </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/admin/external-requests/index.blade.php ENDPATH**/ ?>