<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Http\Middleware\CheckRole;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;

class FodigPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('fodig')
            ->path('fodig')
            ->brandLogo(asset('svg/klamm-logo.svg'))
            ->darkModeBrandLogo(asset('svg/klamm-logo-dark.svg'))
            ->homeUrl('/welcome')
            ->sidebarWidth('15rem')
            ->login()
            ->passwordReset()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Edit Profile')
                    ->url('/profile')
                    ->icon('heroicon-o-pencil-square'),
                MenuItem::make()
                    ->label('Admin Settings')
                    ->url('/admin')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->visible(fn() => CheckRole::class . ':admin'),
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Error Lookup Tool')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Siebel Tables')
                    ->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Fodig/Resources'), for: 'App\\Filament\\Fodig\\Resources')
            ->discoverPages(in: app_path('Filament/Fodig/Pages'), for: 'App\\Filament\\Fodig\\Pages')
            ->pages([
                //
            ])
            ->discoverWidgets(in: app_path('Filament/Fodig/Widgets'), for: 'App\\Filament\\Fodig\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                CheckRole::class . ':fodig,fodig-view-only,admin',
            ]);
    }
}
