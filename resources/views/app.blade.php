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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script>
        (function () {
            var key = 'aresta-workflow-theme';
            var savedTheme = null;
            try {
                savedTheme = localStorage.getItem(key);
            } catch (err) {
                savedTheme = null;
            }
            // A Aresta é dark-native (starter kit): escuro por padrão, claro só se o usuário escolheu.
            var isDark = savedTheme ? savedTheme !== 'light' : true;
            document.documentElement.classList.toggle('dark', isDark);
        })();
    </script>
    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
