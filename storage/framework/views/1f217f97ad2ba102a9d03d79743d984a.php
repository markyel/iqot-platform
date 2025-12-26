

<?php $__env->startSection('title', 'Заявка ' . $externalRequest->request_number); ?>

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

    .status-draft { background: #f3f4f6; color: #6b7280; }
    .status-new { background: #dbeafe; color: #1e40af; }
    .status-active { background: #d1fae5; color: #065f46; }
    .status-collecting { background: #fef3c7; color: #92400e; }
    .status-completed { background: #d1fae5; color: #065f46; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }
    .status-emails-sent { background: #e0e7ff; color: #3730a3; }
    .status-responses-received { background: #ddd6fe; color: #5b21b6; }
    .status-queued-for-sending { background: #fef3c7; color: #78350f; }

    .info-label {
        color: #6b7280;
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }

    .info-value {
        color: #111827;
        font-weight: 600;
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 0.5rem;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #10b981, #059669);
        transition: width 0.3s;
    }

    .item-card {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .item-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .item-name {
        color: #111827;
        font-weight: 600;
        font-size: 1.125rem;
        margin-bottom: 0.5rem;
    }

    .item-meta {
        color: #6b7280;
        font-size: 0.875rem;
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

    .no-offers {
        text-align: center;
        padding: 2rem;
        color: #9ca3af;
        font-style: italic;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
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
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div style="max-width: 1600px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="<?php echo e(route('admin.external-requests.index')); ?>" class="back-link">
            ← Назад к списку заявок
        </a>
        <div style="display: flex; justify-content: space-between; align-items: start; margin-top: 1rem;">
            <div>
                <h1 style="font-size: 2rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem;">
                    Заявка <?php echo e($externalRequest->request_number); ?>

                </h1>
                <p style="color: #6b7280;">
                    Создана <?php echo e($externalRequest->created_at ? $externalRequest->created_at->format('d.m.Y в H:i') : '—'); ?>

                </p>
            </div>
            <div>
                <?php
                    $statusClass = 'status-' . str_replace('_', '-', $externalRequest->status);
                    $statusLabel = \App\Models\ExternalRequest::getStatusLabels()[$externalRequest->status] ?? $externalRequest->status;
                ?>
                <span class="status-badge <?php echo e($statusClass); ?>"><?php echo e($statusLabel); ?></span>
            </div>
        </div>
    </div>

    <!-- Основная информация о заявке -->
    <div class="admin-card">
        <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">Информация о заявке</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->title): ?>
            <div>
                <div class="info-label">Название</div>
                <div class="info-value"><?php echo e($externalRequest->title); ?></div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->customer_company): ?>
            <div>
                <div class="info-label">Компания клиента</div>
                <div class="info-value"><?php echo e($externalRequest->customer_company); ?></div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->customer_contact_person): ?>
            <div>
                <div class="info-label">Контактное лицо</div>
                <div class="info-value"><?php echo e($externalRequest->customer_contact_person); ?></div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->customer_email): ?>
            <div>
                <div class="info-label">Email клиента</div>
                <div class="info-value">
                    <a href="mailto:<?php echo e($externalRequest->customer_email); ?>" style="color: #10b981; text-decoration: none;">
                        <?php echo e($externalRequest->customer_email); ?>

                    </a>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->customer_phone): ?>
            <div>
                <div class="info-label">Телефон клиента</div>
                <div class="info-value"><?php echo e($externalRequest->customer_phone); ?></div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->collection_deadline): ?>
            <div>
                <div class="info-label">Срок сбора</div>
                <div class="info-value"><?php echo e($externalRequest->collection_deadline->format('d.m.Y H:i')); ?></div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <!-- Прогресс выполнения -->
        <div>
            <div class="info-label">Прогресс выполнения</div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="flex: 1;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo e($externalRequest->completion_percentage); ?>%"></div>
                    </div>
                </div>
                <div style="color: #111827; font-weight: 700; font-size: 1.125rem;">
                    <?php echo e(number_format($externalRequest->completion_percentage, 0)); ?>%
                </div>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($externalRequest->notes): ?>
        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
            <div class="info-label">Заметки</div>
            <div style="color: #374151; white-space: pre-wrap; margin-top: 0.5rem;"><?php echo e($externalRequest->notes); ?></div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <!-- Статистика -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Всего позиций</div>
            <div class="stat-value"><?php echo e($externalRequest->total_items); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">С предложениями</div>
            <div class="stat-value stat-value-accent"><?php echo e($externalRequest->items_with_offers); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Процент закрытия</div>
            <div class="stat-value stat-value-accent">
                <?php echo e($externalRequest->total_items > 0 ? number_format(($externalRequest->items_with_offers / $externalRequest->total_items) * 100, 0) : 0); ?>%
            </div>
        </div>
    </div>

    <!-- Товарные позиции с предложениями -->
    <div class="admin-card">
        <h2 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">
            Товарные позиции (<?php echo e($externalRequest->items->count()); ?>)
        </h2>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $externalRequest->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="item-card">
            <div class="item-header">
                <div style="flex: 1;">
                    <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                        Позиция #<?php echo e($item->position_number); ?>

                    </div>
                    <div class="item-name"><?php echo e($item->name); ?></div>
                    <div class="item-meta">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->brand): ?>
                            <span>Бренд: <strong style="color: #374151;"><?php echo e($item->brand); ?></strong></span> •
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->article): ?>
                            <span>Артикул: <strong style="color: #374151;"><?php echo e($item->article); ?></strong></span> •
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span>Количество: <strong style="color: #374151;"><?php echo e($item->quantity); ?> <?php echo e($item->unit); ?></strong></span>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->description): ?>
                    <div style="color: #6b7280; font-size: 0.875rem; margin-top: 0.5rem;">
                        <?php echo e($item->description); ?>

                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <?php
                        $itemStatusClass = 'status-badge status-' . str_replace('_', '-', $item->status);
                        $itemStatusLabel = \App\Models\ExternalRequestItem::getStatusLabels()[$item->status] ?? $item->status;
                    ?>
                    <span class="<?php echo e($itemStatusClass); ?>" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">
                        <?php echo e($itemStatusLabel); ?>

                    </span>
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->offers->count() > 0): ?>
            <!-- Статистика по позиции -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 6px;">
                <div>
                    <div style="color: #6b7280; font-size: 0.75rem;">Предложений</div>
                    <div style="color: #059669; font-weight: 700; font-size: 1.125rem;"><?php echo e($item->offers->count()); ?></div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->min_price): ?>
                <div>
                    <div style="color: #6b7280; font-size: 0.75rem;">Мин. цена</div>
                    <div style="color: #111827; font-weight: 700; font-size: 1.125rem;"><?php echo e(number_format($item->min_price, 2)); ?> ₽</div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->max_price): ?>
                <div>
                    <div style="color: #6b7280; font-size: 0.75rem;">Макс. цена</div>
                    <div style="color: #111827; font-weight: 700; font-size: 1.125rem;"><?php echo e(number_format($item->max_price, 2)); ?> ₽</div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <!-- Таблица предложений -->
            <table class="offers-table">
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
                        <td>
                            <div class="supplier-name"><?php echo e($offer->supplier->name ?? 'Не указан'); ?></div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->supplier && $offer->supplier->email): ?>
                            <div style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem;">
                                <?php echo e($offer->supplier->email); ?>

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
                            <span style="color: #fff; font-weight: 600;"><?php echo e(number_format($offer->total_price_in_rub, 2)); ?> ₽</span>
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
                        <td>
                            <?php
                                $offerStatusClass = 'status-badge status-' . $offer->status;
                                $offerStatusLabel = \App\Models\ExternalOffer::getStatusLabels()[$offer->status] ?? $offer->status;
                            ?>
                            <span class="<?php echo e($offerStatusClass); ?>" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                <?php echo e($offerStatusLabel); ?>

                            </span>
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
                Нет предложений по данной позиции
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="no-offers">
            Товарные позиции отсутствуют
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/admin/external-requests/show.blade.php ENDPATH**/ ?>