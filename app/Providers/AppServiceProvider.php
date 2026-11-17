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
        /* Passerelle de paiement : manuelle tant qu'aucun contrat marchand
           n'est signé. Pour automatiser T-Money, écrire une classe qui
           implémente PasserelleOperateur et changer cette seule ligne. */
        $this->app->bind(
            \App\Services\Paiement\PasserelleOperateur::class,
            \App\Services\Paiement\PasserelleManuelle::class
        );

        /* Passerelle SMS : choisie par réglage. « journal » n'envoie rien
           et sert tant qu'aucun contrat opérateur n'est signé. */
        $this->app->bind(\App\Services\Sms\PasserelleSms::class, function () {
            return match (\App\Models\Setting::get('sms.fournisseur', 'journal')) {
                'togocom' => new \App\Services\Sms\SmsTogocom(),
                'moov'    => new \App\Services\Sms\SmsMoov(),
                default   => new \App\Services\Sms\SmsJournal(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Le super-administrateur passe tous les contrôles d'autorisation.
        Gate::before(fn ($user, $ability) => $user->hasRole('super_admin') ? true : null);

        /* Limiteur propre aux codes de secours 2FA.
           Sans clé dédiée, Laravel range toutes les routes « throttle:n,m »
           d'un même compte dans le même seau : un agent qui vient de rater
           son code ne pourrait plus régénérer ses codes de secours —
           précisément la manœuvre de déblocage dont il a besoin. */
        \Illuminate\Support\Facades\RateLimiter::for('2fa-codes', fn ($request) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)
                ->by('2fa-codes:' . ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // En production : toutes les URLs générées sont en HTTPS
        // (cohérent avec HSTS + cookies sécurisés).
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
