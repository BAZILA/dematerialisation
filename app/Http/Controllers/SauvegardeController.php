<?php

namespace App\Http\Controllers;

use App\Jobs\ExecuterSauvegarde;
use App\Services\SauvegardeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pilotage des sauvegardes : état, déclenchement manuel, téléchargement,
 * suppression. Réservé au super-admin (une archive contient TOUTES les
 * pièces déposées et un dump complet de la base).
 */
class SauvegardeController extends Controller
{
    public function __construct(private SauvegardeService $service)
    {
    }

    public function index()
    {
        $archives = $this->service->lister();

        // État de la file d'attente : sans worker, les travaux s'empilent sans fin
        $connexion = config('queue.default');
        $file = [
            'connexion' => $connexion,
            'sync'      => $connexion === 'sync',
            'en_attente' => $connexion === 'database' ? DB::table('jobs')->count() : 0,
            'echoues'    => \Illuminate\Support\Facades\Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->count() : 0,
        ];

        return view('admin.sauvegardes.index', [
            'archives'  => $archives,
            'service'   => $this->service,
            'espace'    => $this->service->espaceOccupe(),
            'auto'      => $this->service->automatiqueActive(),
            'heure'     => $this->service->heureAuto(),
            'retention' => $this->service->retentionJours(),
            'file'      => $file,
        ]);
    }

    /** Sauvegarde immédiate (mise en file si un worker tourne). */
    public function executer(Request $request)
    {
        $avecFichiers = !$request->boolean('sans_fichiers');

        ExecuterSauvegarde::dispatch($avecFichiers, 'manuelle — ' . $request->user()->staff_name);

        return back()->with('success', config('queue.default') === 'sync'
            ? 'Sauvegarde terminée.'
            : "Sauvegarde lancée en arrière-plan : l'archive apparaîtra dans la liste d'ici quelques instants.");
    }

    public function telecharger(string $nom)
    {
        $chemin = $this->chemin($nom);

        \App\Models\AuditLog::record('sauvegarde.telechargee', null,
            "Archive de sauvegarde « {$nom} » téléchargée");

        return response()->download($chemin, $nom);
    }

    public function supprimer(string $nom)
    {
        $chemin = $this->chemin($nom);
        @unlink($chemin);

        \App\Models\AuditLog::record('sauvegarde.supprimee', null,
            "Archive de sauvegarde « {$nom} » supprimée");

        return back()->with('success', "Archive « {$nom} » supprimée.");
    }

    /**
     * Résout un nom d'archive en chemin sûr : le nom vient de l'URL, il ne
     * doit jamais permettre de sortir du dossier des sauvegardes.
     */
    private function chemin(string $nom): string
    {
        abort_unless(preg_match('/^sauvegarde-[\w\-]+\.zip$/', $nom), 404);

        $chemin = $this->service->dossier() . DIRECTORY_SEPARATOR . $nom;
        abort_unless(is_file($chemin), 404, 'Archive introuvable.');

        return $chemin;
    }
}
