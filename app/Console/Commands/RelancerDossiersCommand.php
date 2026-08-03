<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\NotificationEnvoyee;
use App\Services\RelanceService;
use Illuminate\Console\Command;

/**
 * Rappelle aux usagers que leur dossier « à compléter » les attend.
 * Prévu pour tourner une fois par jour via le planificateur.
 */
class RelancerDossiersCommand extends Command
{
    protected $signature = 'uk:relancer
                            {--simulation : Lister les dossiers concernés sans rien envoyer}
                            {--force : Relancer même si le réglage automatique est éteint}';

    protected $description = 'Relance les usagers dont le dossier reste incomplet';

    public function handle(RelanceService $service): int
    {
        $simulation = (bool) $this->option('simulation');

        if (!$service->actif() && !$this->option('force') && !$simulation) {
            $this->warn('Relance automatique désactivée dans les réglages — rien à faire.');
            $this->line('  Activez « Relance automatique », ou utilisez --force pour un envoi ponctuel.');

            return self::SUCCESS;
        }

        if (!NotificationEnvoyee::moduleActif() && !$simulation) {
            $this->warn('Module de notifications éteint : les rappels seront consignés « suspendus », sans être expédiés.');
        }

        $this->info(sprintf(
            'Relance des dossiers inactifs depuis %d jour(s) — %d rappel(s) maximum, %d jour(s) entre deux.',
            $service->delaiJours(), $service->maxRelances(), $service->intervalleJours()
        ));
        if ($simulation) {
            $this->comment('Mode simulation : aucun message ne partira.');
        }
        $this->newLine();

        $r = $service->executer($simulation);

        foreach (['candidatures' => 'Candidatures', 'depots' => 'Dépôts'] as $cle => $titre) {
            $this->line("<options=bold>{$titre}</> — " . count($r[$cle]) . ' relance(s)');
            if ($r[$cle]) {
                $this->table(
                    ['Référence', 'Usager', 'Contact', 'Inactif depuis', 'Rappel n°'],
                    array_map(fn ($l) => [
                        $l['reference'], $l['nom'], $l['contact'],
                        $l['jours'] . ' j', $l['rappel'],
                    ], $r[$cle])
                );
            }
            $this->newLine();
        }

        $total = count($r['candidatures']) + count($r['depots']);
        $this->line("Dossiers écartés (trop récents, quota atteint ou déjà relancés) : {$r['ignores']}");

        if (!$simulation && $total > 0) {
            AuditLog::record('relance.automatique', null,
                "Relance des dossiers incomplets — {$total} usager(s) rappelé(s) "
                    . '(' . count($r['candidatures']) . ' candidature(s), ' . count($r['depots']) . ' dépôt(s))',
                ['candidatures' => count($r['candidatures']), 'depots' => count($r['depots'])]);
        }

        $this->newLine();
        $this->info($total > 0
            ? "{$total} usager(s) " . ($simulation ? 'seraient relancés.' : 'relancé(s).')
            : 'Aucun dossier à relancer aujourd\'hui.');

        return self::SUCCESS;
    }
}
