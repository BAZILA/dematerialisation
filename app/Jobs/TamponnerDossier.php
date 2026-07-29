<?php

namespace App\Jobs;

use App\Models\StudentRequest;
use App\Models\User;
use App\Services\TamponnageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Tamponnage des pièces d'un dossier validé, en arrière-plan.
 *
 * L'agent n'attend plus la génération des PDF : la validation est instantanée
 * et les fichiers tamponnés apparaissent quelques secondes plus tard.
 * Le traitement est idempotent — un réessai ne retamponne pas ce qui l'est déjà.
 */
class TamponnerDossier implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries = 3;

    /** Espacement des réessais : 10 s, puis 1 min, puis 5 min. */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public int $studentRequestId,
        public int $agentId,
    ) {
    }

    public function handle(TamponnageService $service): void
    {
        $sr = StudentRequest::with('files.requestType', 'etudesAnterieures')->find($this->studentRequestId);
        $agent = User::find($this->agentId);

        if (!$sr || !$agent) {
            Log::warning("Tamponnage annulé : demande #{$this->studentRequestId} ou agent #{$this->agentId} introuvable.");

            return;
        }

        $r = $service->traiter($sr, $agent);

        Log::info("Tamponnage terminé pour la demande #{$sr->id} : "
            . "{$r['tamponnes']} pièce(s) tamponnée(s)"
            . ($r['echecs'] ? ', échecs : ' . implode(', ', $r['echecs']) : ''));

        if ($r['echecs']) {
            \App\Models\AuditLog::record('depot.tampon_echec', $sr,
                "Tamponnage en échec pour " . count($r['echecs']) . " pièce(s) — "
                    . implode(', ', $r['echecs']),
                ['fichiers' => $r['echecs']]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Tamponnage ÉCHOUÉ pour la demande #{$this->studentRequestId} : " . $e->getMessage());

        \App\Models\AuditLog::record('depot.tampon_echec', null,
            "Tamponnage abandonné pour la demande #{$this->studentRequestId} : " . $e->getMessage());
    }
}
