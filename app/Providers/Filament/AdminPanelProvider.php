<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
use Althinect\FilamentSpatieRolesPermissions\FilamentSpatieRolesPermissionsPlugin;
use App\Http\Middleware\CheckRole;
use Filament\Navigation\MenuItem;
use Rmsramos\Activitylog\ActivitylogPlugin;
use App\Filament\Plugins\ActivityLog\CustomActivitylogResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandLogo(asset('svg/klamm-logo.svg'))
            ->darkModeBrandLogo(asset('svg/klamm-logo-dark.svg'))
            ->homeUrl('/welcome')
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
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                //
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->plugins([
                FilamentSpatieRolesPermissionsPlugin::make(),
                ActivitylogPlugin::make()
                    ->label('Log')
                    ->pluralLabel('Logs')
                    ->navigationItem(true)
                    ->navigationGroup('Activity Log')
                    ->navigationIcon('heroicon-o-shield-check')
                    ->navigationSort(2)
                    ->isResourceActionHidden(true)
                    ->isRestoreModelActionHidden(true)
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
                CheckRole::class . ':admin',
            ]);
    }
}
