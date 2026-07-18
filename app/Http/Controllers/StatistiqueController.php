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
 * Toujours borné à l'année de contexte et au périmètre de l'agent.
 */
class StatistiqueController extends Controller
{
    /** Requête de base (année + périmètre + filtres), neuve à chaque appel. */
    private function base(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $q = StudentRequest::query();

        if ($annee = AnneeAcademique::context()) {
            $q->where('annee_academique_id', $annee->id);
        }
        $request->user()->applyPerimeterFilter($q);

        if ($request->filled('faculte_id')) {
            $fid = $request->faculte_id;
            $q->whereHas('parcours.departement', fn ($d) => $d->where('faculte_id', $fid));
        }
        if ($request->filled('parcours_id')) {
            $q->where('parcours_id', $request->parcours_id);
        }
        if ($request->filled('semestre')) {
            $q->where('semestre', $request->semestre);
        }

        return $q;
    }

    public function index(Request $request)
    {
        $annee = AnneeAcademique::context();

        /* ── Compteurs par statut ──────────────────────────── */
        $parStatut = $this->base($request)
            ->select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')->pluck('n', 'status')->all();

        $total     = array_sum($parStatut);
        $enAttente = $parStatut['soumis'] ?? 0;
        $aCorriger = $parStatut['en_correction'] ?? 0;
        $validees  = $parStatut['validé'] ?? 0;
        $rejetees  = $parStatut['rejeté'] ?? 0;
        $brouillon = $parStatut['brouillon'] ?? 0;

        /* ── Par procédure (catégorie) × statut ────────────── */
        $parCatStatut = $this->base($request)
            ->select('category', 'status', DB::raw('COUNT(*) as n'))
            ->groupBy('category', 'status')->get()
            ->groupBy('category')
            ->map(fn ($rows) => $rows->pluck('n', 'status')->all())->all();

        $parCategorie = $this->base($request)
            ->select('category', DB::raw('COUNT(*) as n'))
            ->groupBy('category')->pluck('n', 'category')->all();

        /* ── Dépôts par faculté ────────────────────────────── */
        $parFaculte = $this->base($request)
            ->join('parcours', 'parcours.id', '=', 'student_requests.parcours_id')
            ->join('departements', 'departements.id', '=', 'parcours.departement_id')
            ->join('facultes', 'facultes.id', '=', 'departements.faculte_id')
            ->select('facultes.nom', DB::raw('COUNT(*) as n'))
            ->groupBy('facultes.id', 'facultes.nom')
            ->orderByDesc('n')->pluck('n', 'nom')->all();

        /* ── Top parcours ──────────────────────────────────── */
        $parParcours = $this->base($request)
            ->join('parcours', 'parcours.id', '=', 'student_requests.parcours_id')
            ->select('parcours.nom', DB::raw('COUNT(*) as n'))
            ->groupBy('parcours.id', 'parcours.nom')
            ->orderByDesc('n')->limit(10)->pluck('n', 'nom')->all();

        /* ── Par semestre + saison ─────────────────────────── */
        $parSemestre = $this->base($request)
            ->whereNotNull('semestre')
            ->select('semestre', DB::raw('COUNT(*) as n'))
            ->groupBy('semestre')->orderBy('semestre')->pluck('n', 'semestre')->all();

        $parSaison = ['Harmattan' => 0, 'Mousson' => 0];
        foreach ($parSemestre as $sem => $n) {
            $s = StudentRequest::saison($sem);
            if ($s) {
                $parSaison[$s] += $n;
            }
        }

        /* ── Par sexe / type d'étudiant ────────────────────── */
        $parSexe = $this->base($request)
            ->join('students', 'students.id', '=', 'student_requests.student_id')
            ->select('students.sexe', DB::raw('COUNT(*) as n'))
            ->groupBy('students.sexe')->pluck('n', 'sexe')->all();

        $parType = $this->base($request)
            ->join('students', 'students.id', '=', 'student_requests.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->select('users.type_etudiant', DB::raw('COUNT(*) as n'))
            ->groupBy('users.type_etudiant')->pluck('n', 'type_etudiant')->all();

        /* ── Pièces jointes ────────────────────────────────── */
        $ids = $this->base($request)->pluck('student_requests.id');
        $nbPieces   = RequestFile::whereIn('student_request_id', $ids)->count();
        $nbTamponne = RequestFile::whereIn('student_request_id', $ids)->where('is_stamped', true)->count();

        /* ── Délai moyen de traitement (soumission → validation) ── */
        $delaiHeures = $this->base($request)
            ->whereNotNull('submitted_at')->whereNotNull('validated_at')
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, submitted_at, validated_at)'));
        $delaiJours = $delaiHeures ? round($delaiHeures / 24, 1) : null;

        /* ── Évolution : soumissions par mois ──────────────── */
        $parMois = $this->base($request)
            ->whereNotNull('submitted_at')
            ->select(DB::raw("DATE_FORMAT(submitted_at, '%Y-%m') as mois"), DB::raw('COUNT(*) as n'))
            ->groupBy('mois')->orderBy('mois')->pluck('n', 'mois')->all();

        /* ── Candidatures (année de contexte) ──────────────── */
        $candQ = Candidature::query();
        if ($annee) {
            $candQ->where('annee_academique_id', $annee->id);
        }
        $candParStatut = (clone $candQ)->select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')->pluck('n', 'status')->all();
        $candTotal = array_sum($candParStatut);

        /* ── Listes pour les filtres ───────────────────────── */
        $facultes = Faculte::where('is_active', true)->orderBy('nom')->get();
        $parcoursList = Parcours::where('is_active', true)
            ->when($request->filled('faculte_id'), fn ($q) => $q->whereHas('departement',
                fn ($d) => $d->where('faculte_id', $request->faculte_id)))
            ->orderBy('nom')->get();

        $categories = RequestType::categories();
        $statutsLabels = [
            'brouillon' => 'Brouillon', 'soumis' => 'En attente', 'en_correction' => 'À corriger',
            'validé' => 'Validé', 'rejeté' => 'Rejeté',
        ];

        return view('admin.statistiques.index', compact(
            'annee', 'total', 'enAttente', 'aCorriger', 'validees', 'rejetees', 'brouillon',
            'parStatut', 'parCategorie', 'parCatStatut', 'parFaculte', 'parParcours',
            'parSemestre', 'parSaison', 'parSexe', 'parType',
            'nbPieces', 'nbTamponne', 'delaiJours', 'parMois',
            'candParStatut', 'candTotal',
            'facultes', 'parcoursList', 'categories', 'statutsLabels'
        ));
    }
}
