<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Sauvegarde complète de la plateforme : base de données + pièces déposées.
 *
 * Une sauvegarde = une archive ZIP horodatée dans storage/app/sauvegardes,
 * contenant le dump SQL et l'arborescence des fichiers privés. Les archives
 * plus vieilles que la rétention configurée sont purgées automatiquement.
 */
class SauvegardeService
{
    /** Dossier des archives (hors du disque public : jamais servi par le web). */
    /**
     * Dossier des archives.
     *
     * Le chemin passe par la configuration : sans cela, tout code qui
     * appelle ce service — un test, un script — écrit dans le dossier
     * réel et déclenche la purge de rétention sur de vraies
     * sauvegardes. Un hébergement peut aussi vouloir les ranger hors
     * de l'arborescence web.
     */
    public function dossier(): string
    {
        $dir = config('sauvegarde.dossier') ?: storage_path('app/sauvegardes');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /** Rétention en jours (paramétrable, bornée pour éviter les réglages absurdes). */
    public function retentionJours(): int
    {
        return max(1, min(365, (int) Setting::get('sauvegarde.retention_jours', 14)));
    }

    /** La sauvegarde automatique quotidienne est-elle active ? */
    public function automatiqueActive(): bool
    {
        return (bool) Setting::get('sauvegarde.auto', true);
    }

    /** Heure de la sauvegarde automatique, au format HH:MM. */
    public function heureAuto(): string
    {
        $h = (string) Setting::get('sauvegarde.heure', '02:00');

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h) ? $h : '02:00';
    }

    /**
     * Exécute une sauvegarde.
     *
     * @param  bool  $avecFichiers  inclure les pièces déposées (volumineux)
     * @return array{fichier:string, taille:int, base:bool, fichiers:int, duree:float, avertissements:array}
     */
    public function executer(bool $avecFichiers = true, ?string $declencheur = null): array
    {
        $t0 = microtime(true);
        $avertissements = [];

        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException("L'extension PHP zip est requise pour les sauvegardes.");
        }

        $horodatage = now()->format('Ymd-His');
        $nomArchive = 'sauvegarde-' . $horodatage . ($avecFichiers ? '' : '-base-seule') . '.zip';
        $chemin     = $this->dossier() . DIRECTORY_SEPARATOR . $nomArchive;

        $zip = new \ZipArchive();
        if ($zip->open($chemin, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Impossible de créer l'archive de sauvegarde : {$chemin}");
        }

        // ── 1. Base de données ──
        $dumpOk  = false;
        $dumpTmp = null;
        try {
            $dumpTmp = $this->dumpBase();
            $zip->addFile($dumpTmp, 'base/' . config('database.connections.mysql.database') . '.sql');
            $dumpOk = true;
        } catch (\Throwable $e) {
            $avertissements[] = 'Base de données non sauvegardée : ' . $e->getMessage();
            Log::error('Sauvegarde — dump SQL échoué : ' . $e->getMessage());
        }

        // ── 2. Pièces déposées (dépôts, candidatures, cachets, signatures) ──
        $nbFichiers = 0;
        if ($avecFichiers) {
            $racine = storage_path('app');
            foreach ($this->fichiersASauvegarder($racine) as $abs) {
                $relatif = ltrim(str_replace('\\', '/', substr($abs, strlen($racine))), '/');
                if ($zip->addFile($abs, 'fichiers/' . $relatif)) {
                    $nbFichiers++;
                }
            }
        }

        $zip->addFromString('MANIFESTE.txt', $this->manifeste($horodatage, $dumpOk, $nbFichiers, $declencheur, $avertissements));
        $zip->close();

        /* Une archive sans base ressemble en tout point à une sauvegarde
           valide : même nom, même liste, taille plausible. On la renomme
           pour que le manque saute aux yeux partout — dans la liste, dans
           le dossier, et jusque dans le fichier téléchargé. */
        if (!$dumpOk) {
            $nomSansBase = str_replace('.zip', '-SANS-BASE.zip', $nomArchive);
            $cheminSansBase = $this->dossier() . DIRECTORY_SEPARATOR . $nomSansBase;
            if (@rename($chemin, $cheminSansBase)) {
                $nomArchive = $nomSansBase;
                $chemin     = $cheminSansBase;
            }
        }

        if ($dumpTmp && is_file($dumpTmp)) {
            @unlink($dumpTmp);
        }

        $taille = is_file($chemin) ? filesize($chemin) : 0;
        $duree  = round(microtime(true) - $t0, 2);

        $purgees = $this->purger();

        AuditLog::record('sauvegarde.executee', null,
            "Sauvegarde « {$nomArchive} » créée — " . $this->formaterTaille($taille)
                . ($dumpOk ? ', base incluse' : ', BASE ABSENTE')
                . ", {$nbFichiers} fichier(s)"
                . ($purgees ? ", {$purgees} ancienne(s) purgée(s)" : ''),
            ['fichier' => $nomArchive, 'taille' => $taille, 'base' => $dumpOk,
             'fichiers' => $nbFichiers, 'duree' => $duree, 'declencheur' => $declencheur]);

        return [
            'fichier'        => $nomArchive,
            'taille'         => $taille,
            'base'           => $dumpOk,
            'fichiers'       => $nbFichiers,
            'duree'          => $duree,
            'avertissements' => $avertissements,
        ];
    }

