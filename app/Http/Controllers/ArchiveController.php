<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Faculte;
use App\Models\Parcours;
use App\Models\RequestFile;
use App\Models\StudentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Archive numérique des dossiers de dépôt VALIDÉS, par année académique.
 * Consultation + téléchargement : fiche par fiche, dossier complet d'un
 * étudiant, sélection d'étudiants, ou lot filtré (parcours / semestres / grade).
 */
class ArchiveController extends Controller
{
    /**
     * Dossiers archivables = dépôts validés, TOUTES ANNÉES CONFONDUES par
     * défaut (l'archive est cumulative : chaque nouvelle année s'ajoute à
     * l'existant). Filtre « annee_id » optionnel pour ne voir qu'une année.
     */
    private function base(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $q = StudentRequest::query()
            ->where('category', 'depot')
            ->where('status', 'validé');

        // Une année précise uniquement si demandée (défaut = cumul de toutes)
        if ($request->filled('annee_id') && $request->annee_id !== 'all') {
            $q->where('annee_academique_id', (int) $request->annee_id);
        }
        $request->user()->applyPerimeterFilter($q);

        if ($request->filled('faculte_id')) {
            $fid = $request->faculte_id;
            $q->whereHas('parcours.departement', fn ($d) => $d->where('faculte_id', $fid));
        }
        if ($request->filled('parcours_id')) {
            $q->where('parcours_id', $request->parcours_id);
        }
        if ($request->filled('grade')) {
            $g = $request->grade;
            $q->whereHas('parcours', fn ($p) => $p->where('grade', $g));
        }
        // Plusieurs semestres possibles
        $sems = array_filter((array) $request->input('semestres', []), fn ($s) => filled($s));
        if ($sems) {
            $q->whereIn('semestre', $sems);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($qq) use ($s) {
                $qq->whereHas('student', fn ($sq) => $sq->where('nom', 'LIKE', "%{$s}%")
                        ->orWhere('prenom', 'LIKE', "%{$s}%"))
                   ->orWhereHas('student.user', fn ($sq) => $sq->where('numero_identifiant', 'LIKE', "%{$s}%"));
            });
        }

