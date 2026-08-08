<!DOCTYPE html>
<html lang="pt-BR">
<head>
    @php($branding = \App\Models\AppSetting::current())
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $branding->resolvedName() }}</title>
    @if ($branding->faviconUrl())
        <link rel="icon" href="{{ $branding->faviconUrl() }}">
    @else
        <link rel="icon" href="{{ asset('img/favicon.svg') }}" type="image/svg+xml">
        <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($branding->hasCustomPrimaryColor())
        {{-- Sobrescreve a cor de acento calibrada do starter kit (docs/specs/05-identidade-visual.md
             §6/§8) só quando o admin de fato escolheu uma cor em /admin/app-settings — depois do
             @vite pra vencer a cascata sobre :root/.dark de app.css com a mesma especificidade. --}}
        <style>
            :root, .dark {
                --color-accent: {{ $branding->resolvedPrimaryColor() }};
                --color-accent-ink: {{ $branding->accentInkColor() }};
            }
        </style>
    @endif
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
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
