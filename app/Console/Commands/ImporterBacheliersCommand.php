<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Chargement en masse du fichier des bacheliers.
 *
 * L'écran d'import ne peut pas avaler ce fichier, pour trois raisons qui
 * ne se règlent pas en augmentant une limite :
 *
 *  - il lit le fichier entier en mémoire puis interroge la base trois à
 *    quatre fois PAR LIGNE : à 170 000 lignes, c'est plus d'un demi-million
 *    de requêtes dans une page web limitée à deux minutes ;
 *  - il attend des colonnes « sexe » et « serie_bac » là où le fichier
 *    porte « code_sexe » et « code_serie » : chaque ligne est rejetée ;
 *  - il dédoublonne sur le numéro de table, qui se répète d'une année
 *    à l'autre : les promotions s'écraseraient entre elles.
 *
 * Cette commande lit le fichier ligne à ligne, reconnaît les colonnes
 * par leur nom (peu importe leur ordre), et insère par paquets. Elle
 * tourne en ligne de commande, sans limite de temps.
 *
 * PAR DÉFAUT ELLE N'ÉCRIT RIEN : elle analyse et rend compte. Ajouter
 * --importer pour charger.
 */
class ImporterBacheliersCommand extends Command
{
    protected $signature = 'uk:importer-bacheliers
                            {fichier : Chemin du fichier CSV}
                            {--annee=* : Ne charger que cette année de bac (option répétable)}
                            {--id-est-le-suivi : La colonne « id » du fichier EST le numéro de suivi}
                            {--importer : Écrire en base (sans cette option : analyse à blanc)}';

    protected $description = 'Charge en masse le fichier CSV des bacheliers (à blanc par défaut)';

    /** Noms de colonnes acceptés, par champ. Le premier trouvé l'emporte. */
    private const COLONNES = [
        'id_source'      => ['id', 'id_source', 'identifiant'],
        'numero_suivi'   => ['numero_suivi', 'suivi'],
        'nom'            => ['nom'],
        'prenom'         => ['prenom', 'prenoms'],
        'sexe'           => ['code_sexe', 'sexe'],
        'date_naissance' => ['date_naissance'],
        'lieu_naissance' => ['lieu_naissance'],
        'mention_bac'    => ['code_mention_bac', 'mention_bac', 'mention'],
        'numero_table'   => ['numero_table', 'n_table'],
        'annee_bac'      => ['annee_bac', 'annee'],
        'serie_bac'      => ['code_serie', 'serie_bac', 'serie'],
        'numero_releve'  => ['numero_releve'],
        'academie'       => ['academie'],
        'telephone'      => ['telephone', 'tel'],
        'email'          => ['email', 'courriel'],
        'adresse'        => ['adresse'],
    ];

    private const OBLIGATOIRES = ['nom', 'prenom', 'sexe', 'date_naissance', 'lieu_naissance'];

    private const PAQUET = 500;

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');

        $chemin = $this->argument('fichier');
        if (!is_file($chemin) || !is_readable($chemin)) {
            $this->error("Fichier introuvable ou illisible : {$chemin}");

            return self::FAILURE;
        }

        $pourDeVrai = (bool) $this->option('importer');
        $annees     = array_map('strval', (array) $this->option('annee'));
        $idEstSuivi = (bool) $this->option('id-est-le-suivi');

        $flux = fopen($chemin, 'rb');
        $premiere = (string) fgets($flux);
        $separateur = $this->separateur($premiere);
        rewind($flux);

        $entete = fgetcsv($flux, 0, $separateur, '"', '');
        $entete = array_map(fn ($c) => mb_strtolower(trim($this->enUtf8((string) $c), " \t\"\u{FEFF}")), $entete ?: []);
        $position = $this->positions($entete);

        if ($manquantes = array_diff(self::OBLIGATOIRES, array_keys($position))) {
            fclose($flux);
            $this->error('Colonnes obligatoires absentes du fichier : ' . implode(', ', $manquantes));
            $this->line('Colonnes lues : ' . implode(', ', $entete));

            return self::FAILURE;
        }

