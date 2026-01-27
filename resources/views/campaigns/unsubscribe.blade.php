<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отписка от рассылки</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 48px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .icon {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .icon svg {
            width: 48px;
            height: 48px;
            stroke: white;
            stroke-width: 3;
            fill: none;
        }

        h1 {
            font-size: 28px;
            color: #1f2937;
            margin-bottom: 16px;
            font-weight: 600;
        }

        p {
            font-size: 16px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .email {
            font-weight: 600;
            color: #667eea;
        }

        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg viewBox="0 0 24 24">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>

        <h1>{{ $message }}</h1>

        <p>Email: <span class="email">{{ $email }}</span></p>

        <p>Вы больше не будете получать письма от нас.</p>

        <div class="footer">
            <p>© {{ date('Y') }} IQOT. Все права защищены.</p>
        </div>
    </div>
</body>
</html>
