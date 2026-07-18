<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\RequestType;
use App\Models\StudentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    /**
     * Année de consultation de l'étudiant : celle choisie en session si elle
     * fait partie de ses années, sinon l'année active.
     */
    private function anneeContexte($student): ?AnneeAcademique
    {
        $id = session('student_annee_id');
        if ($id) {
            $a = AnneeAcademique::find($id);
            if ($a) {
                return $a;
            }
        }

        return AnneeAcademique::active();
    }

    /** Années consultables : celles où l'étudiant a des demandes + l'année active. */
    private function anneesDisponibles($student)
    {
        $ids = StudentRequest::where('student_id', $student->id)
            ->whereNotNull('annee_academique_id')
            ->distinct()->pluck('annee_academique_id')->all();

        return AnneeAcademique::where(function ($q) use ($ids) {
            $q->whereIn('id', $ids)->orWhere('is_active', true);
        })->orderByDesc('date_debut')->get();
    }

    /** Bascule l'année de consultation de l'étudiant. */
    public function switchAnnee(Request $request)
    {
        $request->validate(['annee_academique_id' => 'required|exists:annees_academiques,id']);
        session(['student_annee_id' => (int) $request->annee_academique_id]);

        return back()->with('success', 'Année de consultation modifiée.');
    }

    public function dashboard()
    {
        $student = Auth::user()->student;
        $typeEtudiant = Auth::user()->type_etudiant;

        $activeTab = request('tab', 'depot');
        if ($typeEtudiant === 'nouveau') {
            $activeTab = 'depot';
        }

        // ── Contexte année ──────────────────────────────────────
        $annees        = $this->anneesDisponibles($student);
        $anneeContexte = $this->anneeContexte($student);
        $anneeActive   = AnneeAcademique::active();
        // Consultation d'une année passée = lecture seule
        $isReadOnly = $anneeContexte && $anneeActive && $anneeContexte->id !== $anneeActive->id;

        $categories = array_keys(RequestType::categoriesFor($typeEtudiant));

        $requestsByCategory = [];
        foreach ($categories as $cat) {
            // Demandes de l'année consultée uniquement (tous statuts)
            $requestsByCategory[$cat] = StudentRequest::with('files.requestType')
                ->where('student_id', $student->id)
                ->where('category', $cat)
                ->when($anneeContexte, fn ($q) => $q->where('annee_academique_id', $anneeContexte->id))
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // Statistiques de l'année consultée
        $all = collect($requestsByCategory)->flatten();
        $stats = [
            'total'      => $all->count(),
            'en_attente' => $all->where('status', 'soumis')->count(),
            'a_corriger' => $all->where('status', 'en_correction')->count(),
            'validees'   => $all->where('status', 'validé')->count(),
            'rejetees'   => $all->where('status', 'rejeté')->count(),
        ];

        $recentes = $all->sortByDesc('updated_at')->take(5)->values();

        // Données de référence : on AFFICHE le nom mais on STOCKE le sigle
        // (le verso de la fiche n'accueille que les sigles courts).
        $opt = fn ($rows) => $rows->map(fn ($r) => [
            'value' => $r->sigle ?: $r->nom,   // sigle prioritaire
            'label' => $r->nom,
        ])->values();
        $refEtudes = [
            'universites'    => [['value' => 'UK', 'label' => 'Université de Kara']],
            'domaines'       => $opt(\App\Models\Domaine::orderBy('nom')->get(['nom', 'sigle'])),
            'etablissements' => $opt(\App\Models\Faculte::where('is_active', true)->orderBy('nom')->get(['nom', 'sigle'])),
            'parcours'       => $opt(\App\Models\Parcours::where('is_active', true)->orderBy('nom')->get(['nom', 'sigle'])),
        ];

        return view('student.dashboard', compact(
            'student',
            'typeEtudiant',
            'requestsByCategory',
            'activeTab',
            'stats',
            'recentes',
            'annees',
            'anneeContexte',
            'isReadOnly',
            'refEtudes'
        ));
    }
}
