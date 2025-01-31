<?php

namespace App\Providers\Filament;

use App\Filament\Bre\Widgets\BREStatsWidget;
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

class BrePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('bre')
            ->path('bre')
            ->brandLogo(asset('svg/klamm-logo.svg'))
            ->darkModeBrandLogo(asset('svg/klamm-logo-dark.svg'))
            ->homeUrl('/welcome')
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Edit Profile')
                    ->url('/profile')
                    ->icon('heroicon-o-pencil-square')
            ])
            ->discoverResources(in: app_path('Filament/Bre/Resources'), for: 'App\\Filament\\Bre\\Resources')
            ->discoverPages(in: app_path('Filament/Bre/Pages'), for: 'App\\Filament\\Bre\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Bre/Widgets'), for: 'App\\Filament\\Bre\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                BREStatsWidget::class,
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
                CheckRole::class . ':bre,bre-view-only,admin',
            ]);
    }
}
