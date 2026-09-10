<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Candidature;
use App\Models\Faculte;
use App\Models\Parcours;
use App\Models\RequestFile;
use App\Models\RequestType;
use App\Models\StudentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tableau de bord statistique : demandes, dépôts, candidatures.
 * Filtres combinables (année, période, procédure, statut, faculté,
 * département, parcours, grade, niveau, sexe, type d'étudiant) et
 * EXPORTS en un clic : Excel (synthèse + détail), CSV (détail), PDF (synthèse).
 * Toujours borné au périmètre de l'agent.
 */
class StatistiqueController extends Controller
{
    /** Libellés des statuts (partagés index / exports). */
    public static array $statutsLabels = [
        'brouillon' => 'Brouillon', 'soumis' => 'En attente', 'en_correction' => 'À corriger',
        'validé' => 'Validé', 'rejeté' => 'Rejeté',
    ];

    /** Requête de base (année + périmètre + TOUS les filtres), neuve à chaque appel. */
    private function base(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $q = StudentRequest::query();

        // Année : filtrable (défaut = année de contexte ; « all » = toutes)
        $anneeId = $request->input('annee_id');
        if ($anneeId === 'all') {
            // pas de filtre d'année (cumul toutes années)
        } elseif ($anneeId) {
            $q->where('annee_academique_id', (int) $anneeId);
        } elseif ($annee = AnneeAcademique::context()) {
            $q->where('annee_academique_id', $annee->id);
        }

        $request->user()->applyPerimeterFilter($q);

        if ($request->filled('category')) {
            $q->where('category', $request->category);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('faculte_id')) {
            $fid = $request->faculte_id;
            $q->whereHas('parcours.departement', fn ($d) => $d->where('faculte_id', $fid));
        }
        if ($request->filled('departement_id')) {
            $did = $request->departement_id;
            $q->whereHas('parcours', fn ($p) => $p->where('departement_id', $did));
        }
        if ($request->filled('parcours_id')) {
            $q->where('parcours_id', $request->parcours_id);
        }
        if ($request->filled('grade')) {
            $g = $request->grade;
            $q->whereHas('parcours', fn ($p) => $p->where('grade', $g));
        }
        if ($request->filled('semestre')) {
            $q->where('semestre', $request->semestre);
        }
        if ($request->filled('type_etudiant')) {
            $t = $request->type_etudiant;
            $q->whereHas('student.user', fn ($u) => $u->where('type_etudiant', $t));
        }
        if ($request->filled('sexe')) {
            $s = $request->sexe;
            $q->whereHas('student', fn ($st) => $st->where('sexe', $s));
        }
        // Période de soumission
        if ($request->filled('date_du')) {
            $q->where('submitted_at', '>=', $request->date('date_du')->startOfDay());
        }
        if ($request->filled('date_au')) {
            $q->where('submitted_at', '<=', $request->date('date_au')->endOfDay());
        }

        return $q;
    }

    /** Tous les agrégats (partagés entre la page et les exports). */
    private function aggregats(Request $request): array
    {
        $parStatut = $this->base($request)
            ->select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')->pluck('n', 'status')->all();

        $parCatStatut = $this->base($request)
            ->select('category', 'status', DB::raw('COUNT(*) as n'))
            ->groupBy('category', 'status')->get()
            ->groupBy('category')
            ->map(fn ($rows) => $rows->pluck('n', 'status')->all())->all();

        $parCategorie = $this->base($request)
            ->select('category', DB::raw('COUNT(*) as n'))
            ->groupBy('category')->pluck('n', 'category')->all();

        $parFaculte = $this->base($request)
            ->join('parcours', 'parcours.id', '=', 'student_requests.parcours_id')
            ->join('departements', 'departements.id', '=', 'parcours.departement_id')
            ->join('facultes', 'facultes.id', '=', 'departements.faculte_id')
            ->select('facultes.nom', DB::raw('COUNT(*) as n'))
            ->groupBy('facultes.id', 'facultes.nom')
            ->orderByDesc('n')->pluck('n', 'nom')->all();

        $parDepartement = $this->base($request)
            ->join('parcours', 'parcours.id', '=', 'student_requests.parcours_id')
            ->join('departements', 'departements.id', '=', 'parcours.departement_id')
            ->select('departements.nom', DB::raw('COUNT(*) as n'))
            ->groupBy('departements.id', 'departements.nom')
            ->orderByDesc('n')->pluck('n', 'nom')->all();

        $parParcours = $this->base($request)
            ->join('parcours', 'parcours.id', '=', 'student_requests.parcours_id')
            ->select('parcours.nom', DB::raw('COUNT(*) as n'))
            ->groupBy('parcours.id', 'parcours.nom')
            ->orderByDesc('n')->limit(10)->pluck('n', 'nom')->all();

        $parSemestre = $this->base($request)
            ->whereNotNull('semestre')
            ->select('semestre', DB::raw('COUNT(*) as n'))
            ->groupBy('semestre')->orderBy('semestre')->pluck('n', 'semestre')
            ->mapWithKeys(fn ($n, $code) => [(Parcours::libelleNiveau($code) ?? $code) => $n])
            ->all();

        $parSexe = $this->base($request)
            ->join('students', 'students.id', '=', 'student_requests.student_id')
            ->select('students.sexe', DB::raw('COUNT(*) as n'))
            ->groupBy('students.sexe')->pluck('n', 'sexe')->all();

        $parType = $this->base($request)
            ->join('students', 'students.id', '=', 'student_requests.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->select('users.type_etudiant', DB::raw('COUNT(*) as n'))
            ->groupBy('users.type_etudiant')->pluck('n', 'type_etudiant')->all();

        $ids = $this->base($request)->pluck('student_requests.id');
        $nbPieces   = RequestFile::whereIn('student_request_id', $ids)->count();
        $nbTamponne = RequestFile::whereIn('student_request_id', $ids)->where('is_stamped', true)->count();

        $delaiHeures = $this->base($request)
            ->whereNotNull('submitted_at')->whereNotNull('validated_at')
            ->avg(DB::raw(\App\Support\SqlPortable::ecartHeures('submitted_at', 'validated_at')));

        $parMois = $this->base($request)
            ->whereNotNull('submitted_at')
            ->select(DB::raw(\App\Support\SqlPortable::mois('submitted_at') . ' as mois'), DB::raw('COUNT(*) as n'))
            ->groupBy('mois')->orderBy('mois')->pluck('n', 'mois')->all();

        return [
            'parStatut'      => $parStatut,
            'total'          => array_sum($parStatut),
            'parCatStatut'   => $parCatStatut,
            'parCategorie'   => $parCategorie,
            'parFaculte'     => $parFaculte,
            'parDepartement' => $parDepartement,
            'parParcours'    => $parParcours,
            'parSemestre'    => $parSemestre,
            'parSexe'        => $parSexe,
            'parType'        => $parType,
            'nbPieces'       => $nbPieces,
            'nbTamponne'     => $nbTamponne,
            'delaiJours'     => $delaiHeures ? round($delaiHeures / 24, 1) : null,
            'parMois'        => $parMois,
        ];
    }

    /** Description lisible des filtres appliqués (affichée sur les exports). */
    private function filtresLisibles(Request $request): array
    {
        $f = [];
        if ($request->input('annee_id') === 'all') {
            $f['Année'] = 'Toutes les années (cumul)';
        } elseif ($request->filled('annee_id')) {
            $f['Année'] = AnneeAcademique::find((int) $request->annee_id)?->libelle ?? $request->annee_id;
        } else {
            $f['Année'] = AnneeAcademique::context()?->libelle ?? '—';
        }
        if ($request->filled('date_du'))   $f['Du'] = $request->date('date_du')->format('d/m/Y');
        if ($request->filled('date_au'))   $f['Au'] = $request->date('date_au')->format('d/m/Y');
        if ($request->filled('category'))  $f['Procédure'] = \App\Models\Procedure::labels()[$request->category] ?? $request->category;
        if ($request->filled('status'))    $f['Statut'] = self::$statutsLabels[$request->status] ?? $request->status;
        if ($request->filled('faculte_id')) $f['Faculté'] = Faculte::find($request->faculte_id)?->nom;
        if ($request->filled('departement_id')) $f['Département'] = \App\Models\Departement::find($request->departement_id)?->nom;
        if ($request->filled('parcours_id')) $f['Parcours'] = Parcours::find($request->parcours_id)?->nom;
        if ($request->filled('grade'))     $f['Grade'] = Parcours::$grades[$request->grade]['label'] ?? $request->grade;
        if ($request->filled('semestre'))  $f['Niveau'] = Parcours::libelleNiveau($request->semestre) ?? $request->semestre;
        if ($request->filled('type_etudiant')) $f['Profil'] = $request->type_etudiant === 'nouveau' ? 'Nouveaux' : 'Anciens';
        if ($request->filled('sexe'))      $f['Sexe'] = $request->sexe;

        return $f;
    }

    public function index(Request $request)
    {
        $annee = AnneeAcademique::context();
        $agg = $this->aggregats($request);

        /* ── Candidatures (année de contexte / filtrée) ─────── */
        $candQ = Candidature::query();
        $anneeId = $request->input('annee_id');
        if ($anneeId && $anneeId !== 'all') {
            $candQ->where('annee_academique_id', (int) $anneeId);
        } elseif (!$anneeId && $annee) {
            $candQ->where('annee_academique_id', $annee->id);
        }
        $candParStatut = $candQ->select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')->pluck('n', 'status')->all();
        $candTotal = array_sum($candParStatut);

        /* ── Listes pour les filtres ───────────────────────── */
        $facultes = Faculte::where('is_active', true)->orderBy('nom')->get();
        $departements = \App\Models\Departement::where('is_active', true)
            ->when($request->filled('faculte_id'), fn ($q) => $q->where('faculte_id', $request->faculte_id))
            ->orderBy('nom')->get();
        $parcoursList = Parcours::where('is_active', true)
            ->when($request->filled('faculte_id'), fn ($q) => $q->whereHas('departement',
                fn ($d) => $d->where('faculte_id', $request->faculte_id)))
            ->orderBy('nom')->get();
        $annees = AnneeAcademique::where('statut', '<>', 'archivee')->orderByDesc('libelle')->get();

        $categories = RequestType::categories();
        $grades = Parcours::$grades;
        $statutsLabels = self::$statutsLabels;

        return view('admin.statistiques.index', array_merge($agg, compact(
            'annee', 'candParStatut', 'candTotal',
            'facultes', 'departements', 'parcoursList', 'annees', 'categories', 'grades', 'statutsLabels'
        ), [
            'enAttente' => $agg['parStatut']['soumis'] ?? 0,
            'aCorriger' => $agg['parStatut']['en_correction'] ?? 0,
            'validees'  => $agg['parStatut']['validé'] ?? 0,
            'rejetees'  => $agg['parStatut']['rejeté'] ?? 0,
            'brouillon' => $agg['parStatut']['brouillon'] ?? 0,
        ]));
    }

    /* ════════════════════════════════════════════════════════
     |  EXPORTS EN UN CLIC — mêmes filtres que la page
     |  excel = synthèse + détail (2 feuilles) | csv = détail | pdf = synthèse
     ════════════════════════════════════════════════════════ */

    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['excel', 'csv', 'pdf']), 404);

        // Nom de fichier lisible : année + filtres actifs
        $anneeId  = $request->input('annee_id');
        $anneeCtx = $anneeId === 'all'
            ? 'toutes-annees'
            : (\App\Models\AnneeAcademique::find((int) ($anneeId ?? \App\Models\AnneeAcademique::context()?->id))?->libelle);
        $contexte = [
            $anneeCtx,
            $request->filled('category') ? \App\Models\Procedure::labels()[$request->category] ?? $request->category : null,
            $request->filled('status') ? self::$statutsLabels[$request->status] ?? $request->status : null,
        ];

        if ($format === 'pdf') {
            $pdf = app(\App\Services\StatsPdfService::class)->build(
                $this->aggregats($request),
                $this->filtresLisibles($request),
                $request->user()->staff_name   // reporté en pied de chaque page
            );
            \App\Models\AuditLog::record('stats.export', null, "Export statistiques PDF", $this->filtresLisibles($request));

            $nom = \App\Support\NomExport::build('statistiques', $contexte, 'pdf');

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $nom . '"',
            ]);
        }

        // Détail : toutes les demandes filtrées, colonnes complètes
        $rows = $this->base($request)->with([
            'student.user', 'student.bachelier', 'student.ancienEtudiant',
            'parcours.departement.faculte', 'anneeAcademique', 'validator', 'files',
        ])->orderBy('annee_academique_id')->orderBy('parcours_id')->orderBy('semestre')->get();

        \App\Models\AuditLog::record('stats.export', null,
            'Export statistiques ' . strtoupper($format) . " — {$rows->count()} ligne(s)",
            $this->filtresLisibles($request));

        if ($format === 'csv') {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\DemandesDetailExport($rows),
                \App\Support\NomExport::build('statistiques-detail', $contexte, 'csv'),
                \Maatwebsite\Excel\Excel::CSV
            );
        }

        // Excel : 2 feuilles (Synthèse + Détail)
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\StatistiquesExport(
                $this->aggregats($request),
                $this->filtresLisibles($request),
                $rows
            ),
            \App\Support\NomExport::build('statistiques', $contexte, 'xlsx')
        );
    }
}
