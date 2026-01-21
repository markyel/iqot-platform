<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Договор-оферта — IQOT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f1117;
            --bg-secondary: #0a0c10;
            --bg-card: #161a22;
            --accent-primary: #10b981;
            --accent-gradient: linear-gradient(135deg, #10b981 0%, #34d399 100%);
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
            line-height: 1.8;
        }

        .header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            background: var(--accent-gradient);
            color: var(--bg-primary);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .card {
            background: var(--bg-card);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        h1 {
            color: var(--text-primary);
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 800;
        }

        .subtitle {
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 1.125rem;
        }

        .pdf-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .pdf-viewer {
            width: 100%;
            height: 800px;
            border: none;
        }

        .download-section {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .btn-outline {
            padding: 0.75rem 1.5rem;
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-outline:hover {
            border-color: var(--accent-primary);
            color: var(--accent-primary);
        }

        @media (max-width: 768px) {
            .pdf-viewer {
                height: 600px;
            }

            h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-container">
            <a href="/" class="logo">IQOT</a>
            <a href="/" class="btn">На главную</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1>Договор-оферта</h1>
            <p class="subtitle">Публичная оферта на оказание услуг по платформе IQOT</p>

            <div class="pdf-container">
                <iframe
                    src="/docs/contract.pdf"
                    class="pdf-viewer"
                    title="Договор-оферта IQOT"
                ></iframe>
            </div>

            <div class="download-section">
                <a href="/docs/contract.pdf" download class="btn">Скачать PDF</a>
                <a href="/docs/contract.pdf" target="_blank" class="btn-outline">Открыть в новой вкладке</a>
            </div>
        </div>
    </div>
</body>
</html>
