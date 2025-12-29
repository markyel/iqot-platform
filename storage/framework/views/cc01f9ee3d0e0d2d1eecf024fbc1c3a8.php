<?php $__env->startSection('title', 'Заявки пользователей'); ?>
<?php $__env->startSection('header', 'Заявки пользователей'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .filters { background: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
    .filters form { display: flex; gap: 1rem; flex-wrap: wrap; }
    .filters input, .filters select { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
    .filters button { padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; }
    .filters button:hover { background: #2563eb; }
    .table-container { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 0.875rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
    .table th { background: #f9fafb; font-weight: 600; color: #6b7280; font-size: 0.875rem; }
    .table tbody tr:hover { background: #f9fafb; }
    .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-draft { background: #f3f4f6; color: #6b7280; }
    .badge-sending { background: #dbeafe; color: #1e40af; }
    .badge-collecting { background: #e0e7ff; color: #3730a3; }
    .badge-completed { background: #d1fae5; color: #065f46; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .btn { padding: 0.5rem 0.875rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .stat-card { background: white; padding: 1.25rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .stat-label { font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem; }
    .stat-value { font-size: 1.875rem; font-weight: 700; color: #111827; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div style="max-width: 1400px; margin: 0 auto;">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem;">
        <p style="color: #065f46; font-weight: 600;"><?php echo e(session('success')); ?></p>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
    <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem;">
        <p style="color: #991b1b; font-weight: 600;"><?php echo e(session('error')); ?></p>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Статистика -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Всего заявок</div>
            <div class="stat-value"><?php echo e(\App\Models\Request::where('is_customer_request', 1)->count()); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Ожидают модерации</div>
            <div class="stat-value" style="color: #d97706;">
                <?php echo e(\App\Models\Request::where('is_customer_request', 1)->where('status', 'pending')->where('synced_to_main_db', false)->count()); ?>

            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">В работе</div>
            <div class="stat-value" style="color: #3b82f6;">
                <?php echo e(\App\Models\Request::where('is_customer_request', 1)->whereIn('status', ['sending', 'collecting'])->count()); ?>

            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Завершено</div>
            <div class="stat-value" style="color: #059669;">
                <?php echo e(\App\Models\Request::where('is_customer_request', 1)->where('status', 'completed')->count()); ?>

            </div>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="filters">
        <form method="GET" action="<?php echo e(route('admin.requests.index')); ?>">
            <input type="text" name="search" placeholder="Поиск по номеру, названию, пользователю..."
                   value="<?php echo e(request('search')); ?>" style="flex: 1; min-width: 300px;">

            <select name="status">
                <option value="">Все статусы</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Models\Request::statuses(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($value); ?>" <?php echo e(request('status') === $value ? 'selected' : ''); ?>>
                    <?php echo e($label); ?>

                </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>

            <select name="synced">
                <option value="">Синхронизация</option>
                <option value="yes" <?php echo e(request('synced') === 'yes' ? 'selected' : ''); ?>>Отправлено в работу</option>
                <option value="no" <?php echo e(request('synced') === 'no' ? 'selected' : ''); ?>>Не отправлено</option>
            </select>

            <button type="submit">Применить</button>
            <a href="<?php echo e(route('admin.requests.index')); ?>" class="btn btn-primary" style="background: #6b7280;">Сбросить</a>
        </form>
    </div>

    <!-- Таблица заявок -->
    <div class="table-container">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($requests->count() > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 120px;">Номер</th>
                    <th>Название</th>
                    <th style="width: 180px;">Пользователь</th>
                    <th style="width: 100px; text-align: center;">Позиций</th>
                    <th style="width: 100px;">Стоимость</th>
                    <th style="width: 120px;">Статус</th>
                    <th style="width: 100px; text-align: center;">Отправлено</th>
                    <th style="width: 140px;">Создана</th>
                    <th style="width: 100px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td style="font-family: monospace; font-weight: 600;">
                        <?php echo e($request->request_number ?? $request->code); ?>

                    </td>
                    <td>
                        <div style="font-weight: 500;"><?php echo e($request->title); ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 500;"><?php echo e($request->user->name); ?></div>
                        <div style="font-size: 0.75rem; color: #6b7280;"><?php echo e($request->user->email); ?></div>
                    </td>
                    <td style="text-align: center; font-weight: 600;">
                        <?php echo e($request->items_count); ?>

                    </td>
                    <td style="font-weight: 600;">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->balanceHold): ?>
                            <?php echo e(number_format($request->balanceHold->amount, 0)); ?> ₽
                        <?php else: ?>
                            —
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
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
                    <td style="text-align: center;">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->synced_to_main_db): ?>
                            <span class="badge badge-success">✓ Да</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Нет</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td style="color: #6b7280; font-size: 0.875rem;">
                        <?php echo e($request->created_at->format('d.m.Y H:i')); ?>

                    </td>
                    <td>
                        <a href="<?php echo e(route('admin.requests.show', $request->id)); ?>" class="btn btn-primary">
                            Открыть
                        </a>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>

        <div style="padding: 1rem;">
            <?php echo e($requests->links()); ?>

        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem; color: #6b7280;">
            <p style="font-size: 1.125rem; margin-bottom: 0.5rem;">Заявок не найдено</p>
            <p style="font-size: 0.875rem;">Попробуйте изменить параметры фильтрации</p>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/admin/requests/index.blade.php ENDPATH**/ ?>