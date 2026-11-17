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

    /** Dépôt déjà TERMINÉ (validé/rejeté) pour une année donnée : 1 dépôt / année. */
    private function depotTermineCetteAnnee(int $studentId, int $anneeId): ?StudentRequest
    {
        return StudentRequest::where('student_id', $studentId)
            ->where('category', 'depot')
            ->where('annee_academique_id', $anneeId)
            ->whereIn('status', ['validé', 'rejeté'])
            ->latest()->first();
    }

    /** Dernier dépôt VALIDÉ (toutes années) : base de la PROGRESSION automatique. */
    private function dernierDepotValide(int $studentId): ?StudentRequest
    {
        return StudentRequest::with('parcours')
            ->where('student_id', $studentId)
            ->where('category', 'depot')
            ->where('status', 'validé')
            ->orderByDesc('annee_academique_id')->orderByDesc('id')
            ->first();
    }

    /**
     * SITUATION de dépôt de l'étudiant (cœur de la progression) :
     *  - 'progression'  : un dépôt validé existe → MÊME parcours, année suivante
     *                     automatique (badge dérogatoire au-delà du grade,
     *                     refus au-delà du seuil paramétré) ;
     *  - 'auto_a1'      : nouveau bachelier, premier dépôt → 1ère année imposée ;
     *  - 'choix_ancien' : ancien sans historique sur la plateforme → il choisit
     *                     son année une seule fois (dérogatoires proposées),
     *                     la suite sera automatique.
     */
    private function situation($student): array
    {
        $dernier = $this->dernierDepotValide($student->id);

        if ($dernier && $dernier->parcours) {
            $p = $dernier->parcours;
            $actuel = Parcours::niveauDepuisSemestre($dernier->semestre) ?? 'A1';
            $n = ((int) substr($actuel, 1)) + 1;
            $normales = Parcours::nbAnneesNormales($p->grade);
            $max = $normales + Parcours::anneesDerogatoires();

            return [
                'mode'        => 'progression',
                'parcours'    => $p,
                'niveau'      => 'A' . $n,
                'label'       => Parcours::libelleNiveau('A' . $n, $p->grade),
                'derogatoire' => $n > $normales,
                'depasse'     => $n > $max,
                'precedent'   => Parcours::libelleNiveau($actuel, $p->grade),
            ];
        }

        if (Auth::user()->type_etudiant === 'nouveau') {
            return ['mode' => 'auto_a1', 'niveau' => 'A1'];
        }

        return ['mode' => 'choix_ancien'];
    }

    /* ─────────────────────────────────────────────────────────
     |  Étape 1 — choix du contexte académique
     ───────────────────────────────────────────────────────── */
    public function start()
    {
        $student = Auth::user()->student;

        // Un dépôt est déjà en cours → on retourne au tableau de bord
        if ($this->depotEnCours($student->id)) {
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

        // UN SEUL dépôt par année académique
        if ($termine = $this->depotTermineCetteAnnee($student->id, $annee->id)) {
            return redirect()->route('student.dashboard', ['tab' => 'depot'])
                ->withErrors($termine->status === 'validé'
                    ? "Votre dépôt {$annee->libelle} est déjà validé — un seul dépôt par année académique."
                    : "Votre dépôt {$annee->libelle} a été rejeté définitivement. Contactez l'administration si une reprise doit être autorisée.");
        }

        // Situation de progression (parcours/année imposés ou choix)
        $sit = $this->situation($student);
        if ($sit['mode'] === 'progression' && $sit['depasse']) {
            return redirect()->route('student.dashboard', ['tab' => 'depot'])
                ->withErrors("Le seuil d'années dérogatoires est atteint pour votre parcours ({$sit['precedent']} déjà validée). Rapprochez-vous de l'administration (dérogation exceptionnelle).");
        }

        // Arbre Faculté → Département → Parcours (actifs) pour la cascade
        $facultes = Faculte::where('is_active', true)
            ->with(['departements' => fn ($q) => $q->where('is_active', true)
                ->with(['parcours' => fn ($q) => $q->where('is_active', true)->orderBy('nom')])
                ->orderBy('nom')])
            ->orderBy('nom')
            ->get();

        // Niveaux par grade — l'ancien qui choisit voit AUSSI les années dérogatoires
        $avecDerog = ($sit['mode'] === 'choix_ancien');
        $gradeNiveaux = collect(Parcours::$grades)
            ->mapWithKeys(fn ($g, $code) => [$code => Parcours::niveauxParGrade($code, $avecDerog)]);

        return view('student.depot.start', [
            'facultes'     => $facultes,
            'annee'        => $annee,
            'grades'       => Parcours::$grades,
            'gradeNiveaux' => $gradeNiveaux,
            'sit'          => $sit,
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

        // UN SEUL dépôt par année académique
        if ($this->depotTermineCetteAnnee($student->id, $annee->id)) {
            return redirect()->route('student.dashboard', ['tab' => 'depot'])
                ->withErrors("Un dépôt existe déjà pour {$annee->libelle} — un seul dépôt par année académique.");
        }

        /* ── La SITUATION fait foi (jamais le formulaire seul) ── */
        $sit = $this->situation($student);

        if ($sit['mode'] === 'progression') {
            if ($sit['depasse']) {
                return redirect()->route('student.dashboard', ['tab' => 'depot'])
                    ->withErrors("Le seuil d'années dérogatoires est atteint. Rapprochez-vous de l'administration.");
            }
            // Parcours et année IMPOSÉS par l'historique
            $parcours = $sit['parcours']->load('domaine', 'departement.faculte');
            $niveau   = $sit['niveau'];
        } else {
            $data = $request->validate([
                'parcours_id' => 'required|exists:parcours,id',
                'semestre'    => $sit['mode'] === 'auto_a1' ? 'nullable|string|max:20' : 'required|string|max:20',
            ], [], [
                'parcours_id' => 'parcours',
                'semestre'    => "année d'études",
            ]);

            $parcours = Parcours::with('domaine', 'departement.faculte')
                ->where('is_active', true)->findOrFail($data['parcours_id']);

            if ($sit['mode'] === 'auto_a1') {
                // Nouveau bachelier : la 1ère année est IMPOSÉE
                $niveau = 'A1';
            } else {
                // Ancien sans historique : choix unique parmi les niveaux du grade
                // (années dérogatoires incluses) — la suite sera automatique.
                $niveau = $data['semestre'];
                if (!array_key_exists($niveau, $parcours->niveaux(avecDerogatoires: true))) {
                    return back()->withInput()
                        ->withErrors(['semestre' => "Année d'études invalide pour ce parcours."]);
                }
            }
        }

        $sr = StudentRequest::create([
            'student_id'          => $student->id,
            'category'            => 'depot',
            'parcours_id'         => $parcours->id,
            'annee_academique_id' => $annee->id,
            'semestre'            => $niveau,
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
        /* Comparaison en entiers : un identifiant rendu sous forme de texte
           par la base faisait echouer « !== » pour le proprietaire lui-meme.

           Et un message explicite : abort(403) sans texte renvoyait
           {"message": ""}, que l'ecran de depot affichait comme « Action
           impossible » — sans permettre de distinguer un refus de
           l'application d'un blocage du pare-feu. */
        $moi = Auth::user()?->student?->id;

        if ($moi === null || (int) $sr->student_id !== (int) $moi) {
            abort(403, "Ce dossier n'est pas rattache a votre compte.");
        }
    }
}