        $this->line($pourDeVrai
            ? '<fg=red;options=bold>MODE IMPORT</> — les fiches vont être écrites en base.'
            : '<options=bold>MODE À BLANC</> — rien ne sera écrit. Relancez avec --importer.');
        $this->line('Fichier    : ' . $chemin . ' (' . round(filesize($chemin) / 1048576, 1) . ' Mo)');
        $this->line('Colonnes   : ' . implode(', ', array_map(
            fn ($champ, $i) => "{$entete[$i]} → {$champ}", array_keys($position), $position)));
        $this->line('N° de suivi : ' . ($idEstSuivi
            ? 'repris de la colonne « id »'
            : (isset($position['numero_suivi']) ? 'repris de la colonne du fichier' : 'laissé VIDE (voir --id-est-le-suivi)')));
        if ($annees) {
            $this->line('Années     : ' . implode(', ', $annees) . ' uniquement');
        }
        $this->newLine();

        /* ── Ce que la base contient déjà ──────────────────────────────
           Deux façons de reconnaître une fiche déjà là : son identifiant
           d'origine (un chargement précédent), ou son identité civile
           (une fiche saisie à la main, peut-être déjà dotée d'un compte :
           on la RATTACHE au fichier au lieu d'en créer une seconde). */
        $dejaParId = [];
        $dejaParIdentite = [];
        DB::table('bacheliers')
            ->select('id', 'id_source', 'nom', 'prenom', 'date_naissance', 'lieu_naissance', 'sexe')
            ->orderBy('id')->chunk(5000, function ($lot) use (&$dejaParId, &$dejaParIdentite) {
                foreach ($lot as $b) {
                    if ($b->id_source !== null) {
                        $dejaParId[$b->id_source] = true;
                    } else {
                        $dejaParIdentite[$this->cleIdentite($b->nom, $b->prenom,
                            substr((string) $b->date_naissance, 0, 10), $b->lieu_naissance, $b->sexe)] = $b->id;
                    }
                }
            });

        $rejets    = $this->ouvrirRapport($chemin, 'rejets', ['ligne', 'motif', ...$entete]);
        $aVerifier = $this->ouvrirRapport($chemin, 'a-verifier', ['ligne', 'motif', 'id', 'annee_bac', 'numero_table']);

        $c = ['lues' => 0, 'hors_annee' => 0, 'rejetees' => 0, 'deja' => 0, 'rattachees' => 0,
              'nouvelles' => 0, 'a_verifier' => 0];
        $vusId = [];
        $vuesIdentites = [];
        $paquet = [];
        $maintenant = now()->format('Y-m-d H:i:s');
        $anneeMax = (int) date('Y') + 1;
        $ligne = 1;

        $barre = $this->output->createProgressBar();
        $barre->setFormat(' %current% lignes lues — %message%');
        $barre->setMessage('…');

