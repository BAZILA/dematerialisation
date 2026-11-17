<?php

namespace App\Http\Controllers;

use App\Models\Candidature;
use App\Models\CandidatureFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CandidatureController extends Controller
{
    public function index(Request $request)
    {
        $query = Candidature::with('parcours.departement.faculte', 'choix.parcours', 'anneeAcademique')
            ->withCount('files', 'choix');

        $this->appliquerFiltres($request, $query);

        $candidatures = $query->orderByRaw("CASE status WHEN 'soumise' THEN 0 WHEN 'en_instruction' THEN 1 WHEN 'en_correction' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')->paginate(\App\Models\Setting::perPage())->withQueryString();

        return view('admin.candidatures.index', array_merge(
            compact('candidatures'),
            $this->optionsFiltres($request)
        ));
    }

    /**
     * Isolation par année + périmètre (PAR PARCOURS, sans niveau) + filtres.
     * L'année de contexte est le défaut ; « all » remonte l'historique.
     */
    private function appliquerFiltres(Request $request, $query): void
    {
        $courante = \App\Models\AnneeAcademique::context();
        $anneeId  = $request->input('annee_id', $courante?->id);
        if (filled($anneeId) && $anneeId !== 'all') {
            $query->where('annee_academique_id', (int) $anneeId);
        }

        $request->user()->applyCandidaturePerimeter($query);

        if ($request->filled('faculte_id')) {
            $fid = (int) $request->faculte_id;
            $query->whereHas('choix.parcours.departement', fn ($d) => $d->where('faculte_id', $fid));
        }
        if ($request->filled('parcours_id')) {
            $pid = (int) $request->parcours_id;
            $query->where(function ($q) use ($pid) {
                $q->where('parcours_id', $pid)
                  ->orWhereHas('choix', fn ($c) => $c->where('parcours_id', $pid));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nom', 'LIKE', "%{$s}%")
                  ->orWhere('prenom', 'LIKE', "%{$s}%")
                  ->orWhere('reference', 'LIKE', "%{$s}%")
                  ->orWhere('email', 'LIKE', "%{$s}%");
            });
        }
    }

    /** Listes déroulantes bornées au périmètre — parcours seulement, pas de niveau. */
    private function optionsFiltres(Request $request): array
    {
        $pIds = $request->user()->perimeterParcoursIds();

        $facultes = \App\Models\Faculte::where('is_active', true)
            ->when($pIds !== null, fn ($q) => $q->whereHas('departements.parcours',
                fn ($p) => $p->whereIn('parcours.id', $pIds)))
            ->orderBy('type')->orderBy('nom')->get();

        $parcoursList = \App\Models\Parcours::with('departement.faculte')
            ->where('is_active', true)
            ->when($pIds !== null, fn ($q) => $q->whereIn('id', $pIds))
            ->when($request->filled('faculte_id'), fn ($q) => $q->whereHas('departement',
                fn ($d) => $d->where('faculte_id', (int) $request->faculte_id)))
            ->orderBy('nom')->get();

        return [
            'facultes'     => $facultes,
            'parcoursList' => $parcoursList,
            'annees'       => \App\Models\AnneeAcademique::orderByDesc('libelle')->get(),
            'anneeContext' => \App\Models\AnneeAcademique::context(),
            'restreint'    => $pIds !== null,
        ];
    }

    /* ══════════ TABLEAU DE BORD PAR PARCOURS ══════════
     |  Les commissions d'admission raisonnent par parcours, pas par
     |  candidat : « combien sur Informatique, combien reste-t-il à
     |  instruire ». On compte donc les VŒUX — un candidat qui vise trois
     |  parcours pèse une unité dans chacun.
     */

    public function tableauBord(Request $request)
    {
        $lignes = $this->agregatParcours($request);

        return view('admin.candidatures.tableau', array_merge(
            ['lignes' => $lignes, 'totaux' => $this->totaliser($lignes)],
            $this->optionsFiltres($request)
        ));
    }

    /**
     * Un enregistrement par parcours : volumétrie, répartition par statut,
     * reste à traiter et taux d'admission.
     *
     * @return \Illuminate\Support\Collection
     */
    private function agregatParcours(Request $request)
    {
        $statuts = array_keys(\App\Models\CandidatureChoix::$statuts);

        $query = \App\Models\CandidatureChoix::query()
            ->join('candidatures', 'candidatures.id', '=', 'candidature_choix.candidature_id')
            ->join('parcours', 'parcours.id', '=', 'candidature_choix.parcours_id')
            ->select('candidature_choix.parcours_id')
            ->selectRaw('COUNT(*) as voeux')
            ->selectRaw('COUNT(DISTINCT candidatures.id) as candidats');

        // Une colonne de comptage par statut, pour éviter N requêtes
        foreach ($statuts as $s) {
            $query->selectRaw(
                "SUM(CASE WHEN candidature_choix.statut = ? THEN 1 ELSE 0 END) as `st_{$s}`",
                [$s]
            );
        }

        // Isolation par année : celle du contexte, sauf « all »
        $anneeId = $request->input('annee_id', \App\Models\AnneeAcademique::context()?->id);
        if (filled($anneeId) && $anneeId !== 'all') {
            $query->where('candidatures.annee_academique_id', (int) $anneeId);
        }

        // Périmètre de l'agent : il ne compte que ses parcours
        $pIds = $request->user()->perimeterParcoursIds();
        if ($pIds !== null) {
            $query->whereIn('candidature_choix.parcours_id', $pIds);
        }

        if ($request->filled('faculte_id')) {
            $fid = (int) $request->faculte_id;
            $query->join('departements', 'departements.id', '=', 'parcours.departement_id')
                  ->where('departements.faculte_id', $fid);
        }
        if ($request->filled('parcours_id')) {
            $query->where('candidature_choix.parcours_id', (int) $request->parcours_id);
        }

        $brut = $query->groupBy('candidature_choix.parcours_id')->get()->keyBy('parcours_id');

        $parcours = \App\Models\Parcours::with('departement.faculte')
            ->whereIn('id', $brut->keys())->get()->keyBy('id');

        return $brut->map(function ($r) use ($parcours, $statuts) {
            $p = $parcours[$r->parcours_id] ?? null;

            $par = [];
            foreach ($statuts as $s) { $par[$s] = (int) $r->{'st_' . $s}; }

            // Un dossier « traité » est celui sur lequel on a tranché
            $tranches = $par['admis'] + $par['refuse'];
            $enCours  = (int) $r->voeux - $tranches;

            return (object) [
                'parcours_id' => (int) $r->parcours_id,
                'nom'         => $p?->nom ?? '—',
                'faculte'     => $p?->departement?->faculte?->nom ?? '—',
                'grade'       => $p?->grade_label ?? '—',
                'voeux'       => (int) $r->voeux,
                'candidats'   => (int) $r->candidats,
                'par_statut'  => $par,
                'tranches'    => $tranches,
                'en_cours'    => $enCours,
                // Sur les dossiers tranchés seulement : un taux calculé sur
                // les dossiers encore en cours ne voudrait rien dire.
                'taux_admis'  => $tranches > 0 ? round($par['admis'] * 100 / $tranches, 1) : null,
            ];
        })->sortByDesc('voeux')->values();
    }

    /** Cumuls tous parcours confondus, pour les encadrés de tête. */
    private function totaliser($lignes): array
    {
        $t = [
            'voeux' => 0, 'candidats' => 0, 'en_cours' => 0, 'tranches' => 0,
            'par_statut' => array_fill_keys(array_keys(\App\Models\CandidatureChoix::$statuts), 0),
        ];

        foreach ($lignes as $l) {
            $t['voeux']    += $l->voeux;
            $t['en_cours'] += $l->en_cours;
            $t['tranches'] += $l->tranches;
            foreach ($l->par_statut as $s => $n) { $t['par_statut'][$s] += $n; }
        }

        // Un même candidat peut viser plusieurs parcours : additionner les
        // colonnes « candidats » le compterait deux fois. On le recompte.
        $t['candidats']  = $lignes->sum('candidats');
        $t['parcours']   = $lignes->count();
        $t['taux_admis'] = $t['tranches'] > 0
            ? round($t['par_statut']['admis'] * 100 / $t['tranches'], 1) : null;

        return $t;
    }

    /** Le tableau de bord, en PDF ou en tableur, avec les mêmes filtres. */
    public function exportTableau(Request $request, string $format)
    {
        abort_unless(in_array($format, ['excel', 'csv', 'pdf'], true), 404);

        $lignes = $this->agregatParcours($request);
        $labels = \App\Models\CandidatureChoix::$statuts;

        $entetes = array_merge(
            ['Parcours', 'Faculté / Institut / École', 'Grade', 'Candidats', 'Vœux'],
            array_values($labels),
            ['Reste à traiter', 'Taux d\'admission']
        );

        $rows = $lignes->map(function ($l) use ($labels) {
            $ligne = [$l->nom, $l->faculte, $l->grade, $l->candidats, $l->voeux];
            foreach (array_keys($labels) as $s) { $ligne[] = $l->par_statut[$s]; }
            $ligne[] = $l->en_cours;
            $ligne[] = $l->taux_admis === null ? '—' : $l->taux_admis . ' %';

            return $ligne;
        });

        $anneeId  = $request->input('annee_id');
        $anneeCtx = $anneeId === 'all'
            ? 'toutes-annees'
            : (\App\Models\AnneeAcademique::find((int) ($anneeId ?? \App\Models\AnneeAcademique::context()?->id))?->libelle);
        $contexte = [
            $anneeCtx,
            $request->filled('faculte_id') ? \App\Models\Faculte::find((int) $request->faculte_id)?->nom : null,
        ];

        \App\Models\AuditLog::record('candidature.tableau_export', null,
            "Export du tableau de bord candidatures ({$format}) — {$lignes->count()} parcours");

        if ($format === 'pdf') {
            $pdf = app(\App\Services\TableauPdfService::class)->build(
                'Candidatures par parcours — vue d\'ensemble',
                $entetes, $rows, $request->user()->staff_name,
                $this->filtresLisibles($request),
                [1.6, 1.6, .6, .6, .5, .6, .7, .7, .5, .6, .7, .7],
                'L'
            );

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'
                    . \App\Support\NomExport::build('candidatures-par-parcours', $contexte, 'pdf') . '"',
            ]);
        }

        $ext    = $format === 'csv' ? 'csv' : 'xlsx';
        $writer = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\TableauSimpleExport($entetes, $rows),
            \App\Support\NomExport::build('candidatures-par-parcours', $contexte, $ext),
            $writer
        );
    }

    /**
     * Export des candidatures FILTRÉES, une ligne par parcours demandé.
     * Reprend exactement les filtres de l'écran et le périmètre de l'agent.
     */
    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['excel', 'csv', 'pdf'], true), 404);

        $query = Candidature::query();
        $this->appliquerFiltres($request, $query);
        $ids = $query->pluck('id');

        // Le périmètre borne aussi les PARCOURS exportés : un agent ne doit
        // pas récupérer, via l'export, des lignes qu'il ne voit pas à l'écran.
        $pIds = $request->user()->perimeterParcoursIds();

        // Filtre « parcours » : on n'exporte QUE la ligne de ce parcours,
        // pas les autres vœux du même candidat.
        $parcoursFiltre = $request->filled('parcours_id') ? (int) $request->parcours_id : null;

        $lignes = \App\Models\CandidatureChoix::with([
                'candidature.anneeAcademique', 'candidature.files',
                'parcours.departement.faculte', 'paiement', 'decideur', 'files',
            ])
            ->whereIn('candidature_id', $ids)
            ->when($pIds !== null, fn ($q) => $q->whereIn('candidature_choix.parcours_id', $pIds))
            ->when($parcoursFiltre, fn ($q) => $q->where('candidature_choix.parcours_id', $parcoursFiltre))
            ->when($request->filled('faculte_id'), fn ($q) => $q->whereHas('parcours.departement',
                fn ($d) => $d->where('faculte_id', (int) $request->faculte_id)))
            ->join('candidatures', 'candidatures.id', '=', 'candidature_choix.candidature_id')
            ->orderBy('candidatures.nom')->orderBy('candidature_choix.ordre')
            ->select('candidature_choix.*')
            ->get();

        \App\Models\AuditLog::record('candidature.export', null,
            "Export des candidatures ({$format}) — {$lignes->count()} ligne(s)",
            ['format' => $format, 'lignes' => $lignes->count()]);

        // Nom de fichier lisible : année + filtres actifs
        $anneeId  = $request->input('annee_id');
        $anneeCtx = $anneeId === 'all'
            ? 'toutes-annees'
            : (\App\Models\AnneeAcademique::find((int) ($anneeId ?? \App\Models\AnneeAcademique::context()?->id))?->libelle);
        $contexte = [
            $anneeCtx,
            $parcoursFiltre ? \App\Models\Parcours::find($parcoursFiltre)?->nom : null,
            $request->filled('status') ? Candidature::$statuts[$request->status] ?? $request->status : null,
        ];

        if ($format === 'pdf') {
            // Mêmes colonnes que l'export Excel, sans le détail interne
            // (motif, décideur, pièces, frais) réservé au suivi administratif.
            $entetes = ['Référence', 'Nom', 'Prénom(s)', 'Sexe', 'Date de naissance', 'Lieu de naissance',
                        'Email', 'Téléphone', 'Année académique', 'Soumise le',
                        'Parcours', 'Faculté / Institut / École', 'Grade', 'Rang du choix', 'Décision'];
            $rows = $lignes->map(function ($c) {
                $d = $c->candidature;

                return [
                    $d->reference,
                    $d->nom,
                    $d->prenom,
                    $d->sexe,
                    $d->date_naissance?->format('d/m/Y'),
                    $d->lieu_naissance,
                    $d->email,
                    $d->telephone ?: '—',
                    $d->anneeAcademique?->libelle ?? '—',
                    $d->created_at?->format('d/m/Y H:i'),
                    $c->parcours?->nom ?? '—',
                    $c->parcours?->departement?->faculte?->nom ?? '—',
                    $c->parcours?->grade_label ?? '—',
                    $c->ordre,
                    $c->statut_label,
                ];
            });

            $pdf = app(\App\Services\TableauPdfService::class)->build(
                'Candidatures — une ligne par parcours demandé',
                $entetes, $rows, $request->user()->staff_name,
                $this->filtresLisibles($request),
                [.9, .8, .8, .4, .8, .8, 1.2, .7, .8, .8, 1.1, 1.3, .6, .5, .8],
                'L'
            );

            $nom = \App\Support\NomExport::build('candidatures', $contexte, 'pdf');

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $nom . '"',
            ]);
        }

        $ext    = $format === 'csv' ? 'csv' : 'xlsx';
        $writer = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\CandidaturesExport($lignes),
            \App\Support\NomExport::build('candidatures', $contexte, $ext),
            $writer
        );
    }

    /** Filtres appliqués, en clair, pour l'en-tête du rapport. */
    private function filtresLisibles(Request $request): string
    {
        $f = [];
        $annee = $request->input('annee_id', \App\Models\AnneeAcademique::context()?->id);
        if ($annee === 'all') {
            $f[] = 'Toutes les années';
        } elseif (filled($annee) && ($a = \App\Models\AnneeAcademique::find((int) $annee))) {
            $f[] = 'Année : ' . $a->libelle;
        }
        if ($request->filled('status')) {
            $f[] = 'Statut : ' . (Candidature::$statuts[$request->status] ?? $request->status);
        }
        if ($request->filled('faculte_id') && ($fac = \App\Models\Faculte::find((int) $request->faculte_id))) {
            $f[] = 'Composante : ' . $fac->nom;
        }
        if ($request->filled('parcours_id') && ($p = \App\Models\Parcours::find((int) $request->parcours_id))) {
            $f[] = 'Parcours : ' . $p->nom;
        }
        if ($request->filled('search')) {
            $f[] = 'Recherche : « ' . $request->search . ' »';
        }

        return $f ? implode('   ·   ', $f) : 'Aucun filtre appliqué';
    }

    public function show(Candidature $candidature)
    {
        abort_unless(request()->user()->coversCandidature($candidature), 403,
            "Cette candidature est hors de votre périmètre d'affectation.");

        $candidature->load('files.requestType', 'parcours.departement.faculte', 'traiteur',
            'choix.parcours.departement.faculte', 'parcoursAdmis', 'anneeAcademique');

        // Pièces attendues : communes + celles propres aux parcours demandés
        $voeux = $candidature->choix->pluck('parcours_id')->filter()->all()
            ?: array_filter([$candidature->parcours_id]);
        $attendus = \App\Models\RequestType::pourCandidature($voeux);

        return view('admin.candidatures.show', compact('candidature', 'attendus'));
    }

    /*
     | updateStatus() a été retiré : la décision globale n'existe plus.
     | Chaque parcours se décide dans decisionSection(), et le statut du
     | dossier est recalculé à partir de ses sections.
     */
    /**
     * Revue d'une pièce : validée (verrouillée pour le candidat) ou
     * rejetée avec motif (le candidat pourra la remplacer, et seulement elle).
     */
    public function reviewFile(Request $request, $file)
    {
        $f = $this->fichierAutorise($file);

        $data = $request->validate([
            'statut'      => 'required|in:fourni,valide,rejete',
            'motif_rejet' => 'nullable|string|max:500',
            'confirmer'   => 'nullable|boolean',
        ]);

        /* Une pièce PROPRE à un parcours n'est examinée que par l'agent
           qui couvre ce parcours : chacun ne voit que son propre travail. */
        if (!$f->estCommune()) {
            $parcoursPiece = $f->requestType?->parcours->pluck('id')->all() ?? [];
            $couvre = empty($parcoursPiece)
                || collect($parcoursPiece)->contains(fn ($id) => $request->user()->coversParcours((int) $id));

            if (!$couvre) {
                $msg = "Cette pièce est propre à un parcours hors de votre périmètre : "
                    . "elle relève de l'agent qui traite ce parcours.";

                return $request->expectsJson()
                    ? response()->json(['ok' => false, 'message' => $msg], 403)
                    : back()->withErrors($msg);
            }
        }

        /* Refuser une pièce COMMUNE renvoie le candidat la reconstituer,
           ce qui remet en cause TOUS ses parcours — y compris ceux déjà
           acceptés par d'autres agents. Réservé à un superviseur. */
        if ($data['statut'] === 'rejete' && $f->estCommune()
            && !$request->user()->can('pieces.commune_refuser')) {

            $msg = "Cette pièce est commune à tous les parcours du candidat : "
                . "son refus l'obligerait à refaire son dossier entier. "
                . "Seul un superviseur peut la refuser — signalez-lui le problème.";

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $msg], 403)
                : back()->withErrors($msg);
        }

        /* Une pièce COMMUNE vaut pour tous les parcours : sa décision
           engage donc les autres agents. On refuse de l'écraser en
           silence — il faut confirmer, et la révision est tracée. */
        $autreAgent = $f->estCommune()
            && $f->statut !== 'fourni'
            && $f->verifie_par
            && $f->verifie_par !== $request->user()->id;

        if ($autreAgent && !$request->boolean('confirmer')) {
            $qui = $f->verificateur?->staff_name ?? 'un autre agent';
            $quand = $f->verifie_at?->format('d/m/Y à H:i');
            $msg = "Pièce commune déjà examinée par {$qui}"
                . ($quand ? " le {$quand}" : '')
                . " (« {$f->statut_label_court} »). Elle vaut pour TOUS les parcours du candidat : "
                . "confirmez si vous voulez réviser cette décision.";

            return $request->expectsJson()
                ? response()->json([
                    'ok'             => false,
                    'deja_examinee'  => true,
                    'message'        => $msg,
                    'agent'          => $qui,
                ], 409)
                : back()->withErrors($msg);
        }

        // Valeurs d'avant : après l'écriture, elles ne seraient plus lisibles
        $ancien      = $f->statut;
        $ancienAgent = $f->verificateur?->staff_name;

        // « nullable » n'ajoute pas la clé si le champ n'a pas été transmis
        $motif = $data['motif_rejet'] ?? null;

        if ($data['statut'] === 'rejete' && blank($motif)) {
            $msg = 'Indiquez au candidat ce qui ne va pas avec cette pièce.';

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $msg], 422)
                : back()->withErrors($msg);
        }

        $f->update([
            'statut'      => $data['statut'],
            'motif_rejet' => $data['statut'] === 'rejete' ? $motif : null,
            'verifie_par' => $request->user()->id,
            'verifie_at'  => now(),
        ]);

        // Révision d'une décision d'autrui : on garde qui, quoi, avant/après
        if ($autreAgent) {
            \App\Models\AuditLog::record('candidature.piece_revisee', $f->candidature,
                "Décision RÉVISÉE sur la pièce commune « " . ($f->requestType->label ?? 'document') . " » — "
                    . (CandidatureFile::$statuts[$ancien] ?? $ancien)
                    . ' → ' . (CandidatureFile::$statuts[$data['statut']] ?? $data['statut'])
                    . " (précédemment examinée par " . ($ancienAgent ?? '—') . ")",
                ['fichier_id' => $f->id, 'avant' => $ancien, 'apres' => $data['statut']]);
        }

        \App\Models\AuditLog::record('candidature.piece', $f->candidature,
            "Pièce « " . ($f->requestType->label ?? 'document') . " » → "
                . (CandidatureFile::$statuts[$data['statut']] ?? $data['statut'])
                . " ({$f->candidature->reference})",
            ['statut' => $data['statut'], 'motif' => $f->motif_rejet]);

        // Enregistrement à la volée : on renvoie de quoi rafraîchir la ligne
        if ($request->expectsJson()) {
            $manquantes = $this->piecesManquantes($f->candidature);

            return response()->json([
                'ok'      => true,
                'statut'  => $f->statut,
                'libelle' => CandidatureFile::$statuts[$f->statut] ?? $f->statut,
                'badge'   => $f->statut_badge,
                'motif'   => $f->motif_rejet,
                'complet' => $manquantes === 0,
                'manque'  => $manquantes,
            ]);
        }

        return back()->with('success', 'Pièce mise à jour.');
    }

    /** Nombre de pièces obligatoires encore absentes du dossier. */
    private function piecesManquantes(Candidature $cand): int
    {
        $voeux = $cand->choix()->pluck('parcours_id')->filter()->all()
            ?: array_filter([$cand->parcours_id]);
        $fournis = $cand->files()->pluck('request_type_id')->filter()->all();

        return \App\Models\RequestType::pourCandidature($voeux)
            ->where('is_required', true)
            ->reject(fn ($t) => in_array($t->id, $fournis, true))
            ->count();
    }

    public function previewFile($file)
    {
        $f = $this->fichierAutorise($file);
        $path = Storage::path($f->file_path);
        abort_unless(file_exists($path), 404, 'Fichier introuvable.');

        return response()->file($path,
            \App\Support\FichierSur::entetesApercu($f->mime_type, $f->original_filename));
    }

    public function downloadFile($file)
    {
        $f = $this->fichierAutorise($file);

        // Sans ce contrôle, une pièce absente du disque casse la page
        // au lieu d'annoncer le problème à l'agent.
        abort_unless(Storage::disk('local')->exists($f->file_path), 404,
            "Le fichier « {$f->original_filename} » est introuvable sur le serveur. "
            . "Demandez au candidat de le redéposer.");

        return Storage::download($f->file_path, $f->original_filename);
    }

    /**
     * Décision sur UN parcours du candidat.
     *
     * Un candidat peut être admis sur plusieurs parcours : il choisira
     * ensuite lequel il suit, au moment de l'inscription. Chaque agent
     * ne statue que sur les parcours de son périmètre.
     */
    public function decisionSection(Request $request, \App\Models\CandidatureChoix $section)
    {
        $section->load('candidature', 'parcours', 'paiement');
        $cand = $section->candidature;

        abort_unless($request->user()->coversCandidature($cand), 403,
            "Cette candidature est hors de votre périmètre d'affectation.");
        abort_unless($request->user()->coversParcours($section->parcours_id), 403,
            "Le parcours « " . ($section->parcours->nom ?? '—') . " » n'est pas dans votre périmètre.");

        $data = $request->validate([
            'statut' => 'required|in:en_instruction,en_correction,admis,refuse',
            'motif'  => 'nullable|string|max:1000',
        ], [], ['motif' => 'motif']);

        // Renvoyer un candidat sans lui dire quoi corriger le laisse sans recours
        // (« nullable » : la clé manque si le champ n'a pas été transmis)
        if (in_array($data['statut'], ['en_correction', 'refuse'], true) && blank($data['motif'] ?? null)) {
            return back()->withErrors("Précisez le motif : le candidat doit savoir ce qui est attendu.");
        }

        // Frais impayés : pas d'admission sur ce parcours, sauf dérogation
        $paiements = app(\App\Services\Paiement\PaiementService::class);
        if ($data['statut'] === 'admis'
            && \App\Models\Setting::get('paiement.bloque_validation', true)
            && !$paiements->enRegle($section, 'candidature')
            && !$request->user()->can('paiements.forcer')) {

            $p = $paiements->pour($section);

            return back()->withErrors(
                "Les frais de ce parcours ne sont pas réglés"
                . ($p ? " ({$p->montant_formate}, statut : {$p->statut_label})" : '')
                . ". Faites valider le paiement avant de prononcer l'admission."
            );
        }

        $section->update([
            'statut'     => $data['statut'],
            'motif'      => $data['motif'] ?? null,
            'decide_par' => $request->user()->id,
            'decide_at'  => now(),
        ]);

        // Le dossier reflète l'ensemble de ses parcours
        $cand->recalculerStatut();

        $this->notifierSection($section, $data['statut'], $data['motif'] ?? '');

        \App\Models\AuditLog::record('candidature.decision_parcours', $cand,
            "Candidature {$cand->reference} — « " . ($section->parcours->nom ?? '—') . " » → "
                . (\App\Models\CandidatureChoix::$statuts[$data['statut']] ?? $data['statut'])
                . ' — ' . $cand->full_name,
            ['parcours_id' => $section->parcours_id, 'statut' => $data['statut']]);

        return back()->with('success',
            "Décision enregistrée pour « " . ($section->parcours->nom ?? '—') . " ».");
    }

    /** Notification du candidat pour un parcours précis. */
    private function notifierSection(\App\Models\CandidatureChoix $section, string $statut, string $motif = ''): void
    {
        if (!\App\Models\NotificationEnvoyee::moduleActif()) {
            return;
        }

        $evenement = match ($statut) {
            'admis'         => 'candidature.admise',
            'refuse'        => 'candidature.refusee',
            'en_correction' => 'candidature.correction',
            default         => null,
        };
        if (!$evenement) {
            return;
        }

        $cand = $section->candidature;

        app(\App\Services\NotificationService::class)->notifier($evenement, [
            'nom'       => $cand->full_name,
            'email'     => $cand->email,
            'telephone' => $cand->telephone,
        ], [
            'reference' => $cand->reference,
            'parcours'  => $section->parcours->nom ?? '',
            'motif'     => $motif,
        ], $cand);
    }

    /** Notification du candidat selon la décision rendue. */
    private function notifierCandidat(Candidature $cand, string $statut, string $motif = ''): void
    {
        if (!\App\Models\NotificationEnvoyee::moduleActif()) {
            return;
        }

        $evenement = match ($statut) {
            'admis'         => 'candidature.admise',
            'refuse'        => 'candidature.refusee',
            'en_correction' => 'candidature.correction',
            default         => null,   // « en instruction » ne mérite pas de message
        };
        if (!$evenement) {
            return;
        }

        $cand->loadMissing('parcoursAdmis');

        app(\App\Services\NotificationService::class)->notifier($evenement, [
            'nom'       => $cand->full_name,
            'email'     => $cand->email,
            'telephone' => $cand->telephone,
        ], [
            'reference' => $cand->reference,
            'parcours'  => $cand->parcoursAdmis?->nom ?? '',
            'motif'     => $motif,
        ], $cand);
    }

    /**
     * Dossier complet d'UN candidat, en ZIP.
     * Arborescence : commun/ puis un dossier par parcours.
     */
    public function telechargerDossier(Request $request, Candidature $candidature)
    {
        abort_unless($request->user()->coversCandidature($candidature), 403,
            "Cette candidature est hors de votre périmètre d'affectation.");

        $candidature->load('files.requestType', 'choix.parcours', 'choix.files.requestType');

        \App\Models\AuditLog::record('candidature.dossier_telecharge', $candidature,
            "Dossier complet téléchargé — {$candidature->full_name} ({$candidature->reference})");

        return $this->zipper(
            collect([$candidature]),
            'Candidature-' . $candidature->reference . '.zip',
            $request->user(),
            null,
            false
        );
    }

    /**
     * Lot de dossiers, selon les filtres de l'écran.
     * Si un parcours est filtré, seules ses pièces (plus les communes)
     * sont incluses : c'est le dossier tel que le voit ce parcours.
     */
    public function telechargerLot(Request $request)
    {
        $query = Candidature::query();
        $this->appliquerFiltres($request, $query);

        $ids = $query->limit(300)->pluck('id');
        $dossiers = Candidature::with('files.requestType', 'choix.parcours', 'choix.files.requestType')
            ->whereIn('id', $ids)->orderBy('nom')->get();

        abort_if($dossiers->isEmpty(), 404, 'Aucun dossier à télécharger pour ces critères.');

        $parcoursId = $request->filled('parcours_id') ? (int) $request->parcours_id : null;
        $nomParcours = $parcoursId ? (\App\Models\Parcours::find($parcoursId)?->nom ?? '') : null;

        \App\Models\AuditLog::record('candidature.lot_telecharge', null,
            "Lot de candidatures téléchargé : {$dossiers->count()} dossier(s)"
                . ($nomParcours ? " — parcours « {$nomParcours} »" : ''),
            ['nb' => $dossiers->count(), 'parcours' => $nomParcours]);

        $nom = 'Candidatures-'
            . ($nomParcours ? \Illuminate\Support\Str::slug($nomParcours) . '-' : '')
            . now()->format('Ymd-His') . '.zip';

        return $this->zipper($dossiers, $nom, $request->user(), $parcoursId, true);
    }

    /**
     * Construit l'archive.
     *
     * @param  int|null  $parcoursId  restreint aux pièces de ce parcours
     * @param  bool      $parCandidat un dossier par candidat dans le ZIP
     */
    private function zipper($dossiers, string $nomZip, $agent, ?int $parcoursId, bool $parCandidat)
    {
        if (!class_exists(\ZipArchive::class)) {
            abort(500, "L'extension PHP zip est requise pour le téléchargement groupé.");
        }

        $tmp = storage_path('app/tmp');
        if (!is_dir($tmp)) {
            mkdir($tmp, 0755, true);
        }
        $this->purgerArchivesTemporaires($tmp);
        $chemin = $tmp . '/' . \Illuminate\Support\Str::random(16) . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($chemin, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, "Impossible de créer l'archive.");
        }

        $nb = 0;
        $index = ["Candidatures — " . \App\Models\Setting::get('app.university_name', 'Université de Kara')];
        $index[] = 'Généré le ' . now()->format('d/m/Y à H:i') . ' par ' . $agent->staff_name;
        if ($parcoursId) {
            $index[] = 'Parcours : ' . (\App\Models\Parcours::find($parcoursId)?->nom ?? $parcoursId);
        }
        $index[] = str_repeat('-', 60);

        foreach ($dossiers as $cand) {
            // Le périmètre s'applique jusque dans l'archive
            if (!$agent->coversCandidature($cand)) {
                continue;
            }

            $racine = $parCandidat ? $this->slugCandidat($cand) . '/' : '';
            $index[] = $cand->reference . ' | ' . $cand->nom . ' ' . $cand->prenom . ' | ' . $cand->email;

            // Pièces communes : valables pour tous les parcours
            foreach ($cand->files->whereNull('candidature_choix_id') as $f) {
                if ($this->ajouterFichier($zip, $f, $racine . 'commun/')) {
                    $nb++;
                }
            }

            // Sections : toutes, ou seulement celle demandée
            foreach ($cand->choix as $section) {
                // $parcoursId arrive de l'URL, donc en texte : sans conversion,
                // « 12 » !== 12 ecartait toutes les sections et l'archive
                // d'un parcours sortait vide.
                if ($parcoursId && (int) $section->parcours_id !== (int) $parcoursId) {
                    continue;
                }
                if (!$agent->coversParcours($section->parcours_id)) {
                    continue;
                }
                $dossier = $racine . \Illuminate\Support\Str::slug($section->parcours->nom ?? 'parcours') . '/';
                $index[] = '    → ' . ($section->parcours->nom ?? '—') . ' : ' . $section->statut_label;

                foreach ($section->files as $f) {
                    if ($this->ajouterFichier($zip, $f, $dossier)) {
                        $nb++;
                    }
                }
            }
        }

        $index[] = str_repeat('-', 60);
        $index[] = 'Dossiers : ' . $dossiers->count() . ' — Fichiers : ' . $nb;
        $zip->addFromString('_index.txt', implode("\n", $index) . "\n");
        $zip->close();

        return response()->download($chemin, $nomZip)->deleteFileAfterSend(true);
    }

    /**
     * L'archive est normalement effacée après l'envoi, mais un
     * téléchargement interrompu la laisse sur place. On balaie donc les
     * restes de plus d'une heure : sans cela le disque se remplit peu à peu.
     */
    private function purgerArchivesTemporaires(string $dossier): void
    {
        $limite = now()->subHour()->getTimestamp();

        foreach (glob($dossier . '/*.zip') ?: [] as $vieux) {
            if (@filemtime($vieux) < $limite) {
                @unlink($vieux);
            }
        }
    }

    private function ajouterFichier(\ZipArchive $zip, CandidatureFile $f, string $dossier): bool
    {
        if (!Storage::disk('local')->exists($f->file_path)) {
            return false;
        }
        $ext = pathinfo($f->original_filename, PATHINFO_EXTENSION);
        $nom = \Illuminate\Support\Str::slug($f->requestType->label ?? 'document') . ($ext ? '.' . $ext : '');

        return $zip->addFile(Storage::disk('local')->path($f->file_path), $dossier . $nom);
    }

    private function slugCandidat(Candidature $c): string
    {
        return $c->reference . '_' . \Illuminate\Support\Str::slug($c->nom . '-' . $c->prenom);
    }

    /** Une pièce n'est consultable que si la candidature est dans le périmètre. */
    private function fichierAutorise($file): CandidatureFile
    {
        $f = CandidatureFile::with('candidature.choix')->findOrFail($file);
        abort_unless($f->candidature && request()->user()->coversCandidature($f->candidature), 403,
            "Cette candidature est hors de votre périmètre d'affectation.");

        // Traçabilité : qui a ouvert quelle pièce de quel candidat
        \App\Models\AuditLog::record('candidature.piece_consultee', $f->candidature,
            "Pièce « {$f->original_filename} » consultée — {$f->candidature->full_name}"
                . " ({$f->candidature->reference})",
            ['fichier_id' => $f->id]);

        return $f;
    }
}
