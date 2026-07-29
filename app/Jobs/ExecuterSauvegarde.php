<?php

namespace App\Jobs;

use App\Services\SauvegardeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Sauvegarde exécutée en arrière-plan (bouton « Sauvegarder maintenant »
 * et planification quotidienne). Sur la connexion « sync », le travail
 * s'exécute immédiatement dans la requête, exactement comme avant.
 */
class ExecuterSauvegarde implements ShouldQueue
{
    use Queueable;

    /** Une sauvegarde peut être longue : on laisse de la marge, sans réessai en boucle. */
    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public bool $avecFichiers = true,
        public ?string $declencheur = null,
    ) {
    }

    public function handle(SauvegardeService $service): void
    {
        $r = $service->executer($this->avecFichiers, $this->declencheur);

        Log::info("Sauvegarde terminée : {$r['fichier']} ({$service->formaterTaille($r['taille'])}, {$r['duree']}s)");
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Sauvegarde ÉCHOUÉE : ' . $e->getMessage());

        \App\Models\AuditLog::record('sauvegarde.echec', null,
            'Échec de la sauvegarde : ' . $e->getMessage(),
            ['declencheur' => $this->declencheur]);
    }
}