        while (($brut = fgetcsv($flux, 0, $separateur, '"', '')) !== false) {
            $ligne++;
            if ($brut === [null] || $brut === []) {
                continue;
            }
            $c['lues']++;
            if ($c['lues'] % 2000 === 0) {
                $barre->setMessage("{$c['nouvelles']} nouvelles, {$c['rejetees']} rejetées");
                $barre->setProgress($c['lues']);
            }

            $v = [];
            foreach ($position as $champ => $i) {
                $v[$champ] = $this->propre($brut[$i] ?? null);
            }

            $annee = $v['annee_bac'] ?? null;
            if ($annees && !in_array((string) $annee, $annees, true)) {
                $c['hors_annee']++;
                continue;
            }

            /* ── Lignes inutilisables : écartées, et consignées ── */
            $v['sexe'] = strtoupper((string) ($v['sexe'] ?? ''));
            $date = $this->date($v['date_naissance'] ?? null);

            $motif = match (true) {
                blank($v['nom'] ?? null)            => 'nom vide',
                blank($v['prenom'] ?? null)         => 'prénom vide',
                blank($v['lieu_naissance'] ?? null) => 'lieu de naissance vide',
                !in_array($v['sexe'], ['M', 'F'], true) => 'sexe inconnu',
                $date === null                      => 'date de naissance illisible',
                default                             => null,
            };

            $idSource = $v['id_source'] ?? null;
            if ($motif === null && $idSource !== null && isset($vusId[$idSource])) {
                $motif = 'identifiant en double dans le fichier';
            }
            if ($motif !== null) {
                $c['rejetees']++;
                fputcsv($rejets, [$ligne, $motif, ...array_map(fn ($x) => $this->enUtf8((string) $x), $brut)], ';', '"', '');
                continue;
            }
            if ($idSource !== null) {
                $vusId[$idSource] = true;
            }

            /* ── Anomalies qui n'empêchent pas de charger ── */
            if ($annee !== null && !(preg_match('/^\d{4}$/', $annee) && $annee >= 1950 && $annee <= $anneeMax)) {
                $this->signaler($aVerifier, $ligne, "année de bac invraisemblable ({$annee}) : laissée vide", $v, $c);
                $annee = null;
            }
            $naissance = (int) substr($date, 0, 4);
            if ($naissance < 1930 || $naissance > (int) date('Y') - 10) {
                $this->signaler($aVerifier, $ligne,
                    "date de naissance invraisemblable ({$date}) : l'intéressé ne pourra pas prouver son identité", $v, $c);
            }

            $identite = $this->cleIdentite($v['nom'], $v['prenom'], $date, $v['lieu_naissance'], $v['sexe']);
            if (isset($vuesIdentites[$identite])) {
                $this->signaler($aVerifier, $ligne,
                    'même nom, prénom, date, lieu et sexe que la ligne ' . $vuesIdentites[$identite]
                    . ' : ces deux fiches ouvriront le même compte', $v, $c);
            } else {
                $vuesIdentites[$identite] = $ligne;
            }

            /* ── Déjà en base ? ── */
            if ($idSource !== null && isset($dejaParId[$idSource])) {
                $c['deja']++;
                continue;
            }

            $suivi = $idEstSuivi ? $idSource : ($v['numero_suivi'] ?? null);

            if (isset($dejaParIdentite[$identite])) {
                // Fiche saisie à la main avant ce chargement : on la rattache,
                // sans toucher à ce qui y est déjà écrit.
                $c['rattachees']++;
                if ($pourDeVrai && $idSource !== null) {
                    $maj = ['id_source' => $idSource, 'updated_at' => $maintenant];
                    if ($suivi !== null) {
                        $maj['numero_suivi'] = DB::raw('COALESCE(numero_suivi, ' . DB::getPdo()->quote($suivi) . ')');
                    }
                    DB::table('bacheliers')->where('id', $dejaParIdentite[$identite])
                        ->whereNull('id_source')->update($maj);
                }
                unset($dejaParIdentite[$identite]);
                continue;
            }

            $c['nouvelles']++;
            if (!$pourDeVrai) {
                continue;
            }

            $paquet[] = [
                'id_source'      => $idSource,
                'numero_suivi'   => $suivi,
                'nom'            => mb_substr($v['nom'], 0, 255),
                'prenom'         => mb_substr($v['prenom'], 0, 255),
                'date_naissance' => $date,
                'lieu_naissance' => mb_substr($v['lieu_naissance'], 0, 255),
                'sexe'           => $v['sexe'],
                'serie_bac'      => $this->borne($v['serie_bac'] ?? null, 255),
                'mention_bac'    => $this->borne($v['mention_bac'] ?? null, 10),
                'annee_bac'      => $annee,
                'numero_releve'  => $this->borne($v['numero_releve'] ?? null, 255),
                'numero_table'   => $this->borne($v['numero_table'] ?? null, 255),
                'academie'       => $this->borne($v['academie'] ?? null, 255),
                'telephone'      => $this->borne($v['telephone'] ?? null, 255),
                'email'          => $this->borne($v['email'] ?? null, 255),
                'adresse'        => $this->borne($v['adresse'] ?? null, 255),
                'is_registered'  => 0,
                'created_at'     => $maintenant,
                'updated_at'     => $maintenant,
            ];

            if (count($paquet) >= self::PAQUET) {
                DB::table('bacheliers')->insert($paquet);
                $paquet = [];
            }
        }

        if ($paquet) {
            DB::table('bacheliers')->insert($paquet);
        }
        fclose($flux);
        fclose($rejets);
        fclose($aVerifier);
        $barre->setProgress($c['lues']);
        $barre->finish();
        $this->newLine(2);

