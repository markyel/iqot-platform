<?php $__env->startSection('title', 'Мои заявки'); ?>
<?php $__env->startSection('header', 'Мои заявки'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; padding: 1.5rem; }
    .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-draft { background: #f3f4f6; color: #6b7280; }
    .badge-sending { background: #dbeafe; color: #1e40af; }
    .badge-collecting { background: #e0e7ff; color: #3730a3; }
    .badge-completed { background: #d1fae5; color: #065f46; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }
    .btn { padding: 0.625rem 1.25rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
    .table th { background: #f9fafb; font-weight: 600; color: #6b7280; font-size: 0.875rem; }
    .table tbody tr:hover { background: #f9fafb; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div style="max-width: 1200px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">Мои заявки</h1>
            <p style="color: #6b7280;">Список ваших заявок на мониторинг позиций</p>
        </div>
        <a href="<?php echo e(route('cabinet.my.requests.create')); ?>" class="btn btn-primary">+ Создать заявку</a>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($requests->count() > 0): ?>
    <div class="card" style="padding: 0; overflow: hidden;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 150px;">Номер</th>
                    <th>Название</th>
                    <th style="width: 120px;">Статус</th>
                    <th style="width: 80px; text-align: center;">Позиций</th>
                    <th style="width: 100px; text-align: right;">Стоимость</th>
                    <th style="width: 150px;">Дата создания</th>
                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td>
                        <a href="<?php echo e(route('cabinet.my.requests.show', $request->id)); ?>" style="color: #3b82f6; text-decoration: none; font-weight: 600;">
                            <?php echo e($request->request_number ?? $request->code); ?>

                        </a>
                    </td>
                    <td><?php echo e($request->title); ?></td>
                    <td>
                        <?php
                            $statusClass = match($request->status) {
                                'draft' => 'badge-draft',
                                'pending' => 'badge-pending',
                                'sending' => 'badge-sending',
                                'collecting' => 'badge-collecting',
                                'completed' => 'badge-completed',
                                'cancelled' => 'badge-cancelled',
                                default => 'badge-draft'
                            };
                            $statusText = \App\Models\Request::statuses()[$request->status] ?? $request->status;
                        ?>
                        <span class="badge <?php echo e($statusClass); ?>"><?php echo e($statusText); ?></span>
                    </td>
                    <td style="text-align: center;"><?php echo e($request->items_count); ?></td>
                    <td style="text-align: right; font-weight: 600; color: #6b7280;">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->balanceHold): ?>
                            <?php echo e(number_format($request->balanceHold->amount, 2)); ?> ₽
                        <?php else: ?>
                            —
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td style="color: #6b7280; font-size: 0.875rem;">
                        <?php echo e($request->created_at->format('d.m.Y H:i')); ?>

                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($requests->hasPages()): ?>
    <div style="margin-top: 1.5rem;">
        <?php echo e($requests->links()); ?>

    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php else: ?>
    <div class="card" style="text-align: center; padding: 3rem;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">У вас пока нет заявок</h2>
        <p style="color: #6b7280; margin-bottom: 1.5rem;">Создайте первую заявку для мониторинга позиций</p>
        <a href="<?php echo e(route('cabinet.my.requests.create')); ?>" class="btn btn-primary">Создать заявку</a>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/requests/index.blade.php ENDPATH**/ ?>