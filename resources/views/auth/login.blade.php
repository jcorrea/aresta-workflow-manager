<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #0D0D0D;
        }
        .card {
            width: 100%;
            max-width: 22rem;
            background: #1A1A1A;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 3rem 3rem 2.5rem;
            text-align: center;
        }
        .logo {
            display: block;
            width: 140px;
            height: auto;
            margin: 0 auto 2rem;
        }
        h1 {
            color: #fff;
            font-size: 1.0625rem;
            font-weight: 600;
            margin: 0 0 0.375rem;
        }
        p.subtitle {
            color: rgba(255, 255, 255, 0.55);
            font-size: 0.8125rem;
            line-height: 1.5;
            margin: 0 0 1.75rem;
        }
        a.btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            width: 100%;
            background: #00FF66;
            color: #0D0D0D;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: background-color 0.15s ease;
        }
        a.btn:hover {
            background: #00AA44;
            color: #fff;
        }
        a.btn svg {
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <div class="card">
        <img class="logo" src="{{ asset('img/aresta-logo.svg') }}" alt="aresta">
        <h1>{{ config('app.name') }}</h1>
        <p class="subtitle">Entre com sua conta corporativa para continuar</p>
        <a class="btn" href="{{ route('azure.redirect') }}">
            <svg width="16" height="16" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">
                <rect x="1" y="1" width="9" height="9" fill="#F25022" />
                <rect x="11" y="1" width="9" height="9" fill="#7FBA00" />
                <rect x="1" y="11" width="9" height="9" fill="#00A4EF" />
                <rect x="11" y="11" width="9" height="9" fill="#FFB900" />
            </svg>
            Entrar com Microsoft
        </a>
    </div>
</body>
</html>
