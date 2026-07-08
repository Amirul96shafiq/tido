<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Link Expired | {{ config('app.name', 'tido') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-bg: #09090b;
            --color-card: #18181b;
            --color-border: #27272a;
            --color-text-primary: #f4f4f5;
            --color-text-secondary: #a1a1aa;
            --color-primary: #FFD07D;
            --color-primary-hover: #fcd34d;
            --color-danger: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--color-bg);
            color: var(--color-text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow-x: hidden;
            position: relative;
        }

        /* Subtle glowing background decorations */
        body::before, body::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 208, 125, 0.08) 0%, rgba(255, 208, 125, 0) 70%);
            z-index: 0;
        }

        body::before {
            top: 10%;
            left: 10%;
        }

        body::after {
            bottom: 10%;
            right: 10%;
        }

        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
            background-color: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: 1.25rem;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.4);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .icon-container {
            width: 80px;
            height: 80px;
            background-color: rgba(239, 68, 68, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            position: relative;
        }

        .icon-container::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 2px solid rgba(239, 68, 68, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            100% {
                transform: scale(1.4);
                opacity: 0;
            }
        }

        .icon {
            width: 40px;
            height: 40px;
            color: var(--color-danger);
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            letter-spacing: -0.025em;
            background: linear-gradient(to bottom right, #ffffff, #a1a1aa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            color: var(--color-text-secondary);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2.5rem;
            font-weight: 400;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--color-primary);
            color: #18181b;
            text-decoration: none;
            padding: 0.875rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            width: 100%;
            cursor: pointer;
        }

        .btn:hover {
            background-color: var(--color-primary-hover);
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon-container">
            <!-- Warning / Clock Expired Icon -->
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>
        
        <h1>Link Expired</h1>
        <p>The verification link to change your email address has expired. For your security, email change verification links are only valid for a limited time.</p>
        
        <a href="/admin/profile" class="btn">Return to Profile Settings</a>
    </div>
</body>
</html>