    /** Fichiers à embarquer : tout storage/app SAUF les sauvegardes et le temporaire. */
    private function fichiersASauvegarder(string $racine): \Generator
    {
        if (!is_dir($racine)) {
            return;
        }
        $exclus = ['sauvegardes', 'tmp', 'framework', 'livewire-tmp'];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $item) {
            $rel = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($racine))), '/');
            $premier = explode('/', $rel)[0] ?? '';
            if (in_array($premier, $exclus, true)) {
                continue;
            }
            if ($item->isFile()) {
                yield $item->getPathname();
            }
        }
    }

    /**
     * Dump SQL via mysqldump. Le mot de passe passe par une variable
     * d'environnement, jamais par la ligne de commande (visible en
     * liste des processus).
     */
    private function dumpBase(): string
    {
        $cfg = config('database.connections.mysql');
        $bin = $this->cheminMysqldump();

        if (!$bin) {
            throw new \RuntimeException(
                "mysqldump introuvable. Renseignez MYSQLDUMP_PATH dans le fichier .env "
                . "(ex. C:\\xampp\\mysql\\bin\\mysqldump.exe)."
            );
        }

        $tmp = tempnam(sys_get_temp_dir(), 'dump_') . '.sql';

        $args = [
            $bin,
            '--host=' . ($cfg['host'] ?? '127.0.0.1'),
            '--port=' . ($cfg['port'] ?? 3306),
            '--user=' . ($cfg['username'] ?? 'root'),
            '--default-character-set=utf8mb4',
            '--single-transaction',
            '--routines',
            '--events',
            '--result-file=' . $tmp,
            $cfg['database'],
        ];

        // mysqldump lit MYSQL_PWD : le mot de passe n'apparaît pas dans la
        // ligne de commande, donc pas dans la liste des processus.
        $env = ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')];

        // Lancé depuis le navigateur, Apache ne transmet qu'un environnement
        // réduit. Sous Windows, mysqldump initialise Winsock, qui a besoin de
        // SystemRoot pour charger ses DLL : sans lui la création du socket
        // échoue (« Can't create TCP/IP socket (10106) ») et la base n'est
        // pas sauvegardée. On réinjecte donc explicitement le strict
        // nécessaire, ici comme sous Linux.
        foreach (['SystemRoot', 'SystemDrive', 'WINDIR', 'COMSPEC', 'PATH', 'TEMP', 'TMP'] as $cle) {
            $valeur = getenv($cle);
            if ($valeur !== false && $valeur !== '') {
                $env[$cle] = $valeur;
            }
        }

        $process = new \Symfony\Component\Process\Process($args, null, $env, null, 900);

        $process->run();

        if (!$process->isSuccessful() || !is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            $err = trim($process->getErrorOutput()) ?: 'code de sortie ' . $process->getExitCode();
            throw new \RuntimeException("mysqldump a échoué : {$err}");
        }

        return $tmp;
    }

    /** Localise mysqldump : .env, puis les emplacements habituels, puis le PATH. */
    private function cheminMysqldump(): ?string
    {
        $candidats = array_filter([
            env('MYSQLDUMP_PATH'),
            'C:/xampp/mysql/bin/mysqldump.exe',
            'C:/wamp64/bin/mysql/mysql8.0.31/bin/mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ]);

        foreach ($candidats as $c) {
            if (is_file($c)) {
                return $c;
            }
        }

        // Dernier recours : le binaire est peut-être dans le PATH
        $exec = (new \Symfony\Component\Process\ExecutableFinder())->find('mysqldump');

        return $exec ?: null;
    }

    /** Supprime les archives au-delà de la rétention. Renvoie le nombre purgé. */
    public function purger(): int
    {
        $limite = now()->subDays($this->retentionJours())->getTimestamp();
        $n = 0;

        foreach ($this->lister() as $a) {
            if ($a['date']->getTimestamp() < $limite) {
                @unlink($a['chemin']);
                $n++;
            }
        }

        return $n;
    }

    /** Archives présentes, de la plus récente à la plus ancienne. */
    public function lister(): array
    {
        $out = [];
        foreach (glob($this->dossier() . DIRECTORY_SEPARATOR . 'sauvegarde-*.zip') ?: [] as $f) {
            $out[] = [
                'nom'     => basename($f),
                'chemin'  => $f,
                'taille'  => filesize($f) ?: 0,
                'date'    => \Carbon\Carbon::createFromTimestamp(filemtime($f)),
                // Restaurer une archive sans base ne rétablit rien :
                // l'écran doit pouvoir la signaler clairement.
                'base'    => !str_contains(basename($f), '-SANS-BASE'),
            ];
        }
        usort($out, fn ($a, $b) => $b['date']->getTimestamp() <=> $a['date']->getTimestamp());

        return $out;
    }

    /** Espace total occupé par les archives. */
    public function espaceOccupe(): int
    {
        return array_sum(array_column($this->lister(), 'taille'));
    }

    public function formaterTaille(int $octets): string
    {
        $u = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $p = $octets > 0 ? floor(log($octets) / log(1024)) : 0;
        $p = (int) min($p, count($u) - 1);

        return round($octets / (1024 ** $p), $p ? 2 : 0) . ' ' . $u[$p];
    }

    private function manifeste(string $horodatage, bool $dumpOk, int $nbFichiers, ?string $declencheur, array $avertissements): string
    {
        $l = ['Sauvegarde — Plateforme de dématérialisation, Université de Kara'];
        $l[] = 'Générée le : ' . now()->format('d/m/Y à H:i:s') . " (horodatage {$horodatage})";
        $l[] = 'Déclencheur : ' . ($declencheur ?: 'inconnu');
        $l[] = 'Base de données : ' . ($dumpOk ? 'incluse (base/*.sql)' : 'ABSENTE — voir avertissements');
        $l[] = 'Pièces déposées : ' . $nbFichiers . ' fichier(s) (fichiers/*)';
        if ($avertissements) {
            $l[] = '';
            $l[] = 'AVERTISSEMENTS :';
            foreach ($avertissements as $a) {
                $l[] = '  - ' . $a;
            }
        }
        $l[] = '';
        $l[] = 'RESTAURATION';
        $l[] = '  1. Décompresser cette archive.';
        $l[] = '  2. Base : mysql -u root -p <nom_base> < base/<nom_base>.sql';
        $l[] = '  3. Fichiers : recopier le contenu de fichiers/ dans storage/app/ de la plateforme.';
        $l[] = '  4. Vider les caches : php artisan optimize:clear';

        return implode("\n", $l) . "\n";
    }
}
