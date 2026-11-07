<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * =====================================================================
 *  Retirer les dossiers deposes pendant les TESTS des etudiants
 * =====================================================================
 *
 *  OBJET
 *    Les etudiants ont essaye l'application : ils ont cree des dossiers,
 *    televerse des pieces, declare des paiements. Cette migration efface
 *    CE QU'ILS ONT FAIT, et rien d'autre. Chaque etudiant retrouve, a sa
 *    prochaine connexion, le bouton « Creer mon dossier ».
 *
 *  EFFACE
 *    - les dossiers (student_requests), tous statuts confondus ;
 *    - en cascade : les pieces deposees, les etudes anterieures saisies,
 *      les derogations ;
 *    - les paiements rattaches a ces dossiers ;
 *    - les notifications envoyees au sujet de ces dossiers ;
 *    - les FICHIERS de ces pieces sur le disque, originaux et versions
 *      tamponnees. Un par un, d'apres le chemin enregistre : aucun
 *      balayage du stockage, aucun fichier etranger ne peut etre touche.
 *
 *  NE TOUCHE PAS
 *    - les COMPTES : etudiants, agents, administrateurs. Personne n'a a
 *      se reinscrire, les codes d'acces restent valables ;
 *    - les fiches du referentiel (anciens etudiants, bacheliers) et leur
 *      marque « inscrit » : le compte existe toujours, la marque reste
 *      donc juste ;
 *    - la CONFIGURATION : pieces attendues par grade, procedures,
 *      periodes, annees, facultes, parcours, parametres, roles ;
 *    - le cachet, les signatures, les gabarits, les sauvegardes ;
 *    - le journal d'audit ;
 *    - les candidatures publiques, sauf reglage contraire ci-dessous.
 *
 *  AVANT DE LANCER : SAUVEGARDEZ.
 *    mysqldump -u USER -p --single-transaction BASE > avant-$(date +%F).sql
 *    tar czf avant-$(date +%F).tgz -C SITE/storage/app/private requests candidatures
 *
 *  Tout se fait en une seule transaction : si une etape echoue, la base
 *  revient d'elle-meme a son etat initial. Les fichiers ne sont effaces
 *  qu'APRES la validation de la transaction — jamais l'inverse.
 * =====================================================================
 */