        /* ── Compte rendu ── */
        $this->table(['', 'Lignes'], [
            ['Lues dans le fichier', $c['lues']],
            ['Hors des années demandées', $c['hors_annee']],
            ['Rejetées (voir le fichier des rejets)', $c['rejetees']],
            ['Déjà en base (chargement précédent)', $c['deja']],
            ['Rattachées à une fiche existante', $c['rattachees']],
            [$pourDeVrai ? 'CHARGÉES' : 'À charger', $c['nouvelles']],
            ['Signalements à vérifier (chargées quand même)', $c['a_verifier']],
        ]);

        $this->line('Rejets      : ' . $this->cheminRapport($chemin, 'rejets'));
        $this->line('À vérifier  : ' . $this->cheminRapport($chemin, 'a-verifier'));
        $this->warn('Ces deux fichiers contiennent des données personnelles : supprimez-les après lecture.');

        if ($pourDeVrai) {
            $this->info('Bacheliers en base : ' . DB::table('bacheliers')->count());

            AuditLog::record('identites.import_masse', null,
                "Chargement en masse des bacheliers : {$c['nouvelles']} fiches créées, "
                . "{$c['rattachees']} rattachées, {$c['rejetees']} rejetées, {$c['deja']} déjà présentes"
                . ($annees ? ' — années ' . implode(', ', $annees) : ''),
                $c + ['fichier' => basename($chemin), 'id_est_le_suivi' => $idEstSuivi]);
        } else {
            $this->newLine();
            $this->line('Rien n\'a été écrit. Pour charger : relancez la même commande avec <options=bold>--importer</>');
        }

        return self::SUCCESS;
    }

    /* ── Lecture ───────────────────────────────────────── */

    private function separateur(string $ligne): string
    {
        $compte = array_map(fn ($s) => substr_count($ligne, $s), [';' => ';', ',' => ',', "\t" => "\t", '|' => '|']);
        arsort($compte);

        return (string) array_key_first($compte);
    }

    /** @return array<string,int>  champ → position de sa colonne dans le fichier */
    private function positions(array $entete): array
    {
        $position = [];
        foreach (self::COLONNES as $champ => $noms) {
            foreach ($noms as $nom) {
                $i = array_search($nom, $entete, true);
                if ($i !== false) {
                    $position[$champ] = $i;
                    break;
                }
            }
        }

        return $position;
    }

    /** Les exports de l'autre système sont en Windows-1252, pas en UTF-8. */
    private function enUtf8(string $v): string
    {
        return mb_check_encoding($v, 'UTF-8') ? $v : mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
    }

    /** Valeur nettoyée : encodage, espaces, et le « NULL » écrit en toutes lettres. */
    private function propre(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim(preg_replace('/\s+/u', ' ', $this->enUtf8($v)) ?? '');

        return ($v === '' || strtoupper($v) === 'NULL') ? null : $v;
    }

    private function borne(?string $v, int $max): ?string
    {
        return $v === null ? null : mb_substr($v, 0, $max);
    }

    private function date(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $d = \DateTime::createFromFormat('!' . $format, $v);
            if ($d && $d->format($format) === $v) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    /** L'identité telle que la connexion la compare : sans casse. */
    private function cleIdentite(?string $nom, ?string $prenom, ?string $date, ?string $lieu, ?string $sexe): string
    {
        return mb_strtolower(trim((string) $nom)) . '|' . mb_strtolower(trim((string) $prenom)) . '|'
            . $date . '|' . mb_strtolower(trim((string) $lieu)) . '|' . strtoupper((string) $sexe);
    }

    /* ── Rapports ──────────────────────────────────────── */

    private function cheminRapport(string $chemin, string $suffixe): string
    {
        return preg_replace('/\.[^.\/\\\\]+$/', '', $chemin) . ".{$suffixe}.csv";
    }

    /** @return resource */
    private function ouvrirRapport(string $chemin, string $suffixe, array $entete)
    {
        $flux = fopen($this->cheminRapport($chemin, $suffixe), 'wb');
        fwrite($flux, "\u{FEFF}");            // pour qu'Excel lise les accents
        fputcsv($flux, $entete, ';', '"', '');

        return $flux;
    }

    private function signaler($flux, int $ligne, string $motif, array $v, array &$c): void
    {
        $c['a_verifier']++;
        fputcsv($flux, [$ligne, $motif, $v['id_source'] ?? '', $v['annee_bac'] ?? '', $v['numero_table'] ?? ''], ';', '"', '');
    }
}
