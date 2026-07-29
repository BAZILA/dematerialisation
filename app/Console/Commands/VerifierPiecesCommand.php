<?php

namespace App\Console\Commands;

use App\Models\CandidatureFile;
use App\Models\RequestFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Contrôle que chaque pièce enregistrée en base a bien son fichier sur le
 * disque. Un écart signale une pièce perdue : le dossier paraît complet
 * alors que l'agent ne pourra rien ouvrir.
 */
class VerifierPiecesCommand extends Command
{
    protected $signature = 'uk:verifier-pieces';

    protected $description = 'Vérifie que les pièces enregistrées ont bien leur fichier sur le disque';

    public function handle(): int
    {
        $disque = Storage::disk('local');
        $perdues = 0;

        $this->info('Contrôle des pièces de candidature…');
        $lignes = [];
        foreach (CandidatureFile::with('candidature', 'requestType')->get() as $f) {
            if (filled($f->file_path) && $disque->exists($f->file_path)) {
                continue;
            }
            $perdues++;
            $lignes[] = [
                $f->id,
                $f->candidature?->reference ?? '—',
                $f->requestType?->label ?? '—',
                $f->original_filename,
            ];
        }
        if ($lignes) {
            $this->table(['Pièce', 'Candidature', 'Document', 'Nom d\'origine'], $lignes);
        } else {
            $this->line('  Aucune pièce manquante.');
        }

        $this->newLine();
        $this->info('Contrôle des pièces de dépôt…');
        $lignes = [];
        foreach (RequestFile::with('studentRequest', 'requestType')->get() as $f) {
            if (filled($f->file_path) && $disque->exists($f->file_path)) {
                continue;
            }
            $perdues++;
            $lignes[] = [
                $f->id,
                $f->studentRequest?->reference ?? '—',
                $f->requestType?->label ?? '—',
                $f->original_filename,
            ];
        }
        if ($lignes) {
            $this->table(['Pièce', 'Demande', 'Document', 'Nom d\'origine'], $lignes);
        } else {
            $this->line('  Aucune pièce manquante.');
        }

        $this->newLine();
        if ($perdues === 0) {
            $this->info('Toutes les pièces sont présentes sur le disque.');

            return self::SUCCESS;
        }

        $this->warn("{$perdues} pièce(s) sans fichier. Il faut les faire redéposer :");
        $this->line('  · candidature — la repasser « à compléter » pour rouvrir le dépôt ;');
        $this->line('  · dépôt — demander à l\'étudiant de recharger la pièce.');

        return self::FAILURE;
    }
}
