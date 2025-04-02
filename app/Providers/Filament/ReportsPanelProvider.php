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
use App\Filament\Reports\Widgets\ReportsStatsWidget;

class ReportsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('reports')
            ->path('reports')
            ->brandLogo(asset('svg/report-dictionary-logo-text-light.svg'))
            ->darkModeBrandLogo(asset('svg/report-dictionary-logo-text-dark.svg'))
            ->homeUrl('/welcome')
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])->userMenuItems([
                MenuItem::make()
                    ->label('Edit Profile')
                    ->url('/profile')
                    ->icon('heroicon-o-pencil-square')
            ])
            ->discoverResources(in: app_path('Filament/Reports/Resources'), for: 'App\\Filament\\Reports\\Resources')
            ->discoverPages(in: app_path('Filament/Reports/Pages'), for: 'App\\Filament\\Reports\\Pages')
            ->pages([
                //Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Reports/Widgets'), for: 'App\\Filament\\Reports\\Widgets')
            ->widgets([
                //ReportsStatsWidget::class,
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
                CheckRole::class . ':reports,reports-view-only,admin',
            ]);
    }
}
