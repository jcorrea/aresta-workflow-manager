<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sem isso, atrás do proxy reverso da Hostinger (termina HTTPS e repassa pro
        // PHP-FPM por HTTP puro) o Laravel acha que todo request é HTTP — quebra scheme
        // em URLs geradas e, mais grave, o fluxo de OAuth (SSO Microsoft): o cookie de
        // sessão sai marcado incorretamente e o state do /auth/azure/callback nunca bate
        // com o de /auth/azure/redirect (InvalidStateException). '*' porque a Hostinger
        // não documenta um IP fixo de proxy pra confiar especificamente.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
