<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title'); ?> — IQOT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f1117;
            --bg-secondary: #0a0c10;
            --bg-card: #161a22;
            --accent-primary: #10b981;
            --accent-secondary: #34d399;
            --accent-gradient: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --border-color: rgba(255, 255, 255, 0.08);
            --error: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .bg-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(16, 185, 129, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16, 185, 129, 0.02) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
            z-index: 0;
        }

        .auth-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
        }

        .auth-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            text-decoration: none;
        }

        .auth-logo img {
            height: 32px;
        }

        .auth-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
        }

        .auth-title {
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .auth-subtitle {
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        .form-input.error {
            border-color: var(--error);
        }

        .form-error {
            color: var(--error);
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-checkbox input {
            width: 18px;
            height: 18px;
            accent-color: var(--accent-primary);
        }

        .form-checkbox label {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .form-link {
            color: var(--accent-primary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .form-link:hover {
            color: var(--accent-secondary);
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.875rem 1.5rem;
            border-radius: 10px;
            font-family: inherit;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            width: 100%;
            background: var(--accent-gradient);
            color: var(--bg-primary);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
        }

        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .auth-footer a {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--accent-primary);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--error);
        }

        @media (max-width: 480px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .auth-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    
    <div class="auth-container">
        <a href="<?php echo e(route('landing')); ?>" class="auth-logo">
            <img src="<?php echo e(asset('images/Q.svg')); ?>" alt="IQOT">
            <img src="<?php echo e(asset('images/IQOT.svg')); ?>" alt="IQOT">
        </a>
        
        <div class="auth-card">
            <?php echo $__env->yieldContent('content'); ?>
        </div>
        
        <?php echo $__env->yieldContent('footer'); ?>
    </div>
</body>
</html>
<?php /**PATH C:\Users\Boag\PhpstormProjects\iqot-platform\resources\views/layouts/auth.blade.php ENDPATH**/ ?>