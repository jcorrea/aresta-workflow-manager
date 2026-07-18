<?php

namespace App\Providers;

use App\Models\Organization;
use App\Observers\OrganizationObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Contexto ambiente default do spatie/laravel-permission (teams = organization_id):
        // `0` é o sentinel de "sem organização", onde vive o papel global `platform-staff`
        // (00-visao-geral.md §6, item 2). Trocar para o id de uma organização real é
        // responsabilidade do trocador de organização (item em aberto, 00-visao-geral.md §9),
        // ainda não implementado — até lá, checagens de papéis escopados por organização
        // (workflow-admin/editor/viewer) precisam setar o contexto explicitamente.
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', Provider::class);
        });

        Organization::observe(OrganizationObserver::class);
    }
}
