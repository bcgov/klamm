<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\FormVersion;
use App\Observers\FormVersionObserver;

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
            Model::preventLazyLoading(true);
        }

        Gate::define('admin', function (User $user) {
            return $user->hasRole('admin');
        });

        Gate::define('bre', function (User $user) {
            return $user->hasRole('bre');
        });

        Gate::define('bre-view-only', function (User $user) {
            return $user->hasRole('bre-view-only');
        });

        Gate::define('fodig', function (User $user) {
            return $user->hasRole('fodig');
        });

        Gate::define('fodig-view-only', function (User $user) {
            return $user->hasRole('fodig-view-only');
        });

        Gate::define('forms', function (User $user) {
            return $user->hasRole('forms');
        });

        Gate::define('forms-view-only', function (User $user) {
            return $user->hasRole('forms-view-only');
        });

        Gate::define('form-developer', function (User $user) {
            return $user->hasRole('form-developer');
        });
    }
}
