

<?php $__env->startSection('title', 'Мои заявки'); ?>
<?php $__env->startSection('header', 'Мои заявки'); ?>

<?php $__env->startSection('content'); ?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">Мои заявки</h1>
        <p style="color: #6b7280;">Управление запросами на подбор запчастей</p>
    </div>
    <a href="<?php echo e(route('cabinet.requests.create')); ?>" class="btn btn-primary">
        + Создать заявку
    </a>
</div>

<!-- Фильтры -->
<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <form method="GET" style="display: flex; gap: 1rem; align-items: end;">
        <div style="flex: 1;">
            <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Поиск</label>
            <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Код или название заявки"
                style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem;">
        </div>
        
        <div style="width: 200px;">
            <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">Статус</label>
            <select name="status" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem;">
                <option value="">Все</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Models\Request::statuses(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($key); ?>" <?php echo e(request('status') == $key ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
        </div>
        
        <button type="submit" class="btn" style="background: #f3f4f6; color: #374151;">
            Применить
        </button>
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request('search') || request('status')): ?>
            <a href="<?php echo e(route('cabinet.requests')); ?>" class="btn" style="background: #fee2e2; color: #991b1b;">
                Сбросить
            </a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </form>
</div>

<!-- Список заявок -->
<div style="background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($requests->count() > 0): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e5e7eb;">
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.875rem;">Код</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.875rem;">Название</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.875rem;">Статус</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.875rem;">Организация</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.875rem;">Позиций</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.875rem;">Создана</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.875rem;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <td style="padding: 1rem;">
                            <a href="<?php echo e(route('cabinet.requests.show', $request)); ?>" style="color: #10b981; text-decoration: none; font-weight: 600;">
                                <?php echo e($request->code); ?>

                            </a>
                        </td>
                        <td style="padding: 1rem;"><?php echo e($request->title ?? '—'); ?></td>
                        <td style="padding: 1rem;">
                            <?php
                                $statusColors = [
                                    'draft' => 'background: #f3f4f6; color: #374151;',
                                    'pending' => 'background: #fef3c7; color: #92400e;',
                                    'sending' => 'background: #dbeafe; color: #1e40af;',
                                    'collecting' => 'background: #e0e7ff; color: #3730a3;',
                                    'completed' => 'background: #d1fae5; color: #065f46;',
                                    'cancelled' => 'background: #fee2e2; color: #991b1b;',
                                ];
                            ?>
                            <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; <?php echo e($statusColors[$request->status] ?? ''); ?>">
                                <?php echo e(\App\Models\Request::statuses()[$request->status] ?? $request->status); ?>

                            </span>
                        </td>
                        <td style="padding: 1rem;"><?php echo e($request->company_name ?? '—'); ?></td>
                        <td style="padding: 1rem;"><?php echo e($request->items_count); ?></td>
                        <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo e($request->created_at->format('d.m.Y H:i')); ?></td>
                        <td style="padding: 1rem;">
                            <a href="<?php echo e(route('cabinet.requests.show', $request)); ?>" style="color: #10b981; text-decoration: none; font-weight: 500;">
                                Открыть →
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="padding: 1.5rem; border-top: 1px solid #e5e7eb;">
            <?php echo e($requests->links()); ?>

        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 4rem 2rem; color: #6b7280;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
            <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; color: #111827;">Заявок не найдено</h3>
            <p style="margin-bottom: 1.5rem;">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request('search') || request('status')): ?>
                    Попробуйте изменить параметры поиска
                <?php else: ?>
                    Создайте первую заявку для подбора запчастей
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </p>
            <a href="<?php echo e(route('cabinet.requests.create')); ?>" class="btn btn-primary">
                Создать заявку
            </a>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/cabinet/requests/index.blade.php ENDPATH**/ ?>