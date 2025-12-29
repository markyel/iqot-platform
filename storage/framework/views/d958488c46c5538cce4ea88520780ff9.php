

<?php $__env->startSection('title', 'Главная'); ?>
<?php $__env->startSection('header', 'Главная'); ?>

<?php $__env->startSection('content'); ?>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Всего заявок</div>
        <div style="font-size: 2rem; font-weight: 700; color: #111827;"><?php echo e($stats['total_requests']); ?></div>
    </div>
    
    <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Активные</div>
        <div style="font-size: 2rem; font-weight: 700; color: #f59e0b;"><?php echo e($stats['active_requests']); ?></div>
    </div>
    
    <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Завершённые</div>
        <div style="font-size: 2rem; font-weight: 700; color: #10b981;"><?php echo e($stats['completed_requests']); ?></div>
    </div>
    
    <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Отчёты</div>
        <div style="font-size: 2rem; font-weight: 700; color: #111827;"><?php echo e($stats['total_reports']); ?></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
    <!-- Последние заявки -->
    <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem; font-weight: 600;">Последние заявки</h2>
            <a href="<?php echo e(route('cabinet.my.requests.create')); ?>" class="btn btn-primary">
                Создать заявку
            </a>
        </div>
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recentRequests->count() > 0): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.875rem;">Код</th>
                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.875rem;">Название</th>
                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.875rem;">Статус</th>
                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.875rem;">Позиций</th>
                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.875rem;">Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recentRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr style="border-bottom: 1px solid #f3f4f6;">
                            <td style="padding: 1rem 0.75rem;">
                                <a href="<?php echo e(route('cabinet.requests.show', $request)); ?>" style="color: #10b981; text-decoration: none; font-weight: 500;">
                                    <?php echo e($request->code); ?>

                                </a>
                            </td>
                            <td style="padding: 1rem 0.75rem;"><?php echo e($request->title ?? '—'); ?></td>
                            <td style="padding: 1rem 0.75rem;">
                                <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #f3f4f6; color: #374151;">
                                    <?php echo e(\App\Models\Request::statuses()[$request->status] ?? $request->status); ?>

                                </span>
                            </td>
                            <td style="padding: 1rem 0.75rem;"><?php echo e($request->items_count); ?></td>
                            <td style="padding: 1rem 0.75rem; color: #6b7280;"><?php echo e($request->created_at->format('d.m.Y H:i')); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: #6b7280;">
                <p style="margin-bottom: 1rem;">У вас пока нет заявок</p>
                <a href="<?php echo e(route('cabinet.my.requests.create')); ?>" class="btn btn-primary">
                    Создать первую заявку
                </a>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/cabinet/dashboard.blade.php ENDPATH**/ ?>