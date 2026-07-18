<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\EtudeAnterieure;
use App\Models\Faculte;
use App\Models\Parcours;
use App\Models\StudentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Assistant de dépôt en 2 temps :
 *   1) Contexte académique — Faculté → Parcours → Année → Semestre
 *   2) (sur le tableau de bord) upload des pièces + études antérieures
 *      + confirmation de l'engagement avant soumission.
 */
class StudentDepotController extends Controller
{
    /** Année ouverte aux dépôts (l'active si elle a une fenêtre « depot » ouverte). */
    private function anneeDepot(): ?AnneeAcademique
    {
        $active = AnneeAcademique::active();
        return ($active && $active->operationOpen('depot')) ? $active : null;
    }

    /** Le brouillon/dépôt en cours de l'étudiant, s'il existe. */
    private function depotEnCours(int $studentId): ?StudentRequest
    {
        return StudentRequest::where('student_id', $studentId)
            ->where('category', 'depot')
            ->whereIn('status', ['brouillon', 'soumis', 'en_correction'])
            ->latest()
            ->first();
    }

    /* ─────────────────────────────────────────────────────────
     |  Étape 1 — choix du contexte académique
     ───────────────────────────────────────────────────────── */
    public function start()
    {
        $student = Auth::user()->student;

        // Un dépôt est déjà en cours → on retourne au tableau de bord
        if ($existing = $this->depotEnCours($student->id)) {
            return redirect()
                ->route('student.dashboard', ['tab' => 'depot'])
                ->with('info', 'Vous avez déjà un dépôt en cours.');
        }

        $annee = $this->anneeDepot();
        if (!$annee) {
            return redirect()
                ->route('student.dashboard', ['tab' => 'depot'])
                ->withErrors("Les dépôts ne sont pas ouverts pour le moment. Réessayez lorsque la période sera ouverte.");
        }

        // Arbre Faculté → Département → Parcours (actifs) pour la cascade
        $facultes = Faculte::where('is_active', true)
            ->with(['departements' => fn ($q) => $q->where('is_active', true)
                ->with(['parcours' => fn ($q) => $q->where('is_active', true)->orderBy('nom')])
                ->orderBy('nom')])
            ->orderBy('nom')
            ->get();

        return view('student.depot.start', [
            'facultes'  => $facultes,
            'annee'     => $annee,
            'grades'    => Parcours::$grades,
        ]);
    }

    /* ─────────────────────────────────────────────────────────
     |  Étape 1 (POST) — création du brouillon avec contexte
     ───────────────────────────────────────────────────────── */
    public function store(Request $request)
    {
        $student = Auth::user()->student;

        if ($this->depotEnCours($student->id)) {
            return redirect()->route('student.dashboard', ['tab' => 'depot'])
                ->with('info', 'Vous avez déjà un dépôt en cours.');
        }

        $annee = $this->anneeDepot();
        if (!$annee) {
            return redirect()->route('student.dashboard', ['tab' => 'depot'])
                ->withErrors("Les dépôts ne sont pas ouverts pour le moment.");
        }

        $data = $request->validate([
            'parcours_id' => 'required|exists:parcours,id',
            'semestre'    => 'required|string|max:20',
        ], [], [
            'parcours_id' => 'parcours',
            'semestre'    => 'semestre',
        ]);

        $parcours = Parcours::with('domaine', 'departement.faculte')
            ->where('is_active', true)->findOrFail($data['parcours_id']);

        // Le semestre doit appartenir au grade du parcours choisi
        if (!in_array($data['semestre'], $parcours->semestres(), true)) {
            return back()->withInput()
                ->withErrors(['semestre' => 'Semestre invalide pour ce parcours.']);
        }

        $sr = StudentRequest::create([
            'student_id'          => $student->id,
            'category'            => 'depot',
            'parcours_id'         => $parcours->id,
            'annee_academique_id' => $annee->id,
            'semestre'            => $data['semestre'],
            'engagement_accepte'  => false,
            'status'              => 'brouillon',
            'version'             => 1,
        ]);

        // Pré-remplissage des études antérieures
        $this->prefillEtudes($sr, $student, $parcours, $annee);

        return redirect()->route('student.dashboard', ['tab' => 'depot'])
            ->with('success', 'Contexte enregistré. Ajoutez vos pièces et vos études antérieures, puis soumettez.');
    }

    /**
     * Pré-remplit les études : on reprend celles du dépôt précédent (report
     * d'une année sur l'autre) ; à défaut, pour un nouveau bachelier, on
     * inscrit d'office l'année en cours (parcours/établissement UK, sans crédits).
     */
    private function prefillEtudes(StudentRequest $sr, $student, Parcours $parcours, AnneeAcademique $annee): void
    {
        $previous = StudentRequest::where('student_id', $student->id)
            ->where('category', 'depot')
            ->where('id', '<>', $sr->id)
            ->whereHas('etudesAnterieures')
            ->latest()->first();

        if ($previous) {
            foreach ($previous->etudesAnterieures as $e) {
                $sr->etudesAnterieures()->create($e->only([
                    'annee_debut', 'annee_fin', 'universite', 'domaine',
                    'etablissement', 'parcours', 'credits',
                ]));
            }
            return;
        }

        if (($student->user->type_etudiant ?? null) === 'nouveau') {
            $annees = explode('-', $annee->libelle);
            $sr->etudesAnterieures()->create([
                'annee_debut'   => $annees[0] ?? null,
                'annee_fin'     => $annees[1] ?? null,
                'universite'    => 'UK',
                'domaine'       => $parcours->domaine?->sigle ?: $parcours->domaine?->nom,
                'etablissement' => $parcours->departement?->faculte?->sigle ?: $parcours->departement?->faculte?->nom,
                'parcours'      => $parcours->sigle ?: $parcours->nom,
                'credits'       => null, // pas de crédits pour l'année en cours
            ]);
        }
    }

    /* ─────────────────────────────────────────────────────────
     |  Études antérieures (verso) — remplace toutes les lignes
     ───────────────────────────────────────────────────────── */
    public function saveEtudes(Request $request, StudentRequest $studentRequest)
    {
        $this->authorizeOwner($studentRequest);

        if ($studentRequest->category !== 'depot' || !$studentRequest->canEdit()) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'Non modifiable.'], 422);
            }
            return redirect()->route('student.dashboard', ['tab' => 'depot'])
                ->withErrors('Ces informations ne peuvent plus être modifiées.');
        }

        $validated = $request->validate([
            'lignes'                  => 'nullable|array',
            'lignes.*.annee_debut'    => 'nullable|digits:4',
            'lignes.*.annee_fin'      => 'nullable|digits:4',
            'lignes.*.universite'     => 'nullable|string|max:255',
            'lignes.*.domaine'        => 'nullable|string|max:255',
            'lignes.*.etablissement'  => 'nullable|string|max:255',
            'lignes.*.parcours'       => 'nullable|string|max:255',
            'lignes.*.credits'        => 'nullable|integer|min:0|max:500',
        ]);

        // On remplace intégralement les lignes existantes
        $studentRequest->etudesAnterieures()->delete();

        foreach ($validated['lignes'] ?? [] as $ligne) {
            // On ignore les lignes entièrement vides
            if (!collect($ligne)->filter(fn ($v) => filled($v))->count()) {
                continue;
            }
            $studentRequest->etudesAnterieures()->create([
                'annee_debut'   => $ligne['annee_debut']   ?? null,
                'annee_fin'     => $ligne['annee_fin']     ?? null,
                'universite'    => $ligne['universite']    ?? null,
                'domaine'       => $ligne['domaine']       ?? null,
                'etablissement' => $ligne['etablissement'] ?? null,
                'parcours'      => $ligne['parcours']      ?? null,
                'credits'       => $ligne['credits']       ?? null,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'count' => $studentRequest->etudesAnterieures()->count()]);
        }

        return redirect()->route('student.dashboard', ['tab' => 'depot'])
            ->with('success', 'Études antérieures enregistrées.');
    }

    private function authorizeOwner(StudentRequest $sr): void
    {
        if ($sr->student_id !== Auth::user()->student->id) {
            abort(403);
        }
    }
}