        return $q;
    }

    public function index(Request $request)
    {
        $annee = AnneeAcademique::context();

        $dossiers = $this->base($request)
            ->with(['student.user', 'student.bachelier', 'student.ancienEtudiant',
                    'parcours.departement.faculte', 'files', 'validator', 'anneeAcademique',
                    // Un dossier validé peut l'avoir été avec une pièce encore
                    // due : l'archive doit le dire, sinon elle ment.
                    'derogations.requestType'])
            ->orderByDesc('annee_academique_id')
            ->orderBy('parcours_id')->orderBy('semestre')
            ->paginate(\App\Models\Setting::perPage(25))->withQueryString();

        $facultes = Faculte::where('is_active', true)->orderBy('nom')->get();
        $parcoursList = Parcours::where('is_active', true)
            ->when($request->filled('faculte_id'), fn ($q) => $q->whereHas('departement',
                fn ($d) => $d->where('faculte_id', $request->faculte_id)))
            ->orderBy('nom')->get();
        $grades = Parcours::$grades;
        $total = $this->base($request)->count();

        // Années disponibles dans l'archive (pour le filtre) + cumul par année
        $annees = AnneeAcademique::orderByDesc('libelle')->get();
        $parAnnee = StudentRequest::where('category', 'depot')->where('status', 'validé')
            ->selectRaw('annee_academique_id, COUNT(*) as n')
            ->groupBy('annee_academique_id')->pluck('n', 'annee_academique_id');

        return view('admin.archives.index', compact(
            'dossiers', 'annee', 'facultes', 'parcoursList', 'grades', 'total', 'annees', 'parAnnee'
        ));
    }

    /** Télécharger une fiche individuelle (version tamponnée si disponible). */
    public function downloadFiche(Request $request, RequestFile $file)
    {
        $sr = $file->studentRequest;
        abort_unless($sr && $sr->status === 'validé', 403, 'Dossier non archivé.');
        abort_unless($request->user()->coversRequest($sr), 403, 'Hors de votre périmètre.');

        $path = $file->stamped_file_path && Storage::disk('local')->exists($file->stamped_file_path)
            ? $file->stamped_file_path
            : $file->file_path;

        abort_unless(Storage::disk('local')->exists($path), 404, 'Fichier introuvable.');

        \App\Models\AuditLog::record('archive.fiche', $sr,
            "Fiche « {$file->original_filename} » téléchargée — "
                . ($sr->student->full_name ?? '—') . " (demande #{$sr->id})",
            ['etudiant' => $sr->student->full_name ?? null]);

        return Storage::disk('local')->download($path, $this->nomFichier($file));
    }

    /** Télécharger le dossier complet d'un étudiant (ZIP). */
    public function downloadDossier(Request $request, StudentRequest $studentRequest)
    {
        abort_unless($studentRequest->status === 'validé', 403, 'Dossier non archivé.');
        abort_unless($request->user()->coversRequest($studentRequest), 403, 'Hors de votre périmètre.');

        $studentRequest->load('student.user', 'files.requestType', 'parcours');

        \App\Models\AuditLog::record('archive.dossier', $studentRequest,
            'Dossier complet téléchargé (' . ($studentRequest->student->full_name ?? '—') . ')');

        $zipName = 'Dossier-' . $this->slugEtudiant($studentRequest) . '.zip';

        return $this->zipDossiers(collect([$studentRequest]), $zipName, false);
    }

    /** Télécharger un lot : sélection d'étudiants ou tout le résultat filtré. */
    public function downloadLot(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));

        $q = $this->base($request)->with('student.user', 'files.requestType', 'parcours', 'anneeAcademique');
        if ($ids) {
            $q->whereIn('student_requests.id', $ids);
        }
        $dossiers = $q->get();

        abort_if($dossiers->isEmpty(), 404, 'Aucun dossier à archiver pour cette sélection.');

        // Libellé du lot : l'année filtrée, sinon « cumul » (toutes années)
        $anneeFiltre = $request->filled('annee_id') && $request->annee_id !== 'all'
            ? AnneeAcademique::find((int) $request->annee_id) : null;

        \App\Models\AuditLog::record('archive.lot', null,
            "Lot d'archives téléchargé : {$dossiers->count()} dossier(s)",
            ['annee' => $anneeFiltre?->libelle ?? 'toutes', 'nb' => $dossiers->count(), 'selection' => (bool) $ids]);

        $zipName = 'Archive-' . ($anneeFiltre?->libelle ?? 'cumul') . '-' . now()->format('Ymd-His') . '.zip';

        return $this->zipDossiers($dossiers, $zipName, true);
    }

    /* ── Construction du ZIP ───────────────────────────────── */

    private function zipDossiers($dossiers, string $zipName, bool $parEtudiant)
    {
        if (!class_exists(\ZipArchive::class)) {
            abort(500, "L'extension PHP zip est requise pour l'archivage.");
        }

        $tmp = storage_path('app/tmp');
        if (!is_dir($tmp)) {
            mkdir($tmp, 0755, true);
        }
        $zipPath = $tmp . '/' . Str::random(16) . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, "Impossible de créer l'archive.");
        }

        $nb = 0;
        foreach ($dossiers as $sr) {
            // Archive cumulative : un dossier par année académique, puis par étudiant
            $dossier = $parEtudiant
                ? ($sr->anneeAcademique?->libelle ?? 'annee-inconnue') . '/' . $this->slugEtudiant($sr) . '/'
                : '';
            foreach ($sr->files as $f) {
                $path = $f->stamped_file_path && Storage::disk('local')->exists($f->stamped_file_path)
                    ? $f->stamped_file_path
                    : $f->file_path;
                if (!Storage::disk('local')->exists($path)) {
                    continue;
                }
                $zip->addFile(Storage::disk('local')->path($path), $dossier . $this->nomFichier($f));
                $nb++;
            }
        }

        // Un index lisible dans l'archive
        $zip->addFromString('_index.txt', $this->indexTexte($dossiers, $nb));
        $zip->close();

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    private function indexTexte($dossiers, int $nb): string
    {
        $anneesConcernees = $dossiers->map(fn ($sr) => $sr->anneeAcademique?->libelle)
            ->filter()->unique()->sort()->implode(', ');

        $l = ["Archive numérique — dossiers de dépôt validés (cumulative)"];
        $l[] = 'Année(s) : ' . ($anneesConcernees ?: '—');
        $l[] = 'Généré le : ' . now()->format('d/m/Y à H:i');
        $l[] = 'Dossiers : ' . $dossiers->count() . ' — Fichiers : ' . $nb;
        $l[] = str_repeat('-', 60);
        foreach ($dossiers as $sr) {
            $l[] = sprintf(
                '%s | %s | %s %s | %s | %s | %d pièce(s)',
                $sr->anneeAcademique?->libelle ?? '—',
                $sr->student?->numero_suivi ?: ($sr->student?->user?->numero_identifiant ?? '—'),
                $sr->student?->nom, $sr->student?->prenom,
                $sr->parcours?->nom ?? '—',
                \App\Models\Parcours::libelleNiveau($sr->semestre, $sr->parcours?->grade) ?? '—',
                $sr->files->count()
            );
        }

        return implode("\n", $l) . "\n";
    }

    private function nomFichier(RequestFile $f): string
    {
        $prefix = $f->is_stamped ? 'TAMPONNE_' : '';
        $label  = Str::slug($f->requestType->label ?? 'document');

        return $prefix . $label . '.' . pathinfo($f->original_filename, PATHINFO_EXTENSION);
    }

    private function slugEtudiant(StudentRequest $sr): string
    {
        $ref = $sr->student?->numero_suivi ?: ($sr->student?->user?->numero_identifiant ?? $sr->id);

        return $ref . '_' . Str::slug(($sr->student?->nom ?? '') . '-' . ($sr->student?->prenom ?? ''));
    }
}
