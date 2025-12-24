<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Создание пароля — IQOT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f1117;
            --bg-secondary: #0a0c10;
            --bg-card: #161a22;
            --accent-primary: #10b981;
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --border-color: rgba(255, 255, 255, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 500px;
            width: 100%;
            background: var(--bg-card);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 3rem;
        }

        h1 {
            color: var(--text-primary);
            font-size: 2rem;
            margin-bottom: 0.5rem;
            font-weight: 800;
        }

        .subtitle {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .info-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 2rem;
        }

        .info-box p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .info-box strong {
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            font-family: 'Manrope', sans-serif;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            background: var(--bg-card);
        }

        .form-help {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .error {
            color: #ef4444;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            background: var(--accent-primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn:hover {
            background: #059669;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .skip-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .skip-link:hover {
            color: var(--accent-primary);
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .container {
                padding: 2rem;
            }

            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Создание пароля</h1>
        <p class="subtitle">Установите пароль для доступа к личному кабинету</p>

        <div class="info-box">
            <p><strong>Ваша заявка принята!</strong></p>
            <p>Создайте пароль для доступа к результатам. Вы сможете отслеживать статус заявки и получать коммерческие предложения в личном кабинете.</p>
        </div>

        @if(session('error'))
            <div class="error" style="margin-bottom: 1rem; padding: 1rem; background: rgba(239, 68, 68, 0.1); border-radius: 10px;">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('set-password.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label class="form-label" for="password">Пароль</label>
                <input type="password" id="password" name="password" class="form-input" required minlength="8" autofocus>
                <p class="form-help">Минимум 8 символов</p>
                @error('password')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirmation">Повторите пароль</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" required minlength="8">
                @error('password_confirmation')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn">Создать пароль и войти</button>
        </form>

        <a href="/" class="skip-link">Пропустить (создать пароль позже)</a>
    </div>
</body>
</html>
