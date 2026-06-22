<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Observers\ExportObserver;
use Filament\Actions\Exports\Models\Export;

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
        if (app()->isProduction()) {
            URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', true);
        } else {
            Model::preventLazyLoading(false);
        }

        Gate::define('admin', function (User $user) {
            return $user->hasRole('admin');
        });

        Gate::define('bre', function (User $user) {
            return $user->hasRole('bre');
        });

        Gate::define('user', function (User $user) {
            return $user->hasRole('user');
        });

        Gate::define('fodig', function (User $user) {
            return $user->hasRole('fodig');
        });

        Gate::define('forms', function (User $user) {
            return $user->hasRole('forms');
        });

        Gate::define('form-developer', function (User $user) {
            return $user->hasRole('form-developer');
        });

        Export::observe(ExportObserver::class);
    }
}
