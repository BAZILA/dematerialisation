<?php

namespace App\Http\Controllers;

use App\Models\RequestType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequestTypeController extends Controller
{
    /* ── Helper redirect avec tab ──────────────────────── */
    private function backToTab(string $tab = null): \Illuminate\Http\RedirectResponse
    {
        // On revient là d'où l'on vient : les pièces s'éditent depuis
        // l'écran d'un niveau, pas depuis la liste des niveaux.
        $niveau = request('niveau');
        if ($niveau && isset(\App\Models\Parcours::$grades[$niveau])) {
            return redirect()->route('admin.request-types.niveau', [
                'grade' => $niveau,
                'tab'   => $tab ?? request('tab'),
            ]);
        }

        $tab = $tab ?? request('tab', 'depot');

        return redirect()->route('admin.request-types.index', ['tab' => $tab]);
    }

    /* ── Accueil : on entre par le NIVEAU ──────────────── */

    /**
     * Les pièces attendues se pensent d'abord par niveau d'études :
     * un master ne fournit pas ce qu'une licence fournit. L'écran
     * s'ouvre donc sur les niveaux, plutôt que sur une liste plate de
     * documents où il fallait déjà savoir ce que l'on cherchait.
     */
    public function index()
    {
        $annee = \App\Models\AnneeAcademique::context();
        $procedures = \App\Models\Procedure::where('is_active', true)->orderBy('ordre')->get();
        $parcours = \App\Models\Parcours::where('is_active', true)->get();

        $niveaux = collect(\App\Models\Parcours::$grades)
            ->map(function ($info, $code) use ($parcours, $procedures, $annee) {
                $duNiveau = $parcours->where('grade', $code);

                $ouvertes = $annee
                    ? $procedures->filter(fn ($proc) => $duNiveau->contains(
                        fn ($p) => $annee->operationOpen($proc->code, $p)
                      ))->count()
                    : 0;

                return [
                    'code'     => $code,
                    'label'    => $info['label'],
                    'parcours' => $duNiveau->count(),
                    'ouvertes' => $ouvertes,
                    'pieces'   => \App\Models\RequestTypeGrade::where('grade', $code)->count(),
                ];
            })->values();

        // Pièces sans aucune restriction : elles valent pour tout le monde
        $nbCommunes = RequestType::whereDoesntHave('parcours')
            ->whereDoesntHave('grades')->where('is_active', true)->count();

        return view('admin.request_types.index',
            compact('niveaux', 'nbCommunes', 'annee', 'procedures'));
    }

    /**
     * Détail d'un niveau : chaque procédure, son état d'ouverture pour
     * ce niveau, et les pièces qui s'y appliquent — communes à tous,
     * propres au niveau, ou propres à l'un de ses parcours.
     */
    public function niveau(string $grade)
    {
        abort_unless(isset(\App\Models\Parcours::$grades[$grade]), 404,
            "Ce niveau d'études n'existe pas.");

        $annee = \App\Models\AnneeAcademique::context();
        $info  = \App\Models\Parcours::$grades[$grade];

        $parcoursDuNiveau = \App\Models\Parcours::with('departement.faculte')
            ->where('is_active', true)->where('grade', $grade)
            ->orderBy('nom')->get();
        $idsParcours = $parcoursDuNiveau->pluck('id')->all();

        $procedures = \App\Models\Procedure::where('is_active', true)->orderBy('ordre')->get();

        $types = RequestType::with('parcours', 'grades')
            ->where(function ($q) use ($grade, $idsParcours) {
                $q->where(fn ($sq) => $sq->whereDoesntHave('parcours')->whereDoesntHave('grades'))
                  ->orWhereHas('grades', fn ($g) => $g->where('grade', $grade));
                if ($idsParcours) {
                    $q->orWhereHas('parcours', fn ($p) => $p->whereIn('parcours.id', $idsParcours));
                }
            })
            ->orderBy('order')->orderBy('id')
            ->get()->groupBy('category');

        /* État d'ouverture, procédure par procédure, pour ce niveau.
           Par défaut le niveau SUIT la règle générale : une procédure
           ouverte pour toute l'université l'est aussi ici. On n'en fait
           pas une copie en base — sinon un changement global cesserait
           de se propager. C'est l'héritage qui est affiché ; une règle
           propre au niveau ne prend le dessus qu'une fois posée. */
        $ouvertures = [];
        foreach ($procedures as $proc) {
            $generale = $annee?->periodeGenerale($proc->code);

            $ciblees = $annee
                ? $annee->periodesCiblees($proc->code)
                    ->filter(fn ($r) => $r->grade === $grade
                        || in_array((int) $r->parcours_id, $idsParcours, true))
                    ->values()
                : collect();

            // Un niveau sans parcours n'a personne à qui appliquer une
            // règle ciblée : il reflète alors la règle générale.
            $ouverte = $parcoursDuNiveau->isEmpty()
                ? (bool) ($annee && $annee->statut === 'ouverte' && $generale?->ouverteAujourdhui())
                : (bool) ($annee && $parcoursDuNiveau->contains(
                    fn ($p) => $annee->operationOpen($proc->code, $p)
                  ));

            $ouvertures[$proc->code] = [
                'ouverte'  => $ouverte,
                'generale' => $generale,
                'ciblees'  => $ciblees,
                'heritee'  => $ciblees->isEmpty(),   // rien de propre : on suit le général
            ];
        }

        $parcoursParFaculte = $parcoursDuNiveau
            ->groupBy(fn ($p) => $p->departement?->faculte?->nom ?? 'Sans rattachement');
        $grades = \App\Models\Parcours::$grades;

        return view('admin.request_types.niveau', compact(
            'grade', 'info', 'annee', 'procedures', 'types',
            'parcoursDuNiveau', 'parcoursParFaculte', 'ouvertures', 'grades'
        ));
    }

    /**
     * Ouvre ou ferme une procédure pour ce niveau, ou pour l'un de ses
     * parcours. À la lecture, la règle la plus précise l'emporte.
     */
    public function enregistrerOuverture(Request $request, string $grade)
    {
        abort_unless(isset(\App\Models\Parcours::$grades[$grade]), 404);

        $data = $request->validate([
            'operation'   => ['required', Rule::exists('procedures', 'code')],
            'cible'       => 'required|in:niveau,parcours',
            'parcours_id' => 'nullable|integer|exists:parcours,id',
            'date_debut'  => 'nullable|date',
            'date_fin'    => 'nullable|date|after_or_equal:date_debut',
        ]);

        $annee = \App\Models\AnneeAcademique::context();
        abort_unless($annee, 404, "Aucune année de contexte.");

        $cible = $data['cible'] === 'parcours' && $data['parcours_id']
            ? ['grade' => null, 'parcours_id' => (int) $data['parcours_id']]
            : ['grade' => $grade, 'parcours_id' => null];

        \App\Models\Periode::updateOrCreate(
            ['annee_academique_id' => $annee->id, 'operation' => $data['operation']] + $cible,
            [
                'date_debut' => $data['date_debut'] ?? null,
                'date_fin'   => $data['date_fin'] ?? null,
                'is_open'    => $request->boolean('is_open'),
            ]
        );

        \App\Models\AuditLog::record('periode.ciblee', null,
            "Ouverture « {$data['operation']} » réglée pour "
            . ($cible['parcours_id']
                ? 'le parcours #' . $cible['parcours_id']
                : 'tout ' . \App\Models\Parcours::$grades[$grade]['label'])
            . ' — ' . ($request->boolean('is_open') ? 'ouverte' : 'fermée'));

        return back()->with('success', 'Ouverture enregistrée.');
    }

    /** Retire une règle ciblée : la règle générale s'applique à nouveau. */
    public function supprimerOuverture(\App\Models\Periode $periode)
    {
        abort_if($periode->estGenerale(), 403,
            "La règle générale se modifie depuis l'écran des années académiques.");

        $periode->delete();

        return back()->with('success', "Règle retirée : la règle générale s'applique à nouveau.");
    }

    /**
     * Portée d'une pièce de candidature. Trois cas :
     *   commune ...... aucune restriction, réclamée à tout le monde ;
     *   par grade .... « tous les masters », sans énumérer les parcours ;
     *   par parcours . nommés un par un.
     * Grade et parcours se cumulent : on peut viser tous les masters
     * ET ajouter une pièce propre au master d'informatique.
     */
    private function syncPortee(Request $request, RequestType $type): void
    {
        if ($type->category !== 'candidature') {
            $type->parcours()->detach();
            $type->grades()->delete();

            return;
        }

        $specifique = $request->input('portee') === 'specifique';

        $parcoursSoumis = $specifique
            ? array_map('intval', array_filter((array) $request->input('parcours_ids', [])))
            : [];

        $gradesSoumis = $specifique
            ? array_values(array_intersect(
                (array) $request->input('grades', []),
                array_keys(\App\Models\Parcours::$grades)
              ))
            : [];

        /* Édition depuis l'écran d'un niveau : le formulaire ne montre que
           ce niveau et ses parcours. Sans précaution, enregistrer y
           effacerait les portées des AUTRES niveaux, qui ne sont pas dans
           la requête. On ne touche donc qu'au périmètre affiché. */
        $niveau = $request->input('niveau');
        $depuisUnNiveau = $niveau && isset(\App\Models\Parcours::$grades[$niveau]);

        if ($depuisUnNiveau) {
            $idsDuNiveau = \App\Models\Parcours::where('grade', $niveau)->pluck('id')->all();

            $horsNiveau = $type->parcours()->pluck('parcours.id')
                ->reject(fn ($id) => in_array((int) $id, $idsDuNiveau, true))
                ->map(fn ($id) => (int) $id)->all();

            $parcoursSoumis = array_values(array_unique(array_merge(
                $horsNiveau,
                array_intersect($parcoursSoumis, $idsDuNiveau)
            )));

            $autresGrades = $type->grades()->pluck('grade')
                ->reject(fn ($g) => $g === $niveau)->all();

            $gradesSoumis = array_values(array_unique(array_merge(
                $autresGrades,
                array_intersect($gradesSoumis, [$niveau])
            )));
        }

        $type->parcours()->sync($parcoursSoumis);

        $type->grades()->delete();
        foreach ($gradesSoumis as $grade) {
            $type->grades()->create(['grade' => $grade]);
        }
    }

    /* ── Store ─────────────────────────────────────────── */

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:200',
            'category' => ['required', Rule::exists('procedures', 'code')],
            'type_etudiant' => 'required|in:nouveau,ancien,both',
            'description' => 'nullable|string|max:500',
            'is_required' => 'nullable|boolean',
            'has_stamp' => 'nullable|boolean',
            'fills_verso' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
            'portee' => 'nullable|in:commune,specifique',
            'parcours_ids' => 'nullable|array',
            'parcours_ids.*' => 'integer|exists:parcours,id',
            'grades' => 'nullable|array',
            'grades.*' => 'string|max:30',
        ]);

        if ($data['category'] !== 'depot') {
            $data['type_etudiant'] = 'ancien';
        }

        $type = RequestType::create([
            'label' => $data['label'],
            'category' => $data['category'],
            'type_etudiant' => $data['type_etudiant'],
            'description' => $data['description'] ?? null,
            'is_required' => $request->boolean('is_required', true),
            'has_stamp' => $request->boolean('has_stamp', false),
            'fills_verso' => $request->boolean('fills_verso', false),
            'is_active' => true,
            'order' => $data['order'] ?? 99,
        ]);

        $this->syncPortee($request, $type);

        return $this->backToTab($data['category'])
            ->with('success', "« {$data['label']} » ajouté avec succès.");
    }

    /* ── Update ─────────────────────────────────────────── */

    public function update(Request $request, RequestType $requestType)
    {
        $data = $request->validate([
            'label' => 'required|string|max:200',
            'category' => ['required', Rule::exists('procedures', 'code')],
            'type_etudiant' => 'required|in:nouveau,ancien,both',
            'description' => 'nullable|string|max:500',
            'is_required' => 'nullable|boolean',
            'has_stamp' => 'nullable|boolean',
            'fills_verso' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
            'portee' => 'nullable|in:commune,specifique',
            'parcours_ids' => 'nullable|array',
            'parcours_ids.*' => 'integer|exists:parcours,id',
            'grades' => 'nullable|array',
            'grades.*' => 'string|max:30',
        ]);

        if ($data['category'] !== 'depot') {
            $data['type_etudiant'] = 'ancien';
        }

        $requestType->update([
            'label' => $data['label'],
            'category' => $data['category'],
            'type_etudiant' => $data['type_etudiant'],
            'description' => $data['description'] ?? null,
            'is_required' => $request->boolean('is_required', true),
            'has_stamp' => $request->boolean('has_stamp', false),
            'fills_verso' => $request->boolean('fills_verso', false),
            'is_active' => $request->boolean('is_active', true),
            'order' => $data['order'] ?? $requestType->order,
        ]);

        $this->syncPortee($request, $requestType->refresh());

        return $this->backToTab($requestType->category)
            ->with('success', "« {$requestType->label} » mis à jour.");
    }

    /* ── Destroy ────────────────────────────────────────── */

    public function destroy(RequestType $requestType)
    {
        $cat = $requestType->category;

        if ($requestType->requestFiles()->count() > 0) {
            return $this->backToTab($cat)->withErrors(
                "Impossible de supprimer « {$requestType->label} » : " .
                $requestType->requestFiles()->count() . " fichier(s) y sont associés. Désactivez-le plutôt."
            );
        }

        $label = $requestType->label;
        $requestType->delete();

        return $this->backToTab($cat)->with('success', "« {$label} » supprimé.");
    }

    /* ── Toggle actif ───────────────────────────────────── */

    public function toggleActive(RequestType $requestType)
    {
        $requestType->update(['is_active' => !$requestType->is_active]);
        $cat = $requestType->category;

        $msg = $requestType->is_active
            ? "« {$requestType->label} » activé."
            : "« {$requestType->label} » désactivé.";

        return $this->backToTab($cat)->with('success', $msg);
    }
}
