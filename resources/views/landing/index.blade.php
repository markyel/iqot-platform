<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>IQOT — ИИ-система сбора коммерческих предложений</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f1117;
            --bg-secondary: #0a0c10;
            --bg-card: #161a22;
            --bg-card-hover: #1c2129;
            --accent-primary: #10b981;
            --accent-secondary: #34d399;
            --accent-tertiary: #6ee7b7;
            --accent-gradient: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            --accent-gradient-subtle: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(52, 211, 153, 0.15) 100%);
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --border-color: rgba(255, 255, 255, 0.08);
            --glow: 0 0 80px rgba(16, 185, 129, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Background Effects */
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

        .bg-glow {
            position: fixed;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.4;
            pointer-events: none;
            z-index: 0;
        }

        .bg-glow-1 {
            top: -200px;
            right: -100px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.2) 0%, transparent 70%);
        }

        .bg-glow-2 {
            bottom: 20%;
            left: -200px;
            background: radial-gradient(circle, rgba(52, 211, 153, 0.15) 0%, transparent 70%);
        }

        /* Navigation */
        .nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 1rem 2rem;
            background: rgba(10, 14, 23, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
        }

        .logo-text {
            height: 14px;
        }

        .logo-text img {
            height: 100%;
            width: auto;
        }

        .nav-links {
            display: flex;
            gap: 2.5rem;
            list-style: none;
        }

        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: color 0.2s ease;
        }

        .nav-links a:hover {
            color: var(--accent-primary);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .lang-switch {
            display: flex;
            background: var(--bg-card);
            border-radius: 8px;
            padding: 4px;
            gap: 4px;
        }

        .lang-btn {
            padding: 0.4rem 0.8rem;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-family: 'Manrope', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .lang-btn.active {
            background: var(--accent-gradient);
            color: var(--bg-primary);
        }

        .btn {
            padding: 0.75rem 1.75rem;
            border-radius: 10px;
            font-family: 'Manrope', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--accent-gradient);
            color: var(--bg-primary);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.35);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            border-color: var(--accent-primary);
            background: rgba(0, 212, 170, 0.1);
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 8rem 2rem 4rem;
            position: relative;
            z-index: 1;
        }

        .hero-container {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-content {
            animation: fadeInUp 0.8s ease;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(0, 212, 170, 0.1);
            border: 1px solid rgba(0, 212, 170, 0.3);
            border-radius: 100px;
            font-size: 0.85rem;
            color: var(--accent-primary);
            margin-bottom: 1.5rem;
        }

        .hero-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            background: var(--accent-primary);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            letter-spacing: -0.03em;
        }

        .hero h1 .gradient {
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 2.5rem;
            max-width: 540px;
        }

        .hero-cta {
            display: flex;
            gap: 1rem;
            margin-bottom: 3rem;
        }

        .hero-stats {
            display: flex;
            gap: 3rem;
        }

        .stat {
            text-align: left;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        /* Hero Visual */
        .hero-visual {
            position: relative;
            animation: fadeInUp 0.8s ease 0.2s both;
            overflow: visible;
            padding: 30px 50px;
            margin: -30px -50px;
        }

        .dashboard-preview {
            background: var(--bg-card);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            box-shadow: var(--glow);
            position: relative;
            overflow: hidden;
        }

        .dashboard-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
        }

        .dashboard-tab {
            padding: 0.5rem 1rem;
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-muted);
            font-family: 'Manrope', sans-serif;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .dashboard-tab:hover {
            border-color: var(--accent-primary);
            color: var(--text-secondary);
        }

        .dashboard-tab.active {
            background: var(--accent-gradient);
            border-color: transparent;
            color: var(--bg-primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Summary Tab */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .report-title {
            font-weight: 700;
            font-size: 0.95rem;
        }

        .report-badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent-primary);
            border-radius: 4px;
            font-weight: 600;
        }

        .report-meta {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Summary Table */
        .summary-table {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .summary-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 0.5fr;
            padding: 0.6rem 0;
            font-size: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            align-items: center;
        }

        .summary-row.header {
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.65rem;
            letter-spacing: 0.03em;
        }

        .summary-row span:not(:first-child) {
            text-align: right;
        }

        .summary-row .item-name {
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .summary-row .price-min {
            color: var(--accent-primary);
            font-weight: 600;
        }

        .summary-row .qty-badge {
            background: var(--bg-secondary);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
        }

        /* Details Tab */
        .details-table {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .details-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 0.75rem;
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid transparent;
            transition: all 0.2s;
        }

        .details-row.best {
            border-color: var(--accent-primary);
            background: rgba(16, 185, 129, 0.08);
        }

        .details-row .supplier-col {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .details-row .supplier-name {
            font-weight: 600;
            font-size: 0.85rem;
        }

        .details-row .supplier-note {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .details-row.best .supplier-note {
            color: var(--accent-primary);
        }

        .details-row .price-col {
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .details-row .price-value {
            font-weight: 700;
            font-size: 0.9rem;
        }

        .details-row.best .price-value {
            color: var(--accent-primary);
        }

        .details-row .price-note {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        /* Stats Tab */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 0.85rem;
            text-align: center;
        }

        .stat-card.highlight {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .stat-card .stat-number {
            display: block;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .stat-card.highlight .stat-number {
            color: var(--accent-primary);
        }

        .stat-card .stat-desc {
            font-size: 0.65rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .stats-bar {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 0.75rem;
        }

        .stats-bar-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .stats-bar-track {
            height: 6px;
            background: var(--bg-card);
            border-radius: 3px;
            overflow: hidden;
        }

        .stats-bar-fill {
            height: 100%;
            background: var(--accent-gradient);
            border-radius: 3px;
        }

        .report-date {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* Prices Tab - removed, using new styles above */

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .dashboard-title {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .dashboard-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--accent-primary);
        }

        .dashboard-status::before {
            content: '';
            width: 8px;
            height: 8px;
            background: var(--accent-primary);
            border-radius: 50%;
        }

        .dashboard-content {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .request-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .request-card:hover {
            border-color: rgba(16, 185, 129, 0.3);
            transform: translateX(4px);
        }

        .request-info h4 {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }

        .request-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .request-status {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-complete {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.15);
            color: #fbbf24;
        }

        .status-sending {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }

        .floating-card {
            position: absolute;
            background: var(--bg-card);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: float 3s ease-in-out infinite;
            z-index: 10;
        }

        .floating-card-1 {
            top: -20px;
            right: -30px;
            animation-delay: 0s;
        }

        .floating-card-2 {
            bottom: -20px;
            left: -40px;
            animation-delay: 1.5s;
        }

        .floating-metric {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            white-space: nowrap;
        }

        .floating-metric-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .floating-metric-icon svg {
            width: 20px;
            height: 20px;
        }

        .floating-metric-icon.green {
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent-primary);
        }

        .floating-metric-icon.blue {
            background: rgba(52, 211, 153, 0.15);
            color: var(--accent-secondary);
        }

        .floating-metric-value {
            font-weight: 700;
            font-size: 1.1rem;
            line-height: 1.2;
        }

        .floating-metric-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.2;
        }

        /* Section Styles */
        section {
            padding: 6rem 2rem;
            position: relative;
            z-index: 1;
        }

        .section-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-label {
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            color: var(--accent-primary);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: 2.75rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 1rem;
        }

        .section-subtitle {
            font-size: 1.15rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Problems Section */
        .problems {
            background: var(--bg-secondary);
        }

        .problems-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        .problem-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .problem-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #ef4444, #f97316);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .problem-card:hover {
            transform: translateY(-4px);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .problem-card:hover::before {
            opacity: 1;
        }

        .problem-icon {
            width: 48px;
            height: 48px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            color: #ef4444;
        }

        .problem-icon svg {
            width: 28px;
            height: 28px;
        }

        .problem-card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .problem-card p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* How It Works */
        .how-it-works {
            overflow: hidden;
        }

        .process-flow {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            gap: 1rem;
        }

        .process-flow::before {
            content: '';
            position: absolute;
            top: 50px;
            left: 60px;
            right: 60px;
            height: 2px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            opacity: 0.4;
        }

        .process-step {
            flex: 1;
            text-align: center;
            position: relative;
            padding: 0 1rem;
        }

        .step-number {
            width: 100px;
            height: 100px;
            background: var(--bg-card);
            border: 2px solid var(--accent-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }

        .step-number:hover {
            background: rgba(16, 185, 129, 0.1);
            transform: scale(1.05);
            box-shadow: 0 0 40px rgba(16, 185, 129, 0.25);
        }

        .step-icon {
            color: var(--accent-primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .step-icon svg {
            width: 40px;
            height: 40px;
        }

        .process-step h3 {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .process-step p {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        /* Supplier Questions Section */
        .supplier-questions {
            overflow: hidden;
        }

        .questions-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }

        .questions-left {
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }

        .questions-features {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .question-feature {
            display: flex;
            gap: 1.5rem;
            align-items: flex-start;
        }

        .feature-icon {
            width: 56px;
            height: 56px;
            min-width: 56px;
            background: var(--accent-gradient-subtle);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-primary);
        }

        .feature-icon svg {
            width: 32px;
            height: 32px;
        }

        .question-feature p {
            font-size: 1.05rem;
            color: var(--text-primary);
            line-height: 1.7;
            margin-top: 0.5rem;
        }

        .questions-demo {
            position: relative;
        }

        .demo-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .demo-card:hover {
            box-shadow: var(--glow);
            transform: translateY(-4px);
        }

        .demo-header {
            padding: 1.5rem;
            background: rgba(16, 185, 129, 0.05);
            border-bottom: 1px solid var(--border-color);
        }

        .demo-title {
            display: inline-block;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .demo-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: var(--accent-gradient);
            color: var(--bg-primary);
            font-size: 0.8rem;
            font-weight: 700;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .demo-body {
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .demo-supplier-question {
            padding: 1rem 1.25rem;
            background: var(--bg-secondary);
            border-radius: 12px;
            border-left: 3px solid rgba(156, 163, 175, 0.3);
        }

        .supplier-name {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--accent-secondary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .question-text {
            font-size: 0.9rem;
            color: var(--text-primary);
            line-height: 1.5;
        }

        .demo-consolidated-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 10px;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .demo-consolidated-badge svg {
            color: var(--accent-primary);
            flex-shrink: 0;
        }

        .demo-consolidated-badge span {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--accent-primary);
        }

        .demo-question-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .demo-question-item {
            padding: 1rem 1.25rem;
            background: var(--bg-secondary);
            border-radius: 12px;
            border-left: 3px solid transparent;
        }

        .demo-question-item.consolidated {
            border-left-color: var(--accent-primary);
        }

        .demo-question-item.unique {
            border-left-color: #f59e0b;
        }

        .suppliers-count {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-family: 'JetBrains Mono', monospace;
        }

        .demo-answer {
            padding: 1.25rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .demo-answer.auto {
            background: rgba(16, 185, 129, 0.05);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .demo-answer.manual {
            background: rgba(245, 158, 11, 0.05);
            border-color: rgba(245, 158, 11, 0.3);
        }

        .answer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            gap: 1rem;
        }

        .answer-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .demo-answer.auto .answer-label {
            color: var(--accent-primary);
        }

        .demo-answer.manual .answer-label {
            color: #f59e0b;
        }

        .answer-status {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            font-family: 'JetBrains Mono', monospace;
        }

        .answer-text {
            font-size: 0.9rem;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .questions-accent {
            background: var(--accent-gradient-subtle);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 16px;
            padding: 2rem;
        }

        .accent-content {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1.25rem;
        }

        .accent-content svg {
            color: var(--accent-primary);
            flex-shrink: 0;
        }

        .accent-content p {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Benefits Section */
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }

        .benefit-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 2.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .benefit-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--accent-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }

        .benefit-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--glow);
        }

        .benefit-card:hover::before {
            opacity: 0.05;
        }

        .benefit-card > * {
            position: relative;
            z-index: 1;
        }

        .benefit-icon {
            width: 64px;
            height: 64px;
            background: var(--accent-gradient-subtle);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            color: var(--accent-primary);
        }

        .benefit-icon svg {
            width: 32px;
            height: 32px;
        }

        .benefit-metric {
            font-size: 3.5rem;
            font-weight: 800;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .benefit-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .benefit-card p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* Comparison Table */
        .comparison {
            background: var(--bg-secondary);
        }

        .comparison-table {
            background: var(--bg-card);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .comparison-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            background: var(--bg-primary);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .comparison-header span {
            font-weight: 700;
            font-size: 1rem;
        }

        .comparison-header span:not(:first-child) {
            text-align: center;
        }

        .comparison-header .iqot-col {
            color: var(--accent-primary);
        }

        .comparison-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            padding: 1.25rem 2rem;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
        }

        .comparison-row:last-child {
            border-bottom: none;
        }

        .comparison-row:hover {
            background: rgba(16, 185, 129, 0.03);
        }

        .comparison-row span {
            display: flex;
            align-items: center;
        }

        .comparison-row span:not(:first-child) {
            justify-content: center;
        }

        .comparison-row .feature {
            color: var(--text-secondary);
        }

        .check {
            color: var(--accent-primary);
            font-size: 1.5rem;
        }

        .cross {
            color: #ef4444;
            font-size: 1.5rem;
        }

        /* Pricing Section */
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .pricing-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .pricing-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--glow);
        }

        .pricing-card.highlight {
            border-color: var(--accent-primary);
            position: relative;
        }

        .pricing-card.highlight::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-gradient);
        }

        .pricing-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pricing-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .pricing-badge {
            padding: 0.4rem 0.8rem;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--accent-primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .pricing-body {
            padding: 2rem;
        }

        .pricing-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .pricing-item:last-child {
            border-bottom: none;
        }

        .pricing-name {
            font-size: 1rem;
            color: var(--text-secondary);
        }

        .pricing-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
        }

        .pricing-benefits {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .pricing-benefits li {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 1rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .pricing-benefits li::before {
            content: '✓';
            color: var(--accent-primary);
            font-weight: 700;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .pricing-footer {
            text-align: center;
        }

        /* CTA Section */
        .cta {
            text-align: center;
            padding: 8rem 2rem;
        }

        .cta-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .cta h2 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            letter-spacing: -0.02em;
        }

        .cta p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 2.5rem;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn-large {
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
        }

        /* Footer */
        .footer {
            background: var(--bg-secondary);
            padding: 4rem 2rem;
            border-top: 1px solid var(--border-color);
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-left {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .footer-brand {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .footer-tagline {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-style: italic;
        }

        .footer-copyright {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .footer-links {
            display: flex;
            gap: 2rem;
        }

        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }

        .footer-links a:hover {
            color: var(--accent-primary);
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        /* Mobile Menu */
        .nav-links.active { display: flex !important; position: absolute; top: 100%; left: 0; right: 0; flex-direction: column; background: rgba(10, 14, 23, 0.95); padding: 1rem; border-bottom: 1px solid var(--border-color); } .mobile-menu-btn.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); } .mobile-menu-btn.active span:nth-child(2) { opacity: 0; } .mobile-menu-btn.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); } .mobile-menu-btn { position: relative; z-index: 101;
            display: none;
            flex-direction: column;
            gap: 5px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
        }

        .mobile-menu-btn span {
            width: 24px;
            height: 2px;
            background: var(--text-primary);
            transition: all 0.3s ease;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero h1 {
                font-size: 2.75rem;
            }

            .hero-subtitle {
                margin: 0 auto 2.5rem;
            }

            .hero-cta {
                justify-content: center;
            }

            .hero-stats {
                justify-content: center;
            }

            .hero-visual {
                max-width: 600px;
                margin: 0 auto;
            }

            .problems-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .benefits-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .process-flow {
                flex-direction: column;
                align-items: center;
                gap: 2rem;
            }

            .process-flow::before {
                display: none;
            }

            .questions-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .pricing-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        @media (max-width: 768px) {
            html, body {
                overflow-x: hidden;
                width: 100%;
            }

            .nav-links, .lang-switch {
                display: none;
            }

            .nav-links.active { display: flex !important; position: absolute; top: 100%; left: 0; right: 0; flex-direction: column; background: rgba(10, 14, 23, 0.95); padding: 1rem; border-bottom: 1px solid var(--border-color); } .mobile-menu-btn.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); } .mobile-menu-btn.active span:nth-child(2) { opacity: 0; } .mobile-menu-btn.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); } .mobile-menu-btn { position: relative; z-index: 101;
                display: flex;
            }

            .nav {
                padding: 0.5rem 1rem;
            }

            .nav-container {
                gap: 0.5rem;
            }

            .nav-right {
                gap: 0.5rem;
            }

            .nav-right .btn { position: relative; z-index: 101;
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }

            .logo {
                gap: 0.5rem;
            }

            .logo-icon {
                width: 32px;
                height: 32px;
            }

            .hero {
                padding: 4.5rem 1rem 2rem;
                overflow-x: hidden;
            }

            .hero-container {
                padding: 0;
            }

            .hero-content {
                max-width: 100%;
                text-align: center;
                padding: 0;
            }

            .hero-badge {
                font-size: 0.75rem;
                padding: 0.4rem 0.8rem;
            }

            .hero h1 {
                font-size: 1.25rem;
                line-height: 1.3;
                word-wrap: break-word;
                padding: 0;
            }

            .hero-subtitle {
                font-size: 0.9rem;
                padding: 0;
                margin-bottom: 1.5rem;
            }

            .hero-cta {
                flex-direction: column;
                gap: 0.75rem;
                width: 100%;
                padding: 0;
            }

            .hero-cta .btn {
                width: 100%;
                justify-content: center;
                padding: 0.75rem 1rem;
            }

            .hero-stats {
                flex-direction: column;
                gap: 1rem;
                padding: 0;
            }

            .stat {
                text-align: center;
            }

            .stat-value {
                font-size: 1.75rem;
            }

            .hero-visual, .floating-card {
                display: none;
            }

            section {
                padding: 2.5rem 1rem;
                overflow-x: hidden;
            }

            .section-header {
                padding: 0;
            }

            .section-title {
                font-size: 1.3rem;
            }

            .section-subtitle {
                font-size: 0.9rem;
            }

            .problems-grid,
            .benefits-grid,
            .process-flow {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .comparison-header,
            .comparison-row {
                grid-template-columns: 1.5fr 1fr 1fr;
                padding: 0.5rem;
                font-size: 0.7rem;
            }

            .cta {
                padding: 2rem 1rem;
            }

            .cta h2 {
                font-size: 1.3rem;
            }

            .cta p {
                font-size: 0.9rem;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
                gap: 0.75rem;
                width: 100%;
            }

            .cta-buttons .btn {
                width: 100%;
                justify-content: center;
            }

            .footer {
                padding: 2rem 1rem;
            }

            .footer-container {
                flex-direction: column;
                gap: 1.5rem;
                text-align: center;
            }

            .footer-left {
                flex-direction: column;
            }

            .footer-brand {
                align-items: center;
            }

            .dashboard-preview {
                margin-top: 1.5rem;
                overflow-x: auto;
            }

            .dashboard-tabs {
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .tab-btn {
                font-size: 0.75rem;
                padding: 0.5rem 0.75rem;
            }

            .questions-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .questions-left {
                gap: 2rem;
            }

            .question-feature {
                flex-direction: row;
                text-align: left;
                align-items: flex-start;
            }

            .question-feature p {
                font-size: 0.95rem;
            }

            .demo-body {
                padding: 1.5rem;
            }

            .demo-header {
                padding: 1rem;
            }

            .demo-title {
                font-size: 0.9rem;
            }

            .questions-accent {
                padding: 1.5rem;
            }

            .accent-content {
                flex-direction: row;
                align-items: flex-start;
            }

            .accent-content svg {
                width: 40px;
                height: 40px;
            }

            .accent-content p {
                font-size: 0.95rem;
            }

            .answer-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .hero h1 {
                font-size: 1.1rem;
            }

            .hero-subtitle {
                font-size: 0.85rem;
            }

            .section-title {
                font-size: 1.15rem;
            }

            .nav-right .btn { position: relative; z-index: 101;
                padding: 0.35rem 0.6rem;
                font-size: 0.75rem;
            }

            .comparison-header,
            .comparison-row {
                font-size: 0.65rem;
                padding: 0.4rem;
            }

            .question-feature {
                flex-direction: column;
                text-align: center;
                align-items: center;
            }

            .question-feature p {
                font-size: 0.85rem;
            }

            .questions-accent {
                padding: 1.25rem;
            }

            .accent-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .accent-content svg {
                width: 36px;
                height: 36px;
            }

            .accent-content p {
                font-size: 0.85rem;
            }

            .demo-title {
                font-size: 0.8rem;
            }

            .demo-badge {
                font-size: 0.7rem;
                padding: 0.4rem 0.8rem;
            }

            .supplier-name {
                font-size: 0.75rem;
            }

            .demo-consolidated-badge {
                padding: 0.6rem 0.8rem;
            }

            .demo-consolidated-badge span {
                font-size: 0.75rem;
            }

            .pricing-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .pricing-value {
                font-size: 1.25rem;
            }

            .pricing-name {
                font-size: 0.85rem;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 2rem 2rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .modal-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .modal-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: var(--bg-card-hover);
            color: var(--text-primary);
        }

        .modal-body {
            padding: 2rem;
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

        .form-label.required::after {
            content: ' *';
            color: #ef4444;
        }

        .form-input,
        .form-textarea {
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

        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--accent-primary);
            background: var(--bg-card);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
        }

        .form-help {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .form-checkbox-group {
            margin: 1.5rem 0;
        }

        .checkbox-wrapper {
            display: flex;
            gap: 0.75rem;
            align-items: start;
        }

        .form-checkbox {
            width: 20px;
            height: 20px;
            min-width: 20px;
            margin-top: 2px;
            cursor: pointer;
            accent-color: var(--accent-primary);
        }

        .checkbox-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.5;
            cursor: pointer;
        }

        .checkbox-label a {
            color: var(--accent-primary);
            text-decoration: none;
        }

        .checkbox-label a:hover {
            text-decoration: underline;
        }

        .terms-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.25rem;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .terms-box h4 {
            color: var(--text-primary);
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .terms-box ul {
            margin-left: 1.25rem;
            margin-top: 0.5rem;
        }

        .terms-box li {
            margin-bottom: 0.5rem;
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            .modal-content {
                max-height: 100vh;
                border-radius: 0;
            }

            .modal-header,
            .modal-body {
                padding: 1.5rem;
            }

            .modal-title {
                font-size: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="bg-glow bg-glow-1"></div>
    <div class="bg-glow bg-glow-2"></div>

    <!-- Navigation -->
    <nav class="nav">
        <div class="nav-container">
            <a href="#" class="logo">
                <div class="logo-icon"><img src="/images/Q.svg" alt="IQOT"></div>
                <div class="logo-text"><img src="/images/IQOT.svg" alt="IQOT"></div>
            </a>
            <ul class="nav-links">
                <li><a href="#how-it-works" data-ru="Как работает" data-en="How it works">Как работает</a></li>
                <li><a href="#benefits" data-ru="Преимущества" data-en="Benefits">Преимущества</a></li>
                <li><a href="#pricing" data-ru="Тарифы" data-en="Pricing">Тарифы</a></li>
                <li><a href="{{ route('catalog.index') }}" data-ru="Каталог" data-en="Catalog">Каталог</a></li>
            </ul>
            <div class="nav-right">
                <div class="lang-switch">
                    <button class="lang-btn active" data-lang="ru">RU</button>
                    <button class="lang-btn" data-lang="en">EN</button>
                </div>
                @auth
                    <a href="{{ route('cabinet.dashboard') }}" class="btn btn-outline" data-ru="Личный кабинет" data-en="Dashboard">Личный кабинет</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline" data-ru="Войти" data-en="Sign In">Войти</a>
                    <a href="{{ route('register') }}" class="btn btn-primary" data-ru="Регистрация" data-en="Sign Up">Регистрация</a>
                @endauth
            </div>
            <button class="mobile-menu-btn">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge" data-ru="🚀 Новое решение для B2B-закупок" data-en="🚀 New solution for B2B procurement">
                    🚀 Новое решение для B2B-закупок
                </div>
                <h1>
                    <span data-ru="Интеллектуальный сбор и анализ" data-en="Intelligent collection and analysis of">Интеллектуальный сбор и анализ</span><br>
                    <span class="gradient" data-ru="коммерческих предложений" data-en="commercial quotations">коммерческих предложений</span>
                </h1>
                <p class="hero-subtitle" data-ru="Автоматизируйте сбор и анализ ценовых предложений от поставщиков. Система сама отправит запросы, соберёт ответы и подготовит сводный отчёт." data-en="Automate collection and analysis of price quotes from suppliers. The system sends requests, collects responses, and prepares summary reports.">
                    Автоматизируйте сбор и анализ ценовых предложений от поставщиков. Система сама отправит запросы, соберёт ответы и подготовит сводный отчёт.
                </p>
                <div class="hero-cta">
                    <a href="#" class="btn btn-primary" data-demo-modal data-ru="Запросить демо" data-en="Request Demo">Запросить демо</a>
                    <a href="#how-it-works" class="btn btn-outline" data-ru="Как это работает" data-en="How it works">Как это работает</a>
                </div>
                <div class="hero-stats">
                    <div class="stat">
                        <div class="stat-value">90%</div>
                        <div class="stat-label" data-ru="экономия времени" data-en="time saved">экономия времени</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">0</div>
                        <div class="stat-label" data-ru="ошибок консолидации" data-en="consolidation errors">ошибок консолидации</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">∞</div>
                        <div class="stat-label" data-ru="масштабируемость" data-en="scalability">масштабируемость</div>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="dashboard-preview">
                    <div class="dashboard-tabs">
                        <button class="dashboard-tab active" data-tab="summary" data-ru="Сводная таблица" data-en="Summary Table">Сводная таблица</button>
                        <button class="dashboard-tab" data-tab="details" data-ru="Детализация" data-en="Details">Детализация</button>
                        <button class="dashboard-tab" data-tab="stats" data-ru="Статистика" data-en="Statistics">Статистика</button>
                    </div>
                    
                    <div class="dashboard-content">
                        <!-- Summary Tab -->
                        <div class="tab-content active" id="tab-summary">
                            <div class="report-header">
                                <span class="report-title" data-ru="Заявка REQ-20251216" data-en="Request REQ-20251216">Заявка REQ-20251216</span>
                                <span class="report-badge" data-ru="✓ Завершена" data-en="✓ Complete">✓ Завершена</span>
                            </div>
                            <div class="summary-table">
                                <div class="summary-row header">
                                    <span data-ru="Позиция" data-en="Item">Позиция</span>
                                    <span data-ru="Мин" data-en="Min">Мин</span>
                                    <span data-ru="Сред" data-en="Avg">Сред</span>
                                    <span data-ru="Макс" data-en="Max">Макс</span>
                                    <span data-ru="КП" data-en="Qty">КП</span>
                                </div>
                                <div class="summary-row">
                                    <span class="item-name">Шкив DAA261K8</span>
                                    <span class="price-min">23 400 ₽</span>
                                    <span>49 556 ₽</span>
                                    <span>76 466 ₽</span>
                                    <span class="qty-badge">7</span>
                                </div>
                                <div class="summary-row">
                                    <span class="item-name">Плата SMICE 62.Q</span>
                                    <span class="price-min">65 793 ₽</span>
                                    <span>133 615 ₽</span>
                                    <span>201 437 ₽</span>
                                    <span class="qty-badge">2</span>
                                </div>
                                <div class="summary-row">
                                    <span class="item-name">ПЧ ATV71LD17N4Z</span>
                                    <span class="price-min">148 000 ₽</span>
                                    <span>261 593 ₽</span>
                                    <span>526 375 ₽</span>
                                    <span class="qty-badge">4</span>
                                </div>
                                <div class="summary-row">
                                    <span class="item-name">Двигатель CRL-4001</span>
                                    <span class="price-min">20 485 ₽</span>
                                    <span>23 742 ₽</span>
                                    <span>27 000 ₽</span>
                                    <span class="qty-badge">2</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Details Tab -->
                        <div class="tab-content" id="tab-details">
                            <div class="report-header">
                                <span class="report-title" data-ru="Шкив DAA261K8" data-en="Pulley DAA261K8">Шкив DAA261K8</span>
                                <span class="report-meta">OTIS • 1 шт</span>
                            </div>
                            <div class="details-table">
                                <div class="details-row best">
                                    <div class="supplier-col">
                                        <span class="supplier-name">ЛифтМонтажСервис</span>
                                        <span class="supplier-note" data-ru="★ Лучшая цена" data-en="★ Best price">★ Лучшая цена</span>
                                    </div>
                                    <div class="price-col">
                                        <span class="price-value">23 400 ₽</span>
                                        <span class="price-note" data-ru="56 дн." data-en="56 days">56 дн.</span>
                                    </div>
                                </div>
                                <div class="details-row">
                                    <div class="supplier-col">
                                        <span class="supplier-name">Liftway</span>
                                        <span class="supplier-note" data-ru="в наличии" data-en="in stock">в наличии</span>
                                    </div>
                                    <div class="price-col">
                                        <span class="price-value">31 335 ₽</span>
                                        <span class="price-note" data-ru="2 дн." data-en="2 days">2 дн.</span>
                                    </div>
                                </div>
                                <div class="details-row">
                                    <div class="supplier-col">
                                        <span class="supplier-name">ГРИНВИЧ</span>
                                        <span class="supplier-note" data-ru="в наличии" data-en="in stock">в наличии</span>
                                    </div>
                                    <div class="price-col">
                                        <span class="price-value">40 000 ₽</span>
                                        <span class="price-note" data-ru="1 дн." data-en="1 day">1 дн.</span>
                                    </div>
                                </div>
                                <div class="details-row">
                                    <div class="supplier-col">
                                        <span class="supplier-name">ТД СКАЛА</span>
                                        <span class="supplier-note" data-ru="с НДС" data-en="incl. VAT">с НДС</span>
                                    </div>
                                    <div class="price-col">
                                        <span class="price-value">48 800 ₽</span>
                                        <span class="price-note" data-ru="6 дн." data-en="6 days">6 дн.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stats Tab -->
                        <div class="tab-content" id="tab-stats">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <span class="stat-number">28</span>
                                    <span class="stat-desc" data-ru="позиций в заявке" data-en="items in request">позиций в заявке</span>
                                </div>
                                <div class="stat-card">
                                    <span class="stat-number">113</span>
                                    <span class="stat-desc" data-ru="поставщиков запрошено" data-en="suppliers contacted">поставщиков запрошено</span>
                                </div>
                                <div class="stat-card highlight">
                                    <span class="stat-number">51</span>
                                    <span class="stat-desc" data-ru="предложений получено" data-en="quotes received">предложений получено</span>
                                </div>
                                <div class="stat-card">
                                    <span class="stat-number">92%</span>
                                    <span class="stat-desc" data-ru="позиций с ценами" data-en="items with prices">позиций с ценами</span>
                                </div>
                            </div>
                            <div class="stats-bar">
                                <div class="stats-bar-label" data-ru="Срок сбора: 16.12 — 18.12.2025" data-en="Collection period: Dec 16-18, 2025">Срок сбора: 16.12 — 18.12.2025</div>
                                <div class="stats-bar-track">
                                    <div class="stats-bar-fill" style="width: 92%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="floating-card floating-card-1">
                    <div class="floating-metric">
                        <div class="floating-metric-icon green">
                            <svg width="20" height="20" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                    <polyline points="8,48 20,36 32,42 44,24 56,16"/>
                                    <polyline points="44,16 56,16 56,28"/>
                                </g>
                            </svg>
                        </div>
                        <div>
                            <div class="floating-metric-value">-68%</div>
                            <div class="floating-metric-label" data-ru="разброс цен" data-en="price spread">разброс цен</div>
                        </div>
                    </div>
                </div>
                <div class="floating-card floating-card-2">
                    <div class="floating-metric">
                        <div class="floating-metric-icon blue">
                            <svg width="20" height="20" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                    <polygon points="38,6 16,34 30,34 26,58 48,30 34,30"/>
                                </g>
                            </svg>
                        </div>
                        <div>
                            <div class="floating-metric-value">2 дня</div>
                            <div class="floating-metric-label" data-ru="сбор 51 КП" data-en="51 quotes collected">сбор 51 КП</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Problems Section -->
    <section class="problems" id="problems">
        <div class="section-container">
            <div class="section-header">
                <span class="section-label" data-ru="Проблема" data-en="Problem">Проблема</span>
                <h2 class="section-title" data-ru="Ручной сбор КП — это боль" data-en="Manual quote collection is painful">Ручной сбор КП — это боль</h2>
                <p class="section-subtitle" data-ru="Каждый закупщик знает, сколько времени уходит на рутинную работу с поставщиками" data-en="Every procurement manager knows how much time goes into routine supplier work">Каждый закупщик знает, сколько времени уходит на рутинную работу с поставщиками</p>
            </div>
            <div class="problems-grid">
                <div class="problem-card">
                    <div class="problem-icon">
                        <svg width="32" height="32" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <circle cx="32" cy="32" r="22"/>
                                <polyline points="32,18 32,32 42,38"/>
                            </g>
                        </svg>
                    </div>
                    <h3 data-ru="Часы на переписку" data-en="Hours of correspondence">Часы на переписку</h3>
                    <p data-ru="Отправка запросов, напоминания, сбор ответов — монотонная работа съедает рабочий день" data-en="Sending requests, reminders, collecting responses — monotonous work consumes the workday">Отправка запросов, напоминания, сбор ответов — монотонная работа съедает рабочий день</p>
                </div>
                <div class="problem-card">
                    <div class="problem-icon">
                        <svg width="32" height="32" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <rect x="18" y="12" width="28" height="36" rx="2"/>
                                <path d="M14 20h-2a2 2 0 0 0-2 2v32a2 2 0 0 0 2 2h24a2 2 0 0 0 2-2v-2"/>
                                <line x1="26" y1="22" x2="38" y2="22"/>
                                <line x1="26" y1="30" x2="38" y2="30"/>
                                <line x1="26" y1="38" x2="34" y2="38"/>
                            </g>
                        </svg>
                    </div>
                    <h3 data-ru="Хаос в данных" data-en="Data chaos">Хаос в данных</h3>
                    <p data-ru="Цены в почте, Excel, мессенджерах — нет единой картины для принятия решений" data-en="Prices scattered across email, Excel, messengers — no unified picture for decision making">Цены в почте, Excel, мессенджерах — нет единой картины для принятия решений</p>
                </div>
                <div class="problem-card">
                    <div class="problem-icon">
                        <svg width="32" height="32" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <circle cx="32" cy="32" r="22"/>
                                <line x1="24" y1="24" x2="40" y2="40"/>
                                <line x1="40" y1="24" x2="24" y2="40"/>
                            </g>
                        </svg>
                    </div>
                    <h3 data-ru="Человеческие ошибки" data-en="Human errors">Человеческие ошибки</h3>
                    <p data-ru="Опечатки, пропущенные письма, неверная консолидация — риск переплаты" data-en="Typos, missed emails, incorrect consolidation — risk of overpayment">Опечатки, пропущенные письма, неверная консолидация — риск переплаты</p>
                </div>
                <div class="problem-card">
                    <div class="problem-icon">
                        <svg width="32" height="32" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <polyline points="52,12 52,24 40,24"/>
                                <line x1="52" y1="12" x2="36" y2="28"/>
                                <rect x="20" y="36" width="8" height="16" rx="1"/>
                                <rect x="32" y="28" width="8" height="24" rx="1"/>
                                <rect x="44" y="20" width="8" height="32" rx="1"/>
                            </g>
                        </svg>
                    </div>
                    <h3 data-ru="Нет масштабирования" data-en="No scalability">Нет масштабирования</h3>
                    <p data-ru="Больше позиций = больше людей. Линейный рост затрат на закупки" data-en="More items = more people. Linear growth of procurement costs">Больше позиций = больше людей. Линейный рост затрат на закупки</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works" id="how-it-works">
        <div class="section-container">
            <div class="section-header">
                <span class="section-label" data-ru="Решение" data-en="Solution">Решение</span>
                <h2 class="section-title" data-ru="Как работает IQOT" data-en="How IQOT works">
                    <a href="{{ route('why-it-works') }}" style="color: inherit; text-decoration: none;">
                        Как работает IQOT
                    </a>
                </h2>
                <p class="section-subtitle" data-ru="От списка позиций до готового отчёта — полностью автоматически" data-en="From item list to ready report — fully automated">
                    От списка позиций до готового отчёта — полностью автоматически
                    <a href="{{ route('why-it-works') }}" style="color: var(--accent-primary); text-decoration: none; margin-left: 1rem; font-weight: 600;">
                        Подробнее →
                    </a>
                </p>
            </div>
            <div class="process-flow">
                <div class="process-step">
                    <div class="step-number">
                        <span class="step-icon">
                            <svg width="40" height="40" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                    <rect x="12" y="8" width="40" height="48" rx="3"/>
                                    <polyline points="20,24 24,28 32,20"/>
                                    <line x1="38" y1="24" x2="44" y2="24"/>
                                    <polyline points="20,36 24,40 32,32"/>
                                    <line x1="38" y1="36" x2="44" y2="36"/>
                                    <circle cx="26" cy="48" r="4"/>
                                    <line x1="38" y1="48" x2="44" y2="48"/>
                                </g>
                            </svg>
                        </span>
                    </div>
                    <h3 data-ru="Загрузите список" data-en="Upload list">Загрузите список</h3>
                    <p data-ru="Отправьте перечень позиций через Telegram-бот или веб-интерфейс" data-en="Send your item list via Telegram bot or web interface">Отправьте перечень позиций через Telegram-бот или веб-интерфейс</p>
                </div>
                <div class="process-step">
                    <div class="step-number">
                        <span class="step-icon">
                            <svg width="40" height="40" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                    <rect x="14" y="18" width="36" height="32" rx="4"/>
                                    <circle cx="24" cy="32" r="4"/>
                                    <circle cx="40" cy="32" r="4"/>
                                    <line x1="28" y1="42" x2="36" y2="42"/>
                                    <line x1="32" y1="8" x2="32" y2="18"/>
                                    <circle cx="32" cy="8" r="3"/>
                                    <line x1="8" y1="30" x2="14" y2="30"/>
                                    <line x1="8" y1="38" x2="14" y2="38"/>
                                    <line x1="50" y1="30" x2="56" y2="30"/>
                                    <line x1="50" y1="38" x2="56" y2="38"/>
                                </g>
                            </svg>
                        </span>
                    </div>
                    <h3 data-ru="ИИ отправляет запросы" data-en="AI sends requests">ИИ отправляет запросы</h3>
                    <p data-ru="Система генерирует персонализированные письма и отправляет их поставщикам" data-en="System generates personalized emails and sends them to suppliers">Система генерирует персонализированные письма и отправляет их поставщикам</p>
                </div>
                <div class="process-step">
                    <div class="step-number">
                        <span class="step-icon">
                            <svg width="40" height="40" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                    <rect x="8" y="14" width="48" height="36" rx="3"/>
                                    <polyline points="8,17 32,36 56,17"/>
                                    <line x1="8" y1="47" x2="24" y2="32"/>
                                    <line x1="56" y1="47" x2="40" y2="32"/>
                                </g>
                            </svg>
                        </span>
                    </div>
                    <h3 data-ru="Собирает ответы" data-en="Collects responses">Собирает ответы</h3>
                    <p data-ru="Мониторит почту, распознаёт КП из писем и вложений, извлекает цены" data-en="Monitors email, recognizes quotes from messages and attachments, extracts prices">Мониторит почту, распознаёт КП из писем и вложений, извлекает цены</p>
                </div>
                <div class="process-step">
                    <div class="step-number">
                        <span class="step-icon">
                            <svg width="40" height="40" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                    <rect x="10" y="8" width="44" height="48" rx="3"/>
                                    <line x1="18" y1="18" x2="34" y2="18"/>
                                    <line x1="18" y1="26" x2="46" y2="26"/>
                                    <rect x="18" y="34" width="8" height="14" rx="1"/>
                                    <rect x="30" y="38" width="8" height="10" rx="1"/>
                                    <rect x="42" y="30" width="8" height="18" rx="1"/>
                                </g>
                            </svg>
                        </span>
                    </div>
                    <h3 data-ru="Готовый отчёт" data-en="Ready report">Готовый отчёт</h3>
                    <p data-ru="Получите сводную таблицу с ценами, сроками и рекомендациями по выбору" data-en="Get a summary table with prices, terms, and selection recommendations">Получите сводную таблицу с ценами, сроками и рекомендациями по выбору</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Supplier Questions Section -->
    <section class="supplier-questions">
        <div class="section-container">
            <div class="section-header">
                <span class="section-label" data-ru="Вопросы поставщиков" data-en="Supplier Questions">Вопросы поставщиков</span>
                <h2 class="section-title" data-ru="Минимум вопросов — максимум результата" data-en="Minimum questions — maximum results">Минимум вопросов — максимум результата</h2>
            </div>

            <div class="questions-content">
                <div class="questions-left">
                    <div class="questions-features">
                    <div class="question-feature">
                        <div class="feature-icon">
                            <svg width="40" height="40" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                    <circle cx="32" cy="32" r="24"/>
                                    <path d="M20 32l8 8 16-16"/>
                                </g>
                            </svg>
                        </div>
                        <p data-ru="Типовые вопросы от поставщиков обрабатываются автоматически." data-en="Standard supplier questions are processed automatically.">Типовые вопросы от поставщиков обрабатываются автоматически.</p>
                    </div>

                    <div class="question-feature">
                        <div class="feature-icon">
                            <svg width="40" height="40" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                    <circle cx="24" cy="24" r="16"/>
                                    <circle cx="40" cy="40" r="16"/>
                                    <path d="M32 24a8 8 0 0 1 8 8"/>
                                </g>
                            </svg>
                        </div>
                        <p data-ru="Одинаковые вопросы консолидируются." data-en="Identical questions are consolidated.">Одинаковые вопросы консолидируются.</p>
                    </div>

                    <div class="question-feature">
                        <div class="feature-icon">
                            <svg width="40" height="40" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                    <path d="M32 8v48M16 24h32M20 40h24"/>
                                    <circle cx="32" cy="32" r="4"/>
                                </g>
                            </svg>
                        </div>
                        <p data-ru="Новые поставщики получают уже данные ответы без вашего участия." data-en="New suppliers receive existing answers without your involvement.">Новые поставщики получают уже данные ответы без вашего участия.</p>
                    </div>
                    </div>

                    <div class="questions-accent">
                        <div class="accent-content">
                            <svg width="48" height="48" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                    <path d="M8 32h48M32 8v48"/>
                                    <circle cx="32" cy="32" r="24"/>
                                    <circle cx="32" cy="32" r="4" fill="currentColor"/>
                                </g>
                            </svg>
                            <p data-ru="Даже при работе с десятками поставщиков вы отвечаете только на действительно важные вопросы." data-en="Even when working with dozens of suppliers, you only answer truly important questions.">Даже при работе с десятками поставщиков вы отвечаете только на действительно важные вопросы.</p>
                        </div>
                    </div>
                </div>

                <div class="questions-demo">
                    <div class="demo-card">
                        <div class="demo-header">
                            <span class="demo-title" data-ru="Обработка вопросов поставщиков" data-en="Processing supplier questions">Обработка вопросов поставщиков</span>
                        </div>
                        <div class="demo-body">
                            <div class="demo-supplier-question">
                                <div class="supplier-name" data-ru="ТД «ЛифтКомплект»" data-en="TD LiftKomplekt">ТД «ЛифтКомплект»</div>
                                <div class="question-text" data-ru="Подскажите, вам нужен оригинал или аналог?" data-en="Tell me, do you need original or analog?">Подскажите, вам нужен оригинал или аналог?</div>
                            </div>

                            <div class="demo-supplier-question">
                                <div class="supplier-name" data-ru="ООО «ПромЗапчасть»" data-en="OOO PromZapchast">ООО «ПромЗапчасть»</div>
                                <div class="question-text" data-ru="Уточните, пожалуйста, оригинал или аналог рассматриваете?" data-en="Please clarify, are you considering original or analog?">Уточните, пожалуйста, оригинал или аналог рассматриваете?</div>
                            </div>

                            <div class="demo-consolidated-badge">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 13L10 16L17 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span data-ru="Вопросы объединены. Ожидает 1 ответ." data-en="Questions merged. Awaits 1 answer.">Вопросы объединены. Ожидает 1 ответ.</span>
                            </div>

                            <div class="demo-answer auto">
                                <div class="answer-header">
                                    <div class="answer-label" data-ru="Автоматический ответ" data-en="Automatic reply">Автоматический ответ</div>
                                    <div class="answer-status" data-ru="Отправлен всем" data-en="Sent to all">Отправлен всем</div>
                                </div>
                                <div class="answer-text" data-ru="Рассматриваем как оригинал, так и качественные аналоги. Просим указать оба варианта с указанием производителя." data-en="We consider both original and quality analogs. Please specify both options indicating the manufacturer.">Рассматриваем как оригинал, так и качественные аналоги. Просим указать оба варианта с указанием производителя.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="benefits" id="benefits">
        <div class="section-container">
            <div class="section-header">
                <span class="section-label" data-ru="Преимущества" data-en="Benefits">Преимущества</span>
                <h2 class="section-title" data-ru="Почему IQOT" data-en="Why IQOT">Почему IQOT</h2>
                <p class="section-subtitle" data-ru="Реальные коммерческие предложения вместо устаревших прайсов с сайтов" data-en="Real commercial quotes instead of outdated website price lists">Реальные коммерческие предложения вместо устаревших прайсов с сайтов</p>
            </div>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <svg width="48" height="48" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <circle cx="28" cy="28" r="18"/>
                                <line x1="41" y1="41" x2="54" y2="54"/>
                                <circle cx="28" cy="28" r="8"/>
                            </g>
                        </svg>
                    </div>
                    <h3 data-ru="Автопоиск поставщиков" data-en="Auto supplier search">Автопоиск поставщиков</h3>
                    <p data-ru="Система анализирует запрошенный ассортимент и автоматически подбирает релевантный пул поставщиков для рассылки запросов." data-en="System analyzes requested assortment and automatically selects relevant supplier pool for sending requests.">Система анализирует запрошенный ассортимент и автоматически подбирает релевантный пул поставщиков для рассылки запросов.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <svg width="48" height="48" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <path d="M8 12h18l6 6v26a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2V14a2 2 0 0 1 2-2z"/>
                                <polyline points="26,12 26,18 32,18"/>
                                <path d="M36 24h16l6 6v22a2 2 0 0 1-2 2H38a2 2 0 0 1-2-2V26a2 2 0 0 1 2-2z"/>
                                <polyline points="52,24 52,30 58,30"/>
                            </g>
                        </svg>
                    </div>
                    <h3 data-ru="Любые форматы КП" data-en="Any quote formats">Любые форматы КП</h3>
                    <p data-ru="Поддержка всех форматов ценовых предложений: цена в письме, ссылка на сайт, PDF, Excel, Word. Система распознает и извлекает данные из любого источника." data-en="Support for all quote formats: price in email, website link, PDF, Excel, Word. System recognizes and extracts data from any source.">Поддержка всех форматов ценовых предложений: цена в письме, ссылка на сайт, PDF, Excel, Word. Система распознает и извлекает данные из любого источника.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <svg width="48" height="48" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <path d="M10 12h28a4 4 0 0 1 4 4v16a4 4 0 0 1-4 4H22l-8 8v-8h-4a4 4 0 0 1-4-4V16a4 4 0 0 1 4-4z"/>
                                <path d="M46 24h4a4 4 0 0 1 4 4v16a4 4 0 0 1-4 4h-4v8l-8-8H26a4 4 0 0 1-4-4v-4"/>
                                <circle cx="18" cy="24" r="2" fill="currentColor"/>
                                <circle cx="26" cy="24" r="2" fill="currentColor"/>
                                <circle cx="34" cy="24" r="2" fill="currentColor"/>
                            </g>
                        </svg>
                    </div>
                    <h3 data-ru="Умные ответы на вопросы" data-en="Smart Q&A responses">Умные ответы на вопросы</h3>
                    <p data-ru="Автоматические ответы на базовые уточняющие вопросы поставщиков. Однотипные вопросы объединяются — ответив один раз, все поставщики получат ответ." data-en="Automatic responses to basic clarifying questions. Similar questions are grouped — answer once, all suppliers get the response.">Автоматические ответы на базовые уточняющие вопросы поставщиков. Однотипные вопросы объединяются — ответив один раз, все поставщики получат ответ.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <svg width="48" height="48" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <circle cx="32" cy="32" r="22"/>
                                <polyline points="32,18 32,32 42,38"/>
                            </g>
                        </svg>
                    </div>
                    <h3 data-ru="Экономия времени" data-en="Time savings">Экономия времени</h3>
                    <p data-ru="То, что занимало дни ручной работы, система делает за часы. Закупщик фокусируется на стратегических задачах." data-en="What took days of manual work, the system does in hours. Procurement focuses on strategic tasks.">То, что занимало дни ручной работы, система делает за часы. Закупщик фокусируется на стратегических задачах.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <svg width="48" height="48" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <circle cx="32" cy="32" r="22"/>
                                <polyline points="22,32 28,40 42,24"/>
                            </g>
                        </svg>
                    </div>
                    <h3 data-ru="Точность данных" data-en="Data accuracy">Точность данных</h3>
                    <p data-ru="Никаких опечаток и потерянных писем. ИИ обрабатывает каждый ответ и структурирует информацию без ошибок." data-en="No typos or lost emails. AI processes every response and structures information without errors.">Никаких опечаток и потерянных писем. ИИ обрабатывает каждый ответ и структурирует информацию без ошибок.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <svg width="48" height="48" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <path d="M32 32c-4-5-8-10-14-10-7 0-12 5-12 10s5 10 12 10c6 0 10-5 14-10 4 5 8 10 14 10 7 0 12-5 12-10s-5-10-12-10c-6 0-10 5-14 10z"/>
                            </g>
                        </svg>
                    </div>
                    <h3 data-ru="Масштаб без затрат" data-en="Scale without costs">Масштаб без затрат</h3>
                    <p data-ru="Обрабатывайте сотни позиций с той же скоростью, что и десять. Без найма дополнительных сотрудников." data-en="Process hundreds of items at the same speed as ten. Without hiring additional staff.">Обрабатывайте сотни позиций с той же скоростью, что и десять. Без найма дополнительных сотрудников.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Comparison Section -->
    <section class="comparison" id="comparison">
        <div class="section-container">
            <div class="section-header">
                <span class="section-label" data-ru="Сравнение" data-en="Comparison">Сравнение</span>
                <h2 class="section-title" data-ru="IQOT vs Ручной процесс" data-en="IQOT vs Manual process">IQOT vs Ручной процесс</h2>
            </div>
            <div class="comparison-table">
                <div class="comparison-header">
                    <span data-ru="Возможность" data-en="Feature">Возможность</span>
                    <span class="iqot-col">IQOT</span>
                    <span data-ru="Вручную" data-en="Manual">Вручную</span>
                </div>
                <div class="comparison-row">
                    <span class="feature" data-ru="Отправка запросов 50+ поставщикам за раз" data-en="Send requests to 50+ suppliers at once">Отправка запросов 50+ поставщикам за раз</span>
                    <span class="check">✓</span>
                    <span class="cross">✗</span>
                </div>
                <div class="comparison-row">
                    <span class="feature" data-ru="Автоматический парсинг ответов и вложений" data-en="Automatic parsing of responses and attachments">Автоматический парсинг ответов и вложений</span>
                    <span class="check">✓</span>
                    <span class="cross">✗</span>
                </div>
                <div class="comparison-row">
                    <span class="feature" data-ru="Консолидация в единый отчёт" data-en="Consolidation into single report">Консолидация в единый отчёт</span>
                    <span class="check">✓</span>
                    <span class="cross">✗</span>
                </div>
                <div class="comparison-row">
                    <span class="feature" data-ru="Работа 24/7 без перерывов" data-en="24/7 operation without breaks">Работа 24/7 без перерывов</span>
                    <span class="check">✓</span>
                    <span class="cross">✗</span>
                </div>
                <div class="comparison-row">
                    <span class="feature" data-ru="Масштабирование без роста затрат" data-en="Scaling without cost growth">Масштабирование без роста затрат</span>
                    <span class="check">✓</span>
                    <span class="cross">✗</span>
                </div>
                <div class="comparison-row">
                    <span class="feature" data-ru="Персонализированные письма каждому поставщику" data-en="Personalized emails to each supplier">Персонализированные письма каждому поставщику</span>
                    <span class="check">✓</span>
                    <span class="check">✓</span>
                </div>
                <div class="comparison-row">
                    <span class="feature" data-ru="Исключение человеческих ошибок" data-en="Elimination of human errors">Исключение человеческих ошибок</span>
                    <span class="check">✓</span>
                    <span class="cross">✗</span>
                </div>
                <div class="comparison-row">
                    <span class="feature" data-ru="Консолидация вопросов поставщиков" data-en="Consolidation of supplier questions">Консолидация вопросов поставщиков</span>
                    <span class="check">✓</span>
                    <span class="cross">✗</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing" id="pricing">
        <div class="section-container">
            <div class="section-header">
                <span class="section-label" data-ru="Тарифы и оплата" data-en="Pricing">Тарифы и оплата</span>
                <h2 class="section-title" data-ru="Принцип оплаты" data-en="Payment principle">Принцип оплаты</h2>
                <p class="section-subtitle" data-ru="Оплата взимается за обработку конкретных позиций в заявке" data-en="Payment is charged for processing specific items in the request">Оплата взимается за обработку конкретных позиций в заявке</p>
            </div>

            <div class="pricing-grid">
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3 data-ru="Разовые операции" data-en="One-time operations">Разовые операции</h3>
                    </div>
                    <div class="pricing-body">
                        <div class="pricing-item">
                            <div class="pricing-name" data-ru="Мониторинг позиции в заявке" data-en="Item monitoring in request">Мониторинг позиции в заявке</div>
                            <div class="pricing-value">{{ number_format($pricing['monitoring'], 0, ',', ' ') }} ₽</div>
                        </div>
                        <div class="pricing-item">
                            <div class="pricing-name" data-ru="Разблокировка готового отчёта по позиции" data-en="Unlock ready report for item">Разблокировка готового отчёта по позиции</div>
                            <div class="pricing-value">{{ number_format($pricing['report_unlock'], 0, ',', ' ') }} ₽</div>
                        </div>
                    </div>
                </div>

                <div class="pricing-card highlight">
                    <div class="pricing-header">
                        <h3 data-ru="Подписка" data-en="Subscription">Подписка</h3>
                        <span class="pricing-badge" data-ru="не обязательна" data-en="optional">не обязательна</span>
                    </div>
                    <div class="pricing-body">
                        <ul class="pricing-benefits">
                            <li data-ru="Снижает стоимость позиции" data-en="Reduces item cost">Снижает стоимость позиции</li>
                            <li data-ru="Даёт включённые лимиты" data-en="Includes usage limits">Даёт включённые лимиты</li>
                            <li data-ru="Подходит для регулярных закупок" data-en="Suitable for regular procurement">Подходит для регулярных закупок</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="pricing-footer">
                <a href="/pricing" class="btn btn-outline" data-ru="Подробные тарифы" data-en="Detailed pricing">Подробные тарифы</a>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta" id="cta">
        <div class="cta-container">
            <h2 data-ru="Готовы автоматизировать закупки?" data-en="Ready to automate procurement?">Готовы автоматизировать закупки?</h2>
            <p data-ru="Запустим пилотный проект на вашей задаче. Увидите результат за 1 неделю." data-en="We'll launch a pilot project for your task. See results in 1 week.">Запустим пилотный проект на вашей задаче. Увидите результат за 1 неделю.</p>
            <div class="cta-buttons">
                <a href="#" class="btn btn-primary btn-large" data-demo-modal data-ru="Оставить заявку" data-en="Request Demo">Оставить заявку</a>
                <a href="https://t.me/iqot_support" class="btn btn-outline btn-large" data-ru="Написать в Telegram" data-en="Contact via Telegram">Написать в Telegram</a>
            </div>
        </div>
    </section>

    <!-- Demo Request Modal -->
    <div id="demoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Заказать демонстрацию</h3>
                <p class="modal-subtitle">Заполните форму, и мы свяжемся с вами в ближайшее время</p>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <form id="demoRequestForm" method="POST" action="/demo-request">
                    @csrf

                    <div class="form-group">
                        <label class="form-label required" for="full_name">ФИО</label>
                        <input type="text" id="full_name" name="full_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required" for="organization">Организация</label>
                        <input type="text" id="organization" name="organization" class="form-input" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required" for="inn">ИНН</label>
                            <input type="text" id="inn" name="inn" class="form-input" pattern="\d{10}|\d{12}" placeholder="10 или 12 цифр" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="kpp">КПП</label>
                            <input type="text" id="kpp" name="kpp" class="form-input" pattern="\d{9}" placeholder="9 цифр">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required" for="email">Контактный e-mail</label>
                        <input type="email" id="email" name="email" class="form-input" required>
                        <p class="form-help">На этот email будут отправлены результаты</p>
                    </div>

                    <!-- Password field (shown only if user exists) -->
                    <div id="passwordField" class="form-group" style="display: none;">
                        <label class="form-label required" for="password">Пароль от личного кабинета</label>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Введите ваш пароль">
                        <p class="form-help">Этот email уже зарегистрирован. Введите пароль для доступа к личному кабинету.</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label required" for="phone">Телефон</label>
                        <input type="tel" id="phone" name="phone" class="form-input" placeholder="+7 (999) 123-45-67" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required" for="items_list">Список товаров для запроса КП</label>
                        <textarea id="items_list" name="items_list" class="form-textarea" placeholder="Для демонстрации будет выбрана 1-2 позиции. Для принятия заявки в работу - по каждой позиции должна быть предоставлена исчерпывающая информация: название, артикул, марка, количество.&#10;&#10;Пример:&#10;1. Шкив DAA261K8, OTIS, 1 шт&#10;2. Плата SMICE 62.Q, Schindler, 2 шт" required></textarea>
                        <p class="form-help">Укажите название, артикул, марку и количество для каждой позиции</p>
                    </div>

                    <div class="terms-box">
                        <h4>Условия и правила сервиса — основные положения:</h4>
                        <ul>
                            <li><strong>Достоверность данных:</strong> Вы обязуетесь предоставлять только достоверные данные. Заявка должна отражать реальную потребность вашей организации.</li>
                            <li><strong>Передача данных поставщикам:</strong> Отправляя заявку, вы даёте согласие на то, что указанные вами контактные данные и реквизиты организации будут переданы потенциальным поставщикам в составе запроса коммерческого предложения.</li>
                            <li><strong>Полнота информации:</strong> Для демонстрации будет выбрана 1-2 позиции. Для принятия заявки в работу необходимо предоставить исчерпывающую информацию по каждой позиции: название, артикул, марку, количество.</li>
                            <li><strong>Конфиденциальность:</strong> Ваши данные хранятся на защищённых серверах на территории РФ и не передаются третьим лицам, за исключением поставщиков в рамках обработки заявки.</li>
                        </ul>
                        <p style="margin-top: 0.75rem; font-size: 0.8rem;">Подробнее: <a href="/terms" target="_blank">Условия использования</a> • <a href="/privacy" target="_blank">Политика конфиденциальности</a></p>
                    </div>

                    <div class="form-checkbox-group">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" id="terms_accepted" name="terms_accepted" class="form-checkbox" value="1" required>
                            <label for="terms_accepted" class="checkbox-label">
                                Я согласен с <a href="/terms" target="_blank">Условиями использования</a> и <a href="/privacy" target="_blank">Политикой конфиденциальности</a>, и даю согласие на обработку персональных данных
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-submit">Отправить заявку</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Catalog Section -->
    @if($catalogItems->isNotEmpty())
    <section class="section" style="background: var(--bg-secondary); padding: 6rem 0;">
        <div class="section-container">
            <div class="section-header">
                <span class="section-label" data-ru="Каталог" data-en="Catalog">Каталог</span>
                <h2 class="section-title" data-ru="Готовые отчеты от поставщиков" data-en="Ready Reports from Suppliers">Готовые отчеты от поставщиков</h2>
                <p class="section-subtitle" data-ru="Позиции с проверенными предложениями и ценами" data-en="Items with verified offers and prices">Позиции с проверенными предложениями и ценами</p>
            </div>

            <div style="display: grid; gap: 1rem; margin-bottom: 3rem;">
                @foreach($catalogItems as $item)
                <a href="{{ route('catalog.show', $item->id) }}" style="text-decoration: none;">
                    <div class="card" style="display: grid; grid-template-columns: 1fr auto; gap: 2rem; align-items: center; padding: 1.5rem; transition: all 0.3s ease;">
                        <div>
                            <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">{{ $item->name }}</h3>
                            <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; color: var(--text-secondary); font-size: 0.875rem;">
                                @if($item->brand)
                                <span>{{ $item->brand }}</span>
                                @endif
                                @if($item->article)
                                <span style="font-family: 'JetBrains Mono', monospace;">{{ $item->article }}</span>
                                @endif
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1.5rem;">
                            @if($item->min_price && $item->max_price)
                            <div style="text-align: right;">
                                <div style="color: var(--text-muted); font-size: 0.75rem; margin-bottom: 0.25rem;" data-ru="от" data-en="from">от</div>
                                <div style="color: var(--text-primary); font-weight: 700; font-size: 1.25rem;">{{ number_format($item->min_price, 0, ',', ' ') }} ₽</div>
                            </div>
                            @endif
                            <div style="background: var(--accent-gradient-subtle); border: 2px solid rgba(16, 185, 129, 0.3); border-radius: 10px; padding: 0.75rem 1rem; text-align: center; min-width: 80px;">
                                <div style="color: var(--accent-primary); font-weight: 700; font-size: 1.25rem;">{{ $item->offers_count }}</div>
                                <div style="color: var(--text-secondary); font-size: 0.75rem; white-space: nowrap;" data-ru="предлож." data-en="offers">предлож.</div>
                            </div>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>

            <div style="text-align: center;">
                <a href="{{ route('catalog.index') }}" class="btn btn-primary btn-large" data-ru="Смотреть весь каталог" data-en="View Full Catalog">Смотреть весь каталог →</a>
            </div>
        </div>
    </section>
    @endif

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-left">
                <a href="#" class="logo">
                    <div class="logo-icon"><img src="/images/Q.svg" alt="IQOT"></div>
                    <div class="logo-text"><img src="/images/IQOT.svg" alt="IQOT"></div>
                </a>
                <div class="footer-brand">
                    <span class="footer-tagline">Intelligent Quotation & Offer Tracking</span>
                    <span class="footer-copyright">© 2025 IQOT. <span data-ru="Все права защищены" data-en="All rights reserved">Все права защищены</span></span>
                </div>
            </div>
            <div class="footer-links">
                <a href="mailto:info@iqot.ai" data-ru="Связаться" data-en="Contact">Связаться</a>
                <a href="/terms" data-ru="Условия использования" data-en="Terms of Service">Условия использования</a>
                <a href="/privacy" data-ru="Политика конфиденциальности" data-en="Privacy Policy">Политика конфиденциальности</a>
                <a href="/contract" data-ru="Договор-оферта" data-en="Contract">Договор-оферта</a>
            </div>
        </div>
    </footer>

    <script>
        // Dashboard Tabs
        const dashboardTabs = document.querySelectorAll('.dashboard-tab');
        const tabContents = document.querySelectorAll('.tab-content');

        dashboardTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;
                
                // Update active tab
                dashboardTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // Update active content
                tabContents.forEach(content => {
                    content.classList.remove('active');
                    if (content.id === `tab-${targetTab}`) {
                        content.classList.add('active');
                    }
                });
            });
        });

        // Language Switcher
        const langButtons = document.querySelectorAll('.lang-btn');
        const translatableElements = document.querySelectorAll('[data-ru][data-en]');

        function setLanguage(lang) {
            langButtons.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.lang === lang);
            });

            translatableElements.forEach(el => {
                const text = el.getAttribute(`data-${lang}`);
                if (text) {
                    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                        el.placeholder = text;
                    } else {
                        el.textContent = text;
                    }
                }
            });

            document.documentElement.lang = lang;
            localStorage.setItem('iqot-lang', lang);
        }

        langButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                setLanguage(btn.dataset.lang);
            });
        });

        // Check saved language
        const savedLang = localStorage.getItem('iqot-lang');
        if (savedLang) {
            setLanguage(savedLang);
        }

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar background on scroll
        const nav = document.querySelector('.nav');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                nav.style.background = 'rgba(10, 14, 23, 0.95)';
            } else {
                nav.style.background = 'rgba(10, 14, 23, 0.8)';
            }
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.problem-card, .benefit-card, .process-step').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });
    // Mobile menu
        const mobileMenuBtn = document.querySelector(".mobile-menu-btn");
        const navLinks = document.querySelector(".nav-links");
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener("click", function() {
                navLinks.classList.toggle("active");
                mobileMenuBtn.classList.toggle("active");
            });
        }

        // Demo Request Modal
        const demoModal = document.getElementById('demoModal');
        const demoForm = document.getElementById('demoRequestForm');
        const openModalBtns = document.querySelectorAll('[data-demo-modal]');
        const closeModalBtn = document.querySelector('.modal-close');

        openModalBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                demoModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
        });

        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', () => {
                demoModal.style.display = 'none';
                document.body.style.overflow = '';
            });
        }

        demoModal.addEventListener('click', (e) => {
            if (e.target === demoModal) {
                demoModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });

        // Email check for existing user
        const emailInput = document.getElementById('email');
        const passwordField = document.getElementById('passwordField');
        const passwordInput = document.getElementById('password');
        let emailCheckTimeout;

        if (emailInput) {
            emailInput.addEventListener('blur', async () => {
                const email = emailInput.value.trim();
                if (!email || !emailInput.validity.valid) return;

                clearTimeout(emailCheckTimeout);
                emailCheckTimeout = setTimeout(async () => {
                    try {
                        const response = await fetch('/api/check-email', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ email })
                        });

                        const data = await response.json();

                        if (data.exists) {
                            passwordField.style.display = 'block';
                            passwordInput.required = true;
                        } else {
                            passwordField.style.display = 'none';
                            passwordInput.required = false;
                            passwordInput.value = '';
                        }
                    } catch (error) {
                        console.error('Email check error:', error);
                    }
                }, 500);
            });
        }

        // Form submission
        if (demoForm) {
            demoForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const submitBtn = demoForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Отправка...';

                const formData = new FormData(demoForm);

                try {
                    const response = await fetch('/demo-request', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: formData
                    });

                    const data = await response.json();

                    if (response.ok) {
                        // Success
                        if (data.redirect) {
                            // Redirect to password setup page
                            window.location.href = data.redirect;
                        } else {
                            alert(data.message || 'Заявка отправлена!');
                            demoModal.style.display = 'none';
                            document.body.style.overflow = '';
                            demoForm.reset();
                            passwordField.style.display = 'none';
                            passwordInput.required = false;
                        }
                    } else {
                        // Validation errors
                        if (data.errors) {
                            let errorMsg = 'Исправьте ошибки:\n';
                            Object.values(data.errors).forEach(errors => {
                                errors.forEach(error => errorMsg += '• ' + error + '\n');
                            });
                            alert(errorMsg);
                        } else {
                            alert(data.message || 'Произошла ошибка');
                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Произошла ошибка при отправке формы');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            });
        }
    </script>
</body>
</html>