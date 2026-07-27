<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // Le super-administrateur passe tous les contrôles d'autorisation.
        Gate::before(fn ($user, $ability) => $user->hasRole('super_admin') ? true : null);

        // En production : toutes les URLs générées sont en HTTPS
        // (cohérent avec HSTS + cookies sécurisés).
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
