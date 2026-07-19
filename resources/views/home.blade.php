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
            padding: 2rem 1rem;
            background: #0D0D0D;
        }
        .card {
            width: 100%;
            max-width: 26rem;
            background: #1A1A1A;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 2.5rem;
        }
        .logo {
            display: block;
            width: 120px;
            height: auto;
            margin: 0 0 2rem;
        }
        .profile {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            margin-bottom: 1.75rem;
        }
        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 999px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .avatar-fallback {
            width: 44px;
            height: 44px;
            border-radius: 999px;
            flex-shrink: 0;
            background: #00FF66;
            color: #0D0D0D;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
        }
        .profile-name {
            color: #fff;
            font-size: 0.9375rem;
            font-weight: 600;
            margin: 0;
        }
        .profile-email {
            color: rgba(255, 255, 255, 0.55);
            font-size: 0.8125rem;
            margin: 0.125rem 0 0;
        }
        h2 {
            color: rgba(255, 255, 255, 0.55);
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 0 0 0.75rem;
        }
        .orgs {
            list-style: none;
            margin: 0 0 2rem;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .orgs li {
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
            font-size: 0.8125rem;
            padding: 0.375rem 0.75rem;
            border-radius: 999px;
        }
        .orgs .empty {
            background: transparent;
            color: rgba(255, 255, 255, 0.4);
            font-style: italic;
            padding-left: 0;
        }
        .actions {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }
        .btn {
            display: block;
            width: 100%;
            text-align: center;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }
        .btn-primary {
            background: #00FF66;
            color: #0D0D0D;
            transition: background-color 0.15s ease;
        }
        .btn-primary:hover {
            background: #00AA44;
            color: #fff;
        }
        .btn-secondary {
            background: transparent;
            color: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: border-color 0.15s ease, color 0.15s ease;
        }
        .btn-secondary:hover {
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="card">
        <img class="logo" src="{{ asset('img/aresta-logo.svg') }}" alt="aresta">

        <div class="profile">
            @if (auth()->user()->avatar_url)
                <img class="avatar" src="{{ auth()->user()->avatar_url }}" alt="">
            @else
                <div class="avatar-fallback">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            @endif
            <div>
                <p class="profile-name">{{ auth()->user()->name }}</p>
                <p class="profile-email">{{ auth()->user()->email }}</p>
            </div>
        </div>

        <h2>Organizações</h2>
        <ul class="orgs">
            @forelse (auth()->user()->organizations as $organization)
                <li>{{ $organization->name }}</li>
            @empty
                <li class="empty">Nenhuma organização vinculada ainda.</li>
            @endforelse
        </ul>

        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/admin') }}">Painel administrativo</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Sair</button>
            </form>
        </div>
    </div>
</body>
</html>
