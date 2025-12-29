<?php $__env->startSection('title', 'Отправитель пользователя'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .admin-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .info-table {
        width: 100%;
        border-collapse: collapse;
    }

    .info-table th {
        text-align: left;
        padding: 0.75rem;
        background: #f9fafb;
        color: #6b7280;
        font-weight: 600;
        font-size: 0.875rem;
        width: 30%;
        border-bottom: 1px solid #e5e7eb;
    }

    .info-table td {
        padding: 0.75rem;
        color: #111827;
        border-bottom: 1px solid #f3f4f6;
    }

    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 600;
        display: inline-block;
    }

    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-secondary {
        background: #f3f4f6;
        color: #6b7280;
    }

    .badge-info {
        background: #dbeafe;
        color: #1e40af;
    }

    .btn {
        padding: 0.625rem 1.5rem;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-block;
    }

    .btn-primary {
        background: #10b981;
        color: white;
    }

    .btn-primary:hover {
        background: #059669;
    }

    .btn-danger {
        background: #ef4444;
        color: white;
    }

    .btn-danger:hover {
        background: #dc2626;
    }

    .btn-secondary {
        background: #6b7280;
        color: white;
    }

    .btn-secondary:hover {
        background: #4b5563;
    }

    .btn-success {
        background: #10b981;
        color: white;
        padding: 1rem 2rem;
        font-size: 1rem;
    }

    .btn-success:hover {
        background: #059669;
    }

    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .alert-info {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }

    code {
        background: rgba(0, 0, 0, 0.05);
        padding: 0.125rem 0.375rem;
        border-radius: 4px;
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div style="max-width: 1400px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1 style="font-size: 1.875rem; font-weight: 700; color: #111827;">
            📧 Отправитель пользователя: <?php echo e($user->name); ?>

        </h1>
        <a href="<?php echo e(route('admin.users.index')); ?>" class="btn btn-secondary">
            ← Назад к пользователям
        </a>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="alert alert-success"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
        <div class="alert alert-danger"><?php echo e(session('error')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($configError): ?>
        <div class="alert alert-danger">
            <strong>⚠️ Ошибка конфигурации:</strong> <?php echo e($configError); ?>

            <p style="margin-top: 0.5rem; margin-bottom: 0;">
                Добавьте необходимые переменные в файл <code>.env</code> и проверьте документацию в <code>SENDER_SETUP.md</code>
            </p>
        </div>
    <?php elseif($apiError): ?>
        <div class="alert alert-danger">
            <strong>⚠️ Ошибка подключения к n8n:</strong> <?php echo e($apiError); ?>

            <details style="margin-top: 0.75rem;">
                <summary style="cursor: pointer; font-weight: 600;">Диагностика</summary>
                <div style="margin-top: 0.5rem; padding: 0.75rem; background: rgba(255, 255, 255, 0.5); border-radius: 6px;">
                    <p><strong>Webhook URL:</strong> <code><?php echo e(config('services.n8n.sender_webhook_url')); ?></code></p>
                    <p><strong>Auth Token установлен:</strong> <?php echo e(config('services.n8n.sender_auth_token') ? '✅ Да' : '❌ Нет'); ?></p>
                    <p style="margin-top: 0.75rem;">
                        <a href="<?php echo e(route('admin.sender.test')); ?>" target="_blank" class="btn btn-primary" style="display: inline-block; padding: 0.5rem 1rem; font-size: 0.875rem;">
                            🔍 Тест подключения к n8n
                        </a>
                    </p>
                    <p style="margin-top: 0.75rem; font-size: 0.875rem;">
                        <strong>Что делать:</strong><br>
                        1. Убедитесь, что n8n workflow запущен и активен<br>
                        2. Проверьте, что webhook URL правильный<br>
                        3. Убедитесь, что токен авторизации настроен в n8n<br>
                        4. Замените placeholder токен в .env на реальный<br>
                        5. Проверьте логи Laravel: <code>storage/logs/laravel.log</code>
                    </p>
                </div>
            </details>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sender): ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="admin-card">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin-bottom: 1rem;">
                    Данные отправителя
                </h3>
                <table class="info-table">
                    <tr>
                        <th>Email</th>
                        <td><strong><?php echo e($sender['email'] ?? '—'); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Имя</th>
                        <td><?php echo e($sender['sender_name'] ?? '—'); ?></td>
                    </tr>
                    <tr>
                        <th>Полное имя</th>
                        <td><?php echo e($sender['sender_full_name'] ?? '—'); ?></td>
                    </tr>
                    <tr>
                        <th>Телефон</th>
                        <td><?php echo e($sender['phone'] ?? '—'); ?></td>
                    </tr>
                    <tr>
                        <th>Статус</th>
                        <td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sender['is_active'] ?? false): ?>
                                <span class="badge badge-success">Активен</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Неактивен</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sender['is_verified'] ?? false): ?>
                                <span class="badge badge-info">Верифицирован</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="admin-card">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin-bottom: 1rem;">
                    Организация
                </h3>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($organization): ?>
                    <table class="info-table">
                        <tr>
                            <th>Название</th>
                            <td><strong><?php echo e($organization['name'] ?? '—'); ?></strong></td>
                        </tr>
                        <tr>
                            <th>ИНН / КПП</th>
                            <td><?php echo e($organization['inn'] ?? '—'); ?> / <?php echo e($organization['kpp'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <th>Адрес</th>
                            <td><?php echo e($organization['legal_address'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <th>Контактное лицо</th>
                            <td><?php echo e($organization['contact_person'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <th>Телефон</th>
                            <td><?php echo e($organization['phone'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo e($organization['email'] ?? '—'); ?></td>
                        </tr>
                    </table>
                <?php else: ?>
                    <p style="color: #9ca3af;">Организация не привязана</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div style="margin-top: 1.5rem;">
            <a href="<?php echo e(route('admin.users.sender.edit', $user)); ?>" class="btn btn-primary">
                ✏️ Редактировать
            </a>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sender['is_active'] ?? false): ?>
                <form action="<?php echo e(route('admin.users.sender.deactivate', $user)); ?>"
                      method="POST"
                      style="display: inline-block;"
                      onsubmit="return confirm('Вы уверены? Это деактивирует отправителя.')">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button type="submit" class="btn btn-danger">🚫 Деактивировать</button>
                </form>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

    <?php else: ?>
        
        <div class="admin-card" style="text-align: center; padding: 3rem;">
            <div class="alert alert-info" style="display: inline-block; text-align: left; max-width: 600px;">
                <p style="margin: 0 0 1rem 0; font-size: 1rem;">
                    У пользователя ещё нет персонального отправителя.
                </p>
                <a href="<?php echo e(route('admin.users.sender.create', $user)); ?>" class="btn btn-success">
                    ➕ Создать отправителя
                </a>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/admin/users/sender/show.blade.php ENDPATH**/ ?>