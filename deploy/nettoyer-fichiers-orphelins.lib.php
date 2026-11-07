<?php

/**
 * Logique du nettoyage des fichiers orphelins.
 *
 * Separee du point d'entree pour pouvoir etre eprouvee sur un dossier
 * temporaire, sans jamais toucher au vrai stockage pendant les essais.
 */

use Illuminate\Support\Facades\DB;

/** Dossiers ou l'application range les pieces deposees. Rien d'autre. */
const UK_DOSSIERS_DE_PIECES = ['requests', 'candidatures'];

function ukNettoyerOrphelins(string $racine, bool $pourDeVrai): int
{
    $racine = rtrim(str_replace('\\', '/', $racine), '/');

    echo $pourDeVrai
        ? "MODE SUPPRESSION — les fichiers orphelins vont etre effaces.\n\n"
        : "MODE A BLANC — rien ne sera efface. Relancez avec --supprimer.\n\n";

    /* 1. Ce que la base reference encore.
       Si la base est injoignable, on s'arrete : un ensemble de references
       vide ferait prendre TOUS les fichiers pour des orphelins. */
    try {
        $references = [];
        foreach (DB::table('request_files')->select('file_path', 'stamped_file_path')->cursor() as $f) {
            foreach ([$f->file_path, $f->stamped_file_path] as $chemin) {
                if ($chemin) {
                    $references[ukNormaliser($chemin)] = true;
                }
            }
        }
        foreach (DB::table('candidature_files')->select('file_path')->cursor() as $f) {
            if ($f->file_path) {
                $references[ukNormaliser($f->file_path)] = true;
            }
        }
    } catch (\Throwable $e) {
        fwrite(STDERR, "ARRET : base injoignable, aucun fichier touche.\n  " . $e->getMessage() . "\n");
        return 2;
    }

    echo 'Fichiers references en base : ' . count($references) . "\n\n";

    /* 2. Le disque, dossier de pieces par dossier de pieces. */
    $orphelins = [];
    $gardes = 0;

    foreach (UK_DOSSIERS_DE_PIECES as $dossier) {
        $base = "{$racine}/{$dossier}";
        if (!is_dir($base)) {
            continue;
        }

        $iterateur = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterateur as $fichier) {
            // Un lien symbolique pourrait pointer hors du stockage : on l'ignore.
            if (!$fichier->isFile() || $fichier->isLink()) {
                continue;
            }

            $absolu = str_replace('\\', '/', $fichier->getPathname());
            $relatif = ukNormaliser(substr($absolu, strlen($racine) + 1));

            if (isset($references[$relatif])) {
                $gardes++;
            } else {
                $orphelins[] = [$absolu, $relatif, $fichier->getSize()];
            }
        }
    }

    /* 3. Compte rendu, puis suppression eventuelle. */
    $octets = array_sum(array_column($orphelins, 2));
    echo "Fichiers encore references (gardes) : {$gardes}\n";
    echo 'Fichiers orphelins                  : ' . count($orphelins)
        . ' (' . round($octets / 1048576, 2) . " Mo)\n\n";

    foreach ($orphelins as [$absolu, $relatif]) {
        echo ($pourDeVrai ? '  supprime : ' : '  a supprimer : ') . $relatif . "\n";
        if ($pourDeVrai) {
            @unlink($absolu);
        }
    }

    if ($pourDeVrai) {
        ukRetirerDossiersVides($racine);
        echo "\nTermine.\n";
    } elseif ($orphelins) {
        echo "\nRien n'a ete efface. Pour supprimer : ajoutez --supprimer\n";
    }

    return 0;
}

/** Chemin relatif homogene : barres obliques, sans barre de tete. */
function ukNormaliser(string $chemin): string
{
    return ltrim(str_replace('\\', '/', $chemin), '/');
}

/** Retire les dossiers devenus vides — uniquement sous les dossiers de pieces. */
function ukRetirerDossiersVides(string $racine): void
{
    foreach (UK_DOSSIERS_DE_PIECES as $dossier) {
        $base = "{$racine}/{$dossier}";
        if (!is_dir($base)) {
            continue;
        }

        $iterateur = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterateur as $element) {
            if ($element->isDir() && !$element->isLink()
                && count(scandir($element->getPathname())) === 2) {
                @rmdir($element->getPathname());
            }
        }
        // Le dossier racine des pieces (requests/, candidatures/) est conserve.
    }
}
