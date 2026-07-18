<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #1f2937; }
        a { color: #2563eb; }
        ul { padding-left: 1.25rem; }
    </style>
</head>
<body>
    <p>Autenticado como <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).</p>

    <p>Organizações:</p>
    <ul>
        @forelse (auth()->user()->organizations as $organization)
            <li>{{ $organization->name }}</li>
        @empty
            <li><em>Nenhuma organização vinculada ainda.</em></li>
        @endforelse
    </ul>

    <p><a href="{{ url('/admin') }}">Painel administrativo</a></p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Sair</button>
    </form>
</body>
</html>
