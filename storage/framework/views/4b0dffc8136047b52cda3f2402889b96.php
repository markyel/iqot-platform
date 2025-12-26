

<?php $__env->startSection('title', 'Позиция #' . $item->position_number); ?>

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

    .back-link {
        color: #10b981;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        margin-bottom: 1rem;
    }

    .back-link:hover {
        color: #059669;
    }

    .status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .status-pending { background: #f3f4f6; color: #6b7280; }
    .status-has-offers { background: #d1fae5; color: #065f46; }
    .status-partial-offers { background: #fef3c7; color: #92400e; }
    .status-no-offers { background: #fee2e2; color: #991b1b; }
    .status-clarification-needed { background: #dbeafe; color: #1e40af; }

    .info-label {
        color: #6b7280;
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }

    .info-value {
        color: #111827;
        font-weight: 600;
    }

    .offers-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
        background: #ffffff;
        border-radius: 8px;
        overflow: hidden;
    }

    .offers-table th {
        text-align: left;
        padding: 0.75rem;
        background: #f9fafb;
        color: #6b7280;
        font-size: 0.875rem;
        font-weight: 600;
        border-bottom: 2px solid #e5e7eb;
    }

    .offers-table td {
        padding: 0.75rem;
        border-top: 1px solid #f3f4f6;
        color: #374151;
        font-size: 0.875rem;
    }

    .offers-table tbody tr:hover {
        background: #f9fafb;
    }

    .price-highlight {
        color: #059669;
        font-weight: 700;
        font-size: 1rem;
    }

    .price-best {
        background: #d1fae5;
        border: 1px solid #a7f3d0;
        border-radius: 4px;
        padding: 0.5rem 0.75rem;
    }

    .supplier-name {
        color: #111827;
        font-weight: 600;
    }

    .stat-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1rem;
    }

    .stat-label {
        color: #6b7280;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }

    .stat-value {
        color: #111827;
        font-size: 1.5rem;
        font-weight: 700;
    }

    .stat-value-accent {
        color: #059669;
    }

    .no-offers {
        text-align: center;
        padding: 3rem;
        color: #9ca3af;
        font-style: italic;
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div style="max-width: 1400px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="<?php echo e(route('admin.items.index')); ?>" class="back-link">
            ← Назад к списку позиций
        </a>
        <div style="display: flex; justify-content: space-between; align-items: start; margin-top: 1rem;">
            <div>
                <h1 style="font-size: 2rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem;">
                    Позиция #<?php echo e($item->position_number); ?>

                </h1>
                <p style="color: #6b7280;">
                    Из заявки 
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->request): ?>
                        <a href="<?php echo e(route('admin.external-requests.show', $item->request)); ?>" style="color: #10b981; text-decoration: none; font-weight: 600;">
                            <?php echo e($item->request->request_number); ?>

                        </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
            </div>
            <div>
                <?php
                    $statusClass = 'status-' . str_replace('_', '-', $item->status);
                    $statusLabel = \App\Models\ExternalRequestItem::getStatusLabels()[$item->status] ?? $item->status;
                ?>
                <span class="status-badge <?php echo e($statusClass); ?>"><?php echo e($statusLabel); ?></span>
            </div>
        </div>
    </div>

    <!-- Информация о позиции -->
    <div class="admin-card">
        <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">Информация о позиции</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
            <div>
                <div class="info-label">Название</div>
                <div class="info-value"><?php echo e($item->name); ?></div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->brand): ?>
            <div>
                <div class="info-label">Бренд</div>
                <div class="info-value"><?php echo e($item->brand); ?></div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->article): ?>
            <div>
                <div class="info-label">Артикул</div>
                <div class="info-value"><?php echo e($item->article); ?></div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div>
                <div class="info-label">Количество</div>
                <div class="info-value"><?php echo e($item->quantity); ?> <?php echo e($item->unit); ?></div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->category): ?>
            <div>
                <div class="info-label">Категория</div>
                <div class="info-value"><?php echo e($item->category); ?></div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->description): ?>
        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
            <div class="info-label">Описание</div>
            <div style="color: #374151; margin-top: 0.5rem;"><?php echo e($item->description); ?></div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <!-- Статистика -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->offers->count() > 0): ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="stat-card">
            <div class="stat-label">Всего предложений</div>
            <div class="stat-value stat-value-accent"><?php echo e($item->offers->count()); ?></div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->min_price): ?>
        <div class="stat-card">
            <div class="stat-label">Минимальная цена</div>
            <div class="stat-value"><?php echo e(number_format($item->min_price, 2)); ?> ₽</div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->max_price): ?>
        <div class="stat-card">
            <div class="stat-label">Максимальная цена</div>
            <div class="stat-value"><?php echo e(number_format($item->max_price, 2)); ?> ₽</div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->avg_price): ?>
        <div class="stat-card">
            <div class="stat-label">Средняя цена</div>
            <div class="stat-value"><?php echo e(number_format($item->avg_price, 2)); ?> ₽</div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Предложения от поставщиков -->
    <div class="admin-card">
        <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">
            Предложения от поставщиков (<?php echo e($item->offers->count()); ?>)
        </h2>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->offers->count() > 0): ?>
        <table class="offers-table">
            <thead>
                <tr>
                    <th>Поставщик</th>
                    <th>Цена за ед.</th>
                    <th>Общая цена</th>
                    <th>Срок поставки</th>
                    <th>Условия оплаты</th>
                    <th>Дата ответа</th>
                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $item->offers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $offer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td>
                        <div class="supplier-name"><?php echo e($offer->supplier->name ?? 'Не указан'); ?></div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->supplier && $offer->supplier->email): ?>
                        <div style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem;">
                            <?php echo e($offer->supplier->email); ?>

                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->supplier && $offer->supplier->phone): ?>
                        <div style="color: #6b7280; font-size: 0.75rem;">
                            <?php echo e($offer->supplier->phone); ?>

                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->price_per_unit): ?>
                        <div class="<?php echo e($index === 0 ? 'price-best' : ''); ?>">
                            <span class="price-highlight"><?php echo e(number_format($offer->price_per_unit_in_rub, 2)); ?> ₽</span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->currency !== 'RUB'): ?>
                                <div style="color: #6b7280; font-size: 0.75rem;"><?php echo e(number_format($offer->price_per_unit, 2)); ?> <?php echo e($offer->currency); ?></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->price_includes_vat): ?>
                            <div style="color: #6b7280; font-size: 0.75rem;">с НДС</div>
                            <?php else: ?>
                            <div style="color: #6b7280; font-size: 0.75rem;">без НДС</div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php else: ?>
                        <span style="color: #6b7280;">—</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->total_price): ?>
                        <span style="color: #111827; font-weight: 600;"><?php echo e(number_format($offer->total_price_in_rub, 2)); ?> ₽</span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->currency !== 'RUB'): ?>
                            <div style="color: #6b7280; font-size: 0.75rem;"><?php echo e(number_format($offer->total_price, 2)); ?> <?php echo e($offer->currency); ?></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php else: ?>
                        <span style="color: #6b7280;">—</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->delivery_days): ?>
                        <span><?php echo e($offer->delivery_days); ?> дн.</span>
                        <?php else: ?>
                        <span style="color: #6b7280;">—</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->payment_terms): ?>
                        <span><?php echo e($offer->payment_terms); ?></span>
                        <?php else: ?>
                        <span style="color: #6b7280;">—</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td style="color: #6b7280; font-size: 0.875rem;">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->response_received_at): ?>
                            <?php echo e($offer->response_received_at->format('d.m.Y H:i')); ?>

                        <?php else: ?>
                            —
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->notes): ?>
                <tr>
                    <td colspan="6" style="background: #f9fafb; padding: 0.75rem;">
                        <div style="color: #6b7280; font-size: 0.75rem; margin-bottom: 0.25rem;">Примечание:</div>
                        <div style="color: #111827; font-size: 0.875rem;"><?php echo e($offer->notes); ?></div>
                    </td>
                </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-offers">
            По данной позиции нет предложений от поставщиков
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/admin/external-requests/item-show.blade.php ENDPATH**/ ?>