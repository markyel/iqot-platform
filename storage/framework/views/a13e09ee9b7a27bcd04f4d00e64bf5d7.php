<?php $__env->startSection('title', 'Заявка ' . ($request->request_number ?? $request->code)); ?>
<?php $__env->startSection('header', 'Заявка ' . ($request->request_number ?? $request->code)); ?>

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
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .btn { padding: 0.625rem 1.25rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-secondary { background: #6b7280; color: white; }
    .btn-secondary:hover { background: #4b5563; }
    .btn-success { background: #10b981; color: white; }
    .btn-success:hover { background: #059669; }
    .btn-danger { background: #ef4444; color: white; }
    .btn-danger:hover { background: #dc2626; }
    .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
    .info-item { padding: 0.75rem; background: #f9fafb; border-radius: 0.5rem; }
    .info-label { font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem; }
    .info-value { font-size: 0.875rem; color: #111827; font-weight: 500; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
    .table th { background: #f9fafb; font-weight: 600; color: #6b7280; font-size: 0.875rem; }
    .table tbody tr:hover { background: #f9fafb; }
    .actions { display: flex; gap: 1rem; margin-top: 1.5rem; }
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
    .modal-content { background: white; max-width: 500px; margin: 10% auto; padding: 2rem; border-radius: 0.75rem; }
    .modal-header { font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem; }
    .modal-body { margin-bottom: 1.5rem; }
    .modal-footer { display: flex; gap: 1rem; justify-content: flex-end; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div style="max-width: 1200px; margin: 0 auto;">

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

    <!-- Кнопка назад -->
    <div style="margin-bottom: 1.5rem;">
        <a href="<?php echo e(route('admin.requests.index')); ?>" class="btn btn-secondary">← Назад к списку</a>
    </div>

    <!-- Заголовок заявки -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1.5rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">
                    <?php echo e($request->title); ?>

                </h1>
                <p style="color: #6b7280; font-size: 0.875rem;">
                    Создана <?php echo e($request->created_at->format('d.m.Y в H:i')); ?>

                </p>
            </div>
            <div style="display: flex; gap: 0.75rem; align-items: center;">
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
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->synced_to_main_db): ?>
                    <span class="badge badge-success">✓ Отправлено в работу</span>
                <?php else: ?>
                    <span class="badge badge-warning">Не отправлено</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- Основная информация -->
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Номер заявки</div>
                <div class="info-value"><?php echo e($request->request_number ?? $request->code); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Позиций в заявке</div>
                <div class="info-value"><?php echo e($request->items_count); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Стоимость</div>
                <div class="info-value">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->balanceHold): ?>
                        <?php echo e(number_format($request->balanceHold->amount, 2)); ?> ₽
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->balanceHold->status === 'held'): ?>
                            <span style="color: #d97706; font-size: 0.75rem;">(заморожено)</span>
                        <?php elseif($request->balanceHold->status === 'charged'): ?>
                            <span style="color: #059669; font-size: 0.75rem;">(списано)</span>
                        <?php elseif($request->balanceHold->status === 'released'): ?>
                            <span style="color: #6b7280; font-size: 0.75rem;">(возвращено)</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->synced_to_main_db): ?>
        <div style="margin-top: 1rem; padding: 0.75rem; background: #dbeafe; border-left: 3px solid #3b82f6; border-radius: 0.5rem;">
            <div style="font-size: 0.75rem; color: #1e40af; font-weight: 600; margin-bottom: 0.25rem;">СИНХРОНИЗАЦИЯ</div>
            <div style="font-size: 0.875rem; color: #1e3a8a;">
                ID в основной БД: <strong><?php echo e($request->main_db_request_id); ?></strong>
                <br>
                Дата синхронизации: <?php echo e($request->synced_at->format('d.m.Y в H:i')); ?>

            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->notes): ?>
        <div style="margin-top: 1rem; padding: 0.75rem; background: #f0f9ff; border-left: 3px solid #3b82f6; border-radius: 0.5rem;">
            <div style="font-size: 0.75rem; color: #1e40af; font-weight: 600; margin-bottom: 0.25rem;">ПРИМЕЧАНИЕ</div>
            <div style="font-size: 0.875rem; color: #1e3a8a; white-space: pre-line;"><?php echo e($request->notes); ?></div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <!-- Информация о пользователе -->
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Информация о заказчике</h2>

        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Пользователь</div>
                <div class="info-value">
                    <a href="<?php echo e(route('admin.users.show', $request->user->id)); ?>" style="color: #3b82f6; text-decoration: none;">
                        <?php echo e($request->user->name); ?>

                    </a>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Email</div>
                <div class="info-value"><?php echo e($request->user->email); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Телефон</div>
                <div class="info-value"><?php echo e($request->user->phone ?? $request->user->company_phone ?? '—'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Компания</div>
                <div class="info-value"><?php echo e($request->user->company ?? $request->user->organization ?? '—'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Контактное лицо</div>
                <div class="info-value"><?php echo e($request->user->contact_person ?? $request->user->full_name ?? '—'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Sender Email</div>
                <div class="info-value">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($senderEmail ?? null): ?>
                        <a href="mailto:<?php echo e($senderEmail); ?>" style="color: #3b82f6; text-decoration: none;">
                            <?php echo e($senderEmail); ?>

                        </a>
                    <?php else: ?>
                        —
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Позиции заявки -->
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Позиции заявки</h2>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->items->count() > 0): ?>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 60px;">№</th>
                        <th>Название</th>
                        <th style="width: 150px;">Бренд</th>
                        <th style="width: 150px;">Артикул</th>
                        <th style="width: 100px; text-align: center;">Количество</th>
                        <th style="width: 80px;">Ед. изм.</th>
                        <th style="width: 120px;">Категория</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $request->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td style="color: #6b7280; font-weight: 600;"><?php echo e($item->position_number); ?></td>
                        <td>
                            <div style="font-weight: 500;"><?php echo e($item->name); ?></div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->description): ?>
                            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;"><?php echo e($item->description); ?></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td><?php echo e($item->brand ?? '—'); ?></td>
                        <td style="font-family: monospace; font-size: 0.875rem;"><?php echo e($item->article ?? '—'); ?></td>
                        <td style="text-align: center; font-weight: 600;"><?php echo e($item->quantity); ?></td>
                        <td style="color: #6b7280;"><?php echo e($item->unit ?? 'шт.'); ?></td>
                        <td>
                            <span style="padding: 0.25rem 0.5rem; background: #f3f4f6; border-radius: 0.375rem; font-size: 0.75rem;">
                                <?php echo e($item->category ?? 'Другое'); ?>

                            </span>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="text-align: center; color: #6b7280; padding: 2rem;">Позиции не найдены</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <!-- Действия -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$request->synced_to_main_db && $request->status === \App\Models\Request::STATUS_PENDING): ?>
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Модерация</h2>
        <p style="color: #6b7280; margin-bottom: 1rem;">
            Эта заявка ожидает модерации. Вы можете отправить её в работу или отклонить.
        </p>
        <div class="actions">
            <form method="POST" action="<?php echo e(route('admin.requests.approve', $request->id)); ?>" style="display: inline;">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-success">
                    ✓ Отправить в работу
                </button>
            </form>
            <button type="button" class="btn btn-danger" onclick="showRejectModal()">
                ✗ Отклонить заявку
            </button>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>

<!-- Модальное окно отклонения -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Отклонение заявки</div>
        <form method="POST" action="<?php echo e(route('admin.requests.reject', $request->id)); ?>">
            <?php echo csrf_field(); ?>
            <div class="modal-body">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Причина отклонения:</label>
                <textarea name="reason" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; resize: vertical;" rows="4"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideRejectModal()">Отмена</button>
                <button type="submit" class="btn btn-danger">Отклонить</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal() {
    document.getElementById('rejectModal').style.display = 'block';
}

function hideRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

// Закрытие по клику вне модального окна
window.onclick = function(event) {
    const modal = document.getElementById('rejectModal');
    if (event.target === modal) {
        hideRejectModal();
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/admin/requests/show.blade.php ENDPATH**/ ?>