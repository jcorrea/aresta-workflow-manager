<?php

namespace App\Providers\Filament;

use App\Models\AppSetting;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Identidade configurável via /admin/app-settings (App\Models\AppSetting) — substitui a
        // marca Aresta fixa em código, decisão revista em 2026-08-07 (ver
        // docs/specs/05-identidade-visual.md §2/§7.2/§10). Sem customização, cai nos valores da
        // Aresta como antes.
        $setting = AppSetting::current();
        $customLogo = $setting->logoUrl();

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName($setting->resolvedName())
            ->brandLogo($customLogo ?? asset('img/aresta-logo-black.svg'))
            ->darkModeBrandLogo($customLogo ?? asset('img/aresta-logo.svg'))
            ->brandLogoHeight('1.75rem')
            ->favicon($setting->faviconUrl() ?? asset('img/favicon.svg'))
            // A Aresta é dark-native (starter kit): escuro por padrão, claro como alternativa.
            ->defaultThemeMode(ThemeMode::Dark)
            ->colors([
                'primary' => Color::hex($setting->resolvedPrimaryColor()),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