return new class extends Migration
{
    /*
    |--------------------------------------------------------------------
    |  REGLAGES — a relire avant de lancer
    |--------------------------------------------------------------------
    */

    /**
     * Ne retirer que les dossiers crees AVANT ce moment.
     *
     *   null                    tous les dossiers (cas normal : la
     *                           periode de test est terminee et aucun
     *                           dossier reel n'a encore ete depose) ;
     *   '2026-11-03 08:00:00'   seulement ceux d'avant cette date, si de
     *                           vrais dossiers sont deja arrives. Heure
     *                           du SERVEUR, en UTC (le Togo est en UTC).
     */
    private const LIMITE = null;

    /**
     * Retirer aussi les candidatures (admissions deposees sans compte,
     * via le portail public) creees avant la meme limite.
     *
     * false par defaut : c'est une autre procedure, qui n'a pas de
     * rapport avec le bouton « Creer mon dossier ».
     */
    private const AVEC_LES_CANDIDATURES = false;

    public function up(): void
    {
        $limite = self::LIMITE;

        /* ----------------------------------------------------------------
         |  1. Les dossiers vises, figes une fois pour toutes.
         |     Tout le reste decoule de cette liste : la migration ne peut
         |     pas s'etendre a des lignes apparues entre-temps.
         ---------------------------------------------------------------- */
        $dossiers = DB::table('student_requests')
            ->when($limite, fn ($q) => $q->where('created_at', '<', $limite))
            ->pluck('id');

        if ($dossiers->isEmpty() && !self::AVEC_LES_CANDIDATURES) {
            $this->dire("  Aucun dossier a retirer : rien a faire.\n");
            return;
        }

        /* ----------------------------------------------------------------
         |  2. Les chemins des fichiers, releves AVANT la suppression des
         |     lignes — apres, plus rien ne dirait ou ils se trouvent.
         ---------------------------------------------------------------- */
        $fichiers = [];
        foreach ($dossiers->chunk(500) as $lot) {
            $lignes = DB::table('request_files')
                ->whereIn('student_request_id', $lot)
                ->get(['file_path', 'stamped_file_path']);

            foreach ($lignes as $f) {
                foreach ([$f->file_path, $f->stamped_file_path] as $chemin) {
                    if ($chemin) {
                        $fichiers[] = $chemin;
                    }
                }
            }
        }

        if (self::AVEC_LES_CANDIDATURES) {
            $candidatures = DB::table('candidatures')
                ->when($limite, fn ($q) => $q->where('created_at', '<', $limite))
                ->pluck('id');

            foreach ($candidatures->chunk(500) as $lot) {
                $lignes = DB::table('candidature_files')
                    ->whereIn('candidature_id', $lot)
                    ->whereNotNull('file_path')
                    ->get(['file_path']);

                foreach ($lignes as $f) {
                    $fichiers[] = $f->file_path;
                }
            }
        } else {
            $candidatures = collect();
        }

        $compteur = [
            'dossiers'     => $dossiers->count(),
            'pieces'       => 0,
            'paiements'    => 0,
            'candidatures' => $candidatures->count(),
            'etudiants'    => DB::table('student_requests')
                ->whereIn('id', $dossiers)->distinct()->count('student_id'),
        ];

        /* ----------------------------------------------------------------
         |  3. La suppression en base. Tout ou rien.
         ---------------------------------------------------------------- */
        DB::transaction(function () use ($dossiers, $candidatures, &$compteur) {
            foreach ($dossiers->chunk(500) as $lot) {
                $compteur['pieces'] += DB::table('request_files')
                    ->whereIn('student_request_id', $lot)->count();

                // Les paiements pointent leur objet par reference
                // polymorphe, sans cle etrangere : la cascade ne les
                // atteindrait pas, ils resteraient orphelins.
                $compteur['paiements'] += DB::table('paiements')
                    ->where('payable_type', \App\Models\StudentRequest::class)
                    ->whereIn('payable_id', $lot)
                    ->delete();

                // Meme raison pour le registre des envois.
                DB::table('notifications_envoyees')
                    ->where('concerne_type', \App\Models\StudentRequest::class)
                    ->whereIn('concerne_id', $lot)
                    ->delete();

                // Les pieces, les etudes anterieures et les derogations
                // partent en cascade avec le dossier.
                DB::table('student_requests')->whereIn('id', $lot)->delete();
            }

            foreach ($candidatures->chunk(500) as $lot) {
                $compteur['paiements'] += DB::table('paiements')
                    ->where('payable_type', \App\Models\Candidature::class)
                    ->whereIn('payable_id', $lot)
                    ->delete();

                DB::table('notifications_envoyees')
                    ->where('concerne_type', \App\Models\Candidature::class)
                    ->whereIn('concerne_id', $lot)
                    ->delete();

                // Cascade : voeux, sections et pieces de la candidature.
                DB::table('candidatures')->whereIn('id', $lot)->delete();
            }
        });

        /* ----------------------------------------------------------------
         |  4. Les fichiers, seulement maintenant que la base est validee.
         |     L'inverse laisserait des lignes pointant vers le vide si la
         |     transaction echouait.
         ---------------------------------------------------------------- */
        $effaces = 0;
        $disque  = Storage::disk('local');

        foreach ($fichiers as $chemin) {
            if ($disque->exists($chemin) && $disque->delete($chemin)) {
                $effaces++;
            }
        }

        $this->retirerLesDossiersVides();

        /* ----------------------------------------------------------------
         |  5. Compte rendu.
         ---------------------------------------------------------------- */
        $this->dire("\n  Dossiers de test retires\n");
        $this->dire("  ------------------------------------------\n");
        $this->dire("  dossiers supprimes      : {$compteur['dossiers']}\n");
        $this->dire("  etudiants concernes     : {$compteur['etudiants']}  (leurs comptes sont conserves)\n");
        $this->dire("  pieces deposees         : {$compteur['pieces']}\n");
        $this->dire("  paiements declares      : {$compteur['paiements']}\n");
        if (self::AVEC_LES_CANDIDATURES) {
            $this->dire("  candidatures            : {$compteur['candidatures']}\n");
        }
        $this->dire('  fichiers effaces        : ' . $effaces . ' sur ' . count($fichiers) . "\n");
        $this->dire("  ------------------------------------------\n");
        $this->dire("  Chaque etudiant revoit « Creer mon dossier ».\n\n");
    }

    /** Compte rendu a l'ecran, mais jamais au milieu des tests. */
    private function dire(string $texte): void
    {
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            echo $texte;
        }
    }

    /**
     * Retire les sous-dossiers devenus vides sous requests/ et
     * candidatures/. Les deux dossiers racines sont conserves :
     * l'application s'attend a les trouver.
     */
    private function retirerLesDossiersVides(): void
    {
        $disque = Storage::disk('local');

        foreach (['requests', 'candidatures'] as $racine) {
            if (!$disque->exists($racine)) {
                continue;
            }

            foreach ($disque->directories($racine) as $repertoire) {
                if (!$disque->files($repertoire) && !$disque->directories($repertoire)) {
                    $disque->deleteDirectory($repertoire);
                }
            }
        }
    }

    /**
     * Rien a defaire : les dossiers supprimes ne peuvent pas etre
     * reconstruits. Le retour arriere se fait par la sauvegarde prise
     * avant le lancement (voir l'en-tete de ce fichier).
     */
    public function down(): void
    {
        $this->dire("  Aucun retour arriere possible. Restaurez votre sauvegarde.\n");
    }
};
