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
|
| Ce fichier est chargé au démarrage de CHAQUE commande artisan. Lire les
| réglages ici interroge la base : sur une installation neuve — base vide,
| cache vide — la table « settings » n'existe pas encore et « migrate »
| lui-même échouerait. On se contente donc de ce qui est disponible, et
| l'horaire par défaut sert de repli tant que la base n'est pas prête.
*/

$heureSauvegarde = '02:00';
$sauvegardeActive = true;

try {
    $sauvegarde = app(SauvegardeService::class);
    $sauvegardeActive = $sauvegarde->automatiqueActive();
    $heureSauvegarde = $sauvegarde->heureAuto();
} catch (\Throwable $e) {
    // Base non encore migrée ou injoignable : on planifie avec les valeurs
    // par défaut. La commande relira les réglages au moment de s'exécuter.
}

if ($sauvegardeActive) {
    Schedule::command('uk:sauvegarder', ['--declencheur=planifiee'])
        ->dailyAt($heureSauvegarde)
        ->withoutOverlapping(120)      // jamais deux sauvegardes en parallèle
        ->appendOutputTo(storage_path('logs/sauvegarde.log'));
}

/*
|--------------------------------------------------------------------------
| Relance des dossiers laissés incomplets
|--------------------------------------------------------------------------
| Un dossier renvoyé à son auteur meurt souvent en silence. Le rappel part
| une fois par jour, en fin de matinée : assez tôt pour être lu dans la
| journée, assez tard pour ne pas se noyer dans le courrier du matin.
| Délais, quota et interrupteur se règlent depuis Paramètres.
*/
Schedule::command('uk:relancer')
    ->dailyAt('10:00')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/relances.log'));

// Hygiène de la file d'attente : purge des travaux échoués de plus de 30 jours
Schedule::command('queue:prune-failed', ['--hours=720'])->weekly();
