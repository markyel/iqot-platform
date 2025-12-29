<?php $__env->startSection('title', 'Редактирование отправителя'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .admin-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
    }

    .form-label .required {
        color: #ef4444;
    }

    .form-input, .form-select, .form-textarea {
        width: 100%;
        background: #ffffff;
        border: 1px solid #d1d5db;
        color: #111827;
        padding: 0.625rem 1rem;
        border-radius: 8px;
        outline: none;
        font-size: 0.9375rem;
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .form-input.is-invalid, .form-select.is-invalid {
        border-color: #ef4444;
    }

    .form-input:disabled {
        background: #f3f4f6;
        color: #6b7280;
        cursor: not-allowed;
    }

    .invalid-feedback {
        color: #ef4444;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-block;
        font-size: 1rem;
    }

    .btn-primary {
        background: #10b981;
        color: white;
    }

    .btn-primary:hover {
        background: #059669;
    }

    .btn-secondary {
        background: #6b7280;
        color: white;
    }

    .btn-secondary:hover {
        background: #4b5563;
    }

    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .info-box {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }

    .info-box strong {
        color: #111827;
        font-size: 1rem;
    }

    .info-box small {
        color: #6b7280;
        font-size: 0.875rem;
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div style="max-width: 1200px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1 style="font-size: 1.875rem; font-weight: 700; color: #111827;">
            ✏️ Редактирование отправителя: <?php echo e($user->name); ?>

        </h1>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
        <div class="alert alert-danger"><?php echo e(session('error')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form action="<?php echo e(route('admin.users.sender.update', $user)); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>
        <div class="admin-card">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem;">
                <!-- Левая колонка: Настройки отправителя -->
                <div>
                    <div class="section-title">📧 Настройки отправителя</div>

                    <div class="info-box">
                        <small style="display: block; margin-bottom: 0.25rem;">Email адрес (не изменяется)</small>
                        <strong><?php echo e($sender['email'] ?? '—'); ?></strong>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Шаблон письма</label>
                        <select name="template_id" class="form-select">
                            <option value="">По умолчанию</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $template): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($template['id']); ?>"
                                    <?php echo e((old('template_id', $sender['template_id'] ?? null) == $template['id']) ? 'selected' : ''); ?>>
                                    <?php echo e($template['name']); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Имя отправителя <span class="required">*</span></label>
                        <input type="text" name="sender_name" class="form-input <?php $__errorArgs = ['sender_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                               value="<?php echo e(old('sender_name', $sender['sender_name'] ?? '')); ?>" required>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['sender_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="invalid-feedback"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Полное имя <span class="required">*</span></label>
                        <input type="text" name="sender_full_name" class="form-input <?php $__errorArgs = ['sender_full_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                               value="<?php echo e(old('sender_full_name', $sender['sender_full_name'] ?? '')); ?>" required>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['sender_full_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="invalid-feedback"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Телефон</label>
                        <input type="text" name="phone" class="form-input"
                               value="<?php echo e(old('phone', $sender['phone'] ?? '')); ?>">
                    </div>
                </div>

                <!-- Правая колонка: Данные организации -->
                <div>
                    <div class="section-title">🏢 Данные организации</div>

                    <div class="form-group">
                        <label class="form-label">Название организации <span class="required">*</span></label>
                        <input type="text" name="organization[name]" class="form-input <?php $__errorArgs = ['organization.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                               value="<?php echo e(old('organization.name', $organization['name'] ?? '')); ?>" required>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['organization.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="invalid-feedback"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">ИНН</label>
                            <input type="text" name="organization[inn]" class="form-input"
                                   value="<?php echo e(old('organization.inn', $organization['inn'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">КПП</label>
                            <input type="text" name="organization[kpp]" class="form-input"
                                   value="<?php echo e(old('organization.kpp', $organization['kpp'] ?? '')); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Юридический адрес</label>
                        <textarea name="organization[legal_address]" class="form-textarea" rows="2"><?php echo e(old('organization.legal_address', $organization['legal_address'] ?? '')); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Контактное лицо</label>
                        <input type="text" name="organization[contact_person]" class="form-input"
                               value="<?php echo e(old('organization.contact_person', $organization['contact_person'] ?? '')); ?>">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Телефон организации</label>
                            <input type="text" name="organization[phone]" class="form-input"
                                   value="<?php echo e(old('organization.phone', $organization['phone'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email организации</label>
                            <input type="email" name="organization[email]" class="form-input"
                                   value="<?php echo e(old('organization.email', $organization['email'] ?? '')); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">✅ Сохранить изменения</button>
            <a href="<?php echo e(route('admin.users.sender.show', $user)); ?>" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cabinet', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/admin/users/sender/edit.blade.php ENDPATH**/ ?>