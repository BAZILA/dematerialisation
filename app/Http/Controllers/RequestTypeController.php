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
                    'pieces'   => RequestType::where('grade', $code)->where('is_active', true)->count(),
                ];
            })->values();

        /* Un niveau sans aucune pièce laisse passer des dossiers vides :
           l'écran doit le dire, pas le laisser découvrir. */
        $niveauxVides = $niveaux->where('pieces', 0)->pluck('label')->values();

        return view('admin.request_types.index',
            compact('niveaux', 'niveauxVides', 'annee', 'procedures'));
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

        /* Les pièces de CE niveau, et rien d'autre. Les niveaux partagent
           les procédures, jamais les pièces. */
        $types = RequestType::with('parcours')
            ->where('grade', $grade)
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
     * Portée d'une pièce, à l'intérieur de son niveau.
     *
     * Le niveau est porté par la pièce elle-même : une pièce de licence
     * n'est jamais réclamée à un master. Reste à dire, DANS ce niveau,
     * qui doit la fournir :
     *   commune ...... tous les parcours du niveau ;
     *   restreinte ... seulement les parcours désignés, qui doivent
     *                  appartenir à ce niveau.
     *
     * Vaut pour toutes les procédures, pas seulement les candidatures :
     * un dépôt de licence peut exiger un certificat médical du seul
     * parcours STAPS.
     */
    private function syncPortee(Request $request, RequestType $type): void
    {
        if ($request->input('portee') !== 'specifique') {
            $type->parcours()->detach();   // commune à tout le niveau

            return;
        }

        $soumis = array_map('intval', array_filter((array) $request->input('parcours_ids', [])));

        /* Un parcours d'un AUTRE niveau n'a rien à faire ici : il rendrait
           la pièce invisible pour tout le monde, puisque la sélection
           filtre d'abord sur le niveau de la pièce. On les écarte. */
        $duNiveau = \App\Models\Parcours::where('grade', $type->grade)
            ->whereIn('id', $soumis)->pluck('id')->all();

        // Plus rien de valide : la pièce redevient commune au niveau,
        // plutôt que de disparaître silencieusement du dossier.
        $type->parcours()->sync($duNiveau);
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
            'derogation_possible' => 'nullable|boolean',
            'has_stamp' => 'nullable|boolean',
            'fills_verso' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
            'portee' => 'nullable|in:commune,specifique',
            'parcours_ids' => 'nullable|array',
            'parcours_ids.*' => 'integer|exists:parcours,id',
            'grade' => ['required', Rule::in(array_keys(\App\Models\Parcours::$grades))],
        ]);

        if ($data['category'] !== 'depot') {
            $data['type_etudiant'] = 'ancien';
        }

        $type = RequestType::create([
            'label' => $data['label'],
            'category' => $data['category'],
            'grade' => $data['grade'],
            'type_etudiant' => $data['type_etudiant'],
            'description' => $data['description'] ?? null,
            'is_required' => $request->boolean('is_required'),
            // Une pièce facultative n'a rien à différer : le report ne survit
            // pas au retrait du caractère obligatoire, même par requête forgée.
            'derogation_possible' => $request->boolean('is_required')
                && $request->boolean('derogation_possible'),
            'has_stamp' => $request->boolean('has_stamp'),
            'fills_verso' => $request->boolean('fills_verso'),
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
            'derogation_possible' => 'nullable|boolean',
            'has_stamp' => 'nullable|boolean',
            'fills_verso' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
            'portee' => 'nullable|in:commune,specifique',
            'parcours_ids' => 'nullable|array',
            'parcours_ids.*' => 'integer|exists:parcours,id',
            'grade' => ['required', Rule::in(array_keys(\App\Models\Parcours::$grades))],
        ]);

        if ($data['category'] !== 'depot') {
            $data['type_etudiant'] = 'ancien';
        }

        $requestType->update([
            'label' => $data['label'],
            'category' => $data['category'],
            'grade' => $data['grade'],
            'type_etudiant' => $data['type_etudiant'],
            'description' => $data['description'] ?? null,
            // Une case décochée n'est pas envoyée par le navigateur : avec
            // « true » en valeur de repli, décocher revenait à cocher et la
            // désactivation ne passait jamais.
            'is_required' => $request->boolean('is_required'),
            // Une pièce facultative n'a rien à différer : le report ne survit
            // pas au retrait du caractère obligatoire, même par requête forgée.
            'derogation_possible' => $request->boolean('is_required')
                && $request->boolean('derogation_possible'),
            'has_stamp' => $request->boolean('has_stamp'),
            'fills_verso' => $request->boolean('fills_verso'),
            'is_active' => $request->boolean('is_active'),
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

        /* Supprimer une pièce qui a déjà servi effacerait l'historique :
           les dossiers traités ne diraient plus ce qu'on leur avait demandé.
           On refuse, et on oriente vers la désactivation. */
        $fichiers = $requestType->requestFiles()->count();
        if ($fichiers > 0) {
            return $this->backToTab($cat)->withErrors(
                "Impossible de supprimer « {$requestType->label} » : {$fichiers} fichier(s) "
                . "y sont associés. Désactivez-la plutôt — elle disparaîtra des nouveaux "
                . "dossiers sans effacer les anciens."
            );
        }

        // Même raison pour un engagement de report : le dossier doit pouvoir
        // continuer à nommer la pièce qu'il attend.
        $engagements = \App\Models\Derogation::where('request_type_id', $requestType->id)->count();
        if ($engagements > 0) {
            return $this->backToTab($cat)->withErrors(
                "Impossible de supprimer « {$requestType->label} » : {$engagements} dossier(s) "
                . "se sont engagés à la fournir plus tard. Désactivez-la plutôt."
            );
        }

        $label = $requestType->label;
        $requestType->delete();

        return $this->backToTab($cat)->with('success', "« {$label} » supprimé.");
    }

    /* ── Duplication vers un autre niveau ───────────────── */

    /**
     * Recopie les pièces d'une procédure vers un autre niveau.
     *
     * Les niveaux ne partagent plus de pièces : monter la liste du
     * master reviendrait à ressaisir celle de la licence. On la copie,
     * puis on ajuste — c'est la copie qui rend l'indépendance vivable.
     *
     * La portée par parcours n'est PAS reprise : les parcours de départ
     * n'existent pas dans le niveau d'arrivée. Les copies naissent donc
     * communes à leur niveau, à restreindre ensuite.
     */
    public function dupliquerVersNiveau(Request $request, string $grade)
    {
        abort_unless(isset(\App\Models\Parcours::$grades[$grade]), 404);

        $data = $request->validate([
            'vers'     => ['required', Rule::in(array_keys(\App\Models\Parcours::$grades))],
            'category' => ['required', Rule::exists('procedures', 'code')],
        ]);

        if ($data['vers'] === $grade) {
            return back()->withErrors('Le niveau de destination doit être différent.');
        }

        $sources = RequestType::where('grade', $grade)
            ->where('category', $data['category'])->orderBy('order')->get();

        if ($sources->isEmpty()) {
            return back()->withErrors("Ce niveau n'a aucune pièce pour cette procédure.");
        }

        // Ne pas recréer ce qui porte déjà le même libellé : la copie doit
        // pouvoir être relancée sans empiler les doublons.
        $dejaLa = RequestType::where('grade', $data['vers'])
            ->where('category', $data['category'])->pluck('label')
            ->map(fn ($l) => mb_strtolower(trim($l)))->all();

        $copiees = 0;
        foreach ($sources as $source) {
            if (in_array(mb_strtolower(trim($source->label)), $dejaLa, true)) {
                continue;
            }

            $copie = $source->replicate(['created_at', 'updated_at']);
            $copie->grade = $data['vers'];
            $copie->save();
            $copiees++;
        }

        $ignorees = $sources->count() - $copiees;
        $vers = \App\Models\Parcours::$grades[$data['vers']]['label'] ?? $data['vers'];

        $msg = $copiees === 0
            ? "Rien à copier : « {$vers} » possède déjà ces pièces."
            : "{$copiees} pièce(s) copiée(s) vers « {$vers} »."
              . ($ignorees ? " {$ignorees} déjà présente(s), laissée(s) de côté." : '')
              . ' Les copies sont communes à leur niveau : restreignez-les si besoin.';

        \App\Models\AuditLog::record('pieces.dupliquees', null,
            "Pièces « {$data['category']} » copiées de {$grade} vers {$data['vers']} ({$copiees})");

        return redirect()->route('admin.request-types.niveau',
            ['grade' => $data['vers'], 'tab' => $data['category']])->with('success', $msg);
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
