<?php

namespace App\Providers\Filament;

use App\Filament\Forms\Widgets\FormsStatsWidget;
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

class FormsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('forms')
            ->path('forms')
            ->brandLogo(asset('svg/klamm-logo.svg'))
            ->darkModeBrandLogo(asset('svg/klamm-logo-dark.svg'))
            ->homeUrl('/welcome')
            ->login()
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->userMenuItems([
                MenuItem::make()
                   ->label('Edit Profile')
                   ->url('/profile')
                   ->icon('heroicon-o-pencil-square')
            ])
            ->discoverResources(in: app_path('Filament/Forms/Resources'), for: 'App\\Filament\\Forms\\Resources')
            ->discoverPages(in: app_path('Filament/Forms/Pages'), for: 'App\\Filament\\Forms\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Forms/Widgets'), for: 'App\\Filament\\Forms\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                FormsStatsWidget::class,
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
                CheckRole::class . ':forms,forms-view-only,admin',
            ]);
    }
}
