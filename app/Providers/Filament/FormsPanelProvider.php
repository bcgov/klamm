<?php

namespace App\Providers\Filament;

use App\Filament\Forms\Widgets\FormsStatsWidget;
use App\Filament\Forms\Widgets\YourFormsWidget;
use App\Filament\Forms\Widgets\FormsDescriptionWidget;
use App\Filament\Forms\Widgets\MinistryGraphWidget;
use App\Filament\Forms\Widgets\YourFormsLogsWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Http\Middleware\CheckRole;
use Filament\Navigation\MenuItem;
use Rmsramos\Activitylog\ActivitylogPlugin;
use App\Filament\Plugins\ActivityLog\CustomActivitylogResource;
use Filament\Navigation\NavigationGroup;

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
            ->passwordReset()
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
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
            ->discoverResources(in: app_path('Filament/Forms/Resources'), for: 'App\\Filament\\Forms\\Resources')
            ->pages([
                \App\Filament\Forms\Pages\FormsDashboard::class,
            ])
            ->widgets([
                FormsDescriptionWidget::class,
                YourFormsWidget::class,
                YourFormsLogsWidget::class,
                FormsStatsWidget::class,
                MinistryGraphWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Form Metadata')
                    ->collapsible(true)
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Form Building'),
            ])
            ->plugins([
                ActivitylogPlugin::make()
                    ->label('Log')
                    ->pluralLabel('Logs')
                    ->navigationItem(false)
                    ->navigationGroup('Activity Log')
                    ->navigationIcon('heroicon-o-shield-check')
                    ->navigationSort(2)
                    ->resource(CustomActivitylogResource::class)
                    ->authorize(fn() => CheckRole::hasRole(request(), 'admin'))
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
