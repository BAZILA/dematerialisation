<?php

use App\Services\SauvegardeService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Sauvegarde automatique quotidienne
|--------------------------------------------------------------------------
| Activation, heure et rétention se règlent depuis Paramètres.
| Nécessite que « php artisan schedule:run » tourne chaque minute
| (Planificateur de tâches Windows, ou cron sous Linux).
*/

$sauvegarde = app(SauvegardeService::class);

if ($sauvegarde->automatiqueActive()) {
    Schedule::command('uk:sauvegarder', ['--declencheur=planifiee'])
        ->dailyAt($sauvegarde->heureAuto())
        ->withoutOverlapping(120)      // jamais deux sauvegardes en parallèle
        ->appendOutputTo(storage_path('logs/sauvegarde.log'));
}

// Hygiène de la file d'attente : purge des travaux échoués de plus de 30 jours
Schedule::command('queue:prune-failed', ['--hours=720'])->weekly();
