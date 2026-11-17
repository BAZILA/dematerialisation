<?php

namespace App\Console\Commands;

use App\Services\SauvegardeService;
use Illuminate\Console\Command;

class SauvegarderCommand extends Command
{
    protected $signature = 'uk:sauvegarder
                            {--sans-fichiers : Sauvegarder uniquement la base, sans les pièces déposées}
                            {--declencheur=console : Origine de la sauvegarde (trace dans l\'audit)}';

    protected $description = 'Sauvegarde la base de données et les pièces déposées dans une archive ZIP horodatée';

    public function handle(SauvegardeService $service): int
    {
        $avecFichiers = !$this->option('sans-fichiers');

        $this->info('Sauvegarde en cours' . ($avecFichiers ? ' (base + fichiers)' : ' (base seule)') . '…');

        try {
            $r = $service->executer($avecFichiers, $this->option('declencheur'));
        } catch (\Throwable $e) {
            $this->error('Échec : ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("  Archive  : {$r['fichier']}");
        $this->line("  Taille   : " . $service->formaterTaille($r['taille']));
        $this->line("  Base     : " . ($r['base'] ? 'incluse' : 'ABSENTE'));
        $this->line("  Fichiers : {$r['fichiers']}");
        $this->line("  Durée    : {$r['duree']} s");

        foreach ($r['avertissements'] as $a) {
            $this->warn('  ⚠ ' . $a);
        }

        // Une archive sans base n'est pas une vraie sauvegarde : on le signale au planificateur
        return $r['base'] ? self::SUCCESS : self::FAILURE;
    }
}
