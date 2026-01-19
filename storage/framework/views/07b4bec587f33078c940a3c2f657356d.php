<?php $__env->startSection('title', 'Вопросы от поставщиков'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .answer-form {
        margin-top: var(--space-4);
        padding: var(--space-4);
        background: var(--neutral-0);
        border-radius: var(--radius-md);
        border: 1px solid var(--neutral-200);
    }

    .answer-form.answered {
        background: var(--success-50);
        border-color: var(--success-500);
    }

    .answer-textarea {
        width: 100%;
        padding: var(--space-3);
        border: 1px solid var(--neutral-300);
        border-radius: var(--radius-md);
        min-height: 100px;
        font-size: var(--text-sm);
        resize: vertical;
        font-family: var(--font-primary);
    }

    .answer-textarea:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px var(--primary-100);
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>

<!-- Page Header -->
<?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Вопросы от поставщиков','description' => 'Ответы на вопросы поставщиков по заявкам']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Вопросы от поставщиков','description' => 'Ответы на вопросы поставщиков по заявкам']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>

<!-- Filters Card -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <form method="GET" action="<?php echo e(route('admin.questions.index')); ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); align-items: end;">
            <div class="form-group">
                <label class="form-label">Статус</label>
                <select name="status" class="input select">
                    <option value="">Неотвеченные</option>
                    <option value="all" <?php echo e(request('status') === 'all' ? 'selected' : ''); ?>>Все</option>
                    <option value="pending" <?php echo e(request('status') === 'pending' ? 'selected' : ''); ?>>Ожидает ответа</option>
                    <option value="answered" <?php echo e(request('status') === 'answered' ? 'selected' : ''); ?>>Отвечены</option>
                    <option value="skipped" <?php echo e(request('status') === 'skipped' ? 'selected' : ''); ?>>Пропущены</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Приоритет</label>
                <select name="priority" class="input select">
                    <option value="">Все</option>
                    <option value="high" <?php echo e(request('priority') === 'high' ? 'selected' : ''); ?>>Высокий</option>
                    <option value="medium" <?php echo e(request('priority') === 'medium' ? 'selected' : ''); ?>>Средний</option>
                    <option value="low" <?php echo e(request('priority') === 'low' ? 'selected' : ''); ?>>Низкий</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Номер заявки</label>
                <input type="text" name="request_number" class="input" placeholder="REQ-..." value="<?php echo e(request('request_number')); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Поиск</label>
                <input type="text" name="search" class="input" placeholder="Текст вопроса..." value="<?php echo e(request('search')); ?>">
            </div>

            <div style="display: flex; gap: var(--space-2);">
                <button type="submit" class="btn btn-primary btn-md">
                    <i data-lucide="filter" class="icon-sm"></i>
                    Применить
                </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request()->hasAny(['status', 'priority', 'request_number', 'search'])): ?>
                    <a href="<?php echo e(route('admin.questions.index')); ?>" class="btn btn-secondary btn-md">
                        <i data-lucide="x" class="icon-sm"></i>
                        Сбросить
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Список вопросов -->
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($questions)): ?>
    <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'help-circle','title' => 'Нет вопросов','description' => 'По заданным фильтрам вопросов не найдено']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'help-circle','title' => 'Нет вопросов','description' => 'По заданным фильтрам вопросов не найдено']); ?>
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
<?php else: ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $questions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $question): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
            $status = 'pending';
            if ($question['status'] === 'answered' || $question['status'] === 'author_answered') {
                $status = 'answered';
            } elseif ($question['status'] === 'skipped') {
                $status = 'skipped';
            }

            // Формируем массив поставщиков
            $suppliers = [];
            if (!empty($question['supplier_name'])) {
                $suppliers[] = $question['supplier_name'];
            }

            // Берем номер заявки из request или request_number
            $requestNumber = $question['request']['number'] ?? $question['request_number'] ?? null;

            // Берем название позиции
            $itemName = $question['request_item']['name'] ?? $question['item_name'] ?? null;
        ?>

        <div class="question-card" style="margin-bottom: var(--space-4);">
            <div class="question-header">
                <div class="question-meta">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($requestNumber): ?>
                        <span class="text-code"><?php echo e($requestNumber); ?></span>
                    <?php else: ?>
                        <span class="text-code">Вопрос #<?php echo e($question['id']); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($itemName): ?>
                        <span class="question-separator">•</span>
                        <span class="question-item"><?php echo e($itemName); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['type' => $status,'dot' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($status),'dot' => true]); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status === 'answered'): ?> Отвечено
                    <?php elseif($status === 'skipped'): ?> Пропущено
                    <?php else: ?> Требует ответа
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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

            <div class="question-body">
                <p class="question-text"><?php echo e($question['question_text']); ?></p>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($suppliers) > 0): ?>
                    <div class="question-suppliers">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $supplier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <span class="supplier-tag"><?php echo e($supplier); ?></span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($question['author_answer']) || !empty($question['answer_text'])): ?>
                <div class="answer-form answered">
                    <div style="font-size: var(--text-sm); color: var(--success-700); margin-bottom: var(--space-2); font-weight: 600;">
                        <i data-lucide="check-circle" class="icon-sm" style="display: inline-block; vertical-align: middle;"></i>
                        Ваш ответ:
                    </div>
                    <div style="color: var(--success-700);"><?php echo e($question['author_answer'] ?? $question['answer_text']); ?></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($question['answered_at'])): ?>
                        <div style="font-size: var(--text-xs); color: var(--success-600); margin-top: var(--space-2);">
                            Отвечено: <?php echo e(\Carbon\Carbon::parse($question['answered_at'])->format('d.m.Y H:i')); ?>

                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="question-footer">
                    <span class="question-time"><?php echo e(\Carbon\Carbon::parse($question['created_at'])->format('d.m.Y H:i')); ?></span>
                    <div class="question-actions">
                        <form method="POST" action="<?php echo e(route('admin.questions.skip', $question['id'])); ?>" style="display: inline;">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('Пропустить этот вопрос?')">
                                Пропустить
                            </button>
                        </form>
                        <button type="button" class="btn btn-primary btn-sm" onclick="toggleAnswerForm(<?php echo e($question['id']); ?>)">
                            <i data-lucide="message-circle" class="icon-sm"></i>
                            Ответить
                        </button>
                    </div>
                </div>

                <div id="answer-form-<?php echo e($question['id']); ?>" class="answer-form" style="display: none;">
                    <form method="POST" action="<?php echo e(route('admin.questions.answer', $question['id'])); ?>" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="form-group">
                            <label class="form-label">Ваш ответ:</label>
                            <textarea name="answer" class="answer-textarea" required placeholder="Введите ответ на вопрос..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Прикрепить файлы (необязательно):</label>
                            <input type="file" name="files[]" multiple class="input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                            <small style="color: var(--neutral-500); font-size: var(--text-xs); margin-top: var(--space-1); display: block;">
                                Максимальный размер файла: 10 МБ. Можно прикрепить несколько файлов.
                            </small>
                        </div>
                        <div style="display: flex; gap: var(--space-2);">
                            <button type="submit" class="btn btn-primary btn-md">Отправить ответ</button>
                            <button type="button" class="btn btn-secondary btn-md" onclick="toggleAnswerForm(<?php echo e($question['id']); ?>)">Отмена</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Пагинация -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginationData['total_pages'] > 1): ?>
    <div class="pagination" style="margin-top: var(--space-6);">
        <?php
            $queryParams = request()->except('page');
            $startPage = max(1, $paginationData['page'] - 2);
            $endPage = min($paginationData['total_pages'], $paginationData['page'] + 2);
        ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginationData['page'] > 1): ?>
            <a href="?<?php echo e(http_build_query(array_merge($queryParams, ['page' => $paginationData['page'] - 1]))); ?>" class="page-link">
                <i data-lucide="chevron-left" class="icon-sm"></i>
                Назад
            </a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($startPage > 1): ?>
            <a href="?<?php echo e(http_build_query(array_merge($queryParams, ['page' => 1]))); ?>" class="page-link">1</a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($startPage > 2): ?>
                <span class="page-ellipsis">...</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="?<?php echo e(http_build_query(array_merge($queryParams, ['page' => $i]))); ?>" class="page-link <?php echo e($i === $paginationData['page'] ? 'active' : ''); ?>"><?php echo e($i); ?></a>
        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($endPage < $paginationData['total_pages']): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($endPage < $paginationData['total_pages'] - 1): ?>
                <span class="page-ellipsis">...</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <a href="?<?php echo e(http_build_query(array_merge($queryParams, ['page' => $paginationData['total_pages']]))); ?>" class="page-link"><?php echo e($paginationData['total_pages']); ?></a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginationData['page'] < $paginationData['total_pages']): ?>
            <a href="?<?php echo e(http_build_query(array_merge($queryParams, ['page' => $paginationData['page'] + 1]))); ?>" class="page-link">
                Вперёд
                <i data-lucide="chevron-right" class="icon-sm"></i>
            </a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php $__env->startPush('scripts'); ?>
<script>
function toggleAnswerForm(questionId) {
    const form = document.getElementById('answer-form-' + questionId);
    if (form.style.display === 'none') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}

// Reinitialize Lucide icons after content is loaded
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/admin/questions/index.blade.php ENDPATH**/ ?>