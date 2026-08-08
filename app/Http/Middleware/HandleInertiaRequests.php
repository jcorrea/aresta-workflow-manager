<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    // O publish de workflow (WorkflowVersionController::publish) manda vários problemas do
    // grafo de uma vez em `errors.graph` (um por WorkflowGraphValidator issue). Com o
    // default do Inertia ($withAllErrors = false), só a primeira mensagem chegava no
    // front — e pior, `errors.graph` virava string em vez de array, o que quebrava um
    // v-for no editor (iterava caractere por caractere). Manter todas as mensagens.
    protected $withAllErrors = true;

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $setting = AppSetting::current();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar_url' => $request->user()->avatar_url,
                ] : null,
            ],
            // Identidade configurável em /admin/app-settings (App\Models\AppSetting) — o
            // app.blade.php raiz já resolve título/favicon/cor a partir do mesmo model antes do
            // Vue montar, isto é só pra telas Inertia que precisam do logo/nome em runtime
            // (AppNav.vue, Home.vue, Auth/Login.vue, AppFooter.vue).
            'branding' => [
                'app_name' => $setting->resolvedName(),
                'logo_url' => $setting->logoUrl(),
            ],
        ];
    }
}
