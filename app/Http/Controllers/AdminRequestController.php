<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Faculte;
use App\Models\Parcours;
use App\Models\RequestFile;
use App\Models\RequestType;
use App\Models\Setting;
use App\Models\StudentRequest;
use App\Services\FicheVersoService;
use App\Services\StampService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminRequestController extends Controller
{
    public function __construct(
        private StampService $stampService,
        private FicheVersoService $versoService,
        private \App\Services\TamponnageService $tamponnageService,
    ) {
    }

    /* ── Liste ───────────────────────────────────────────── */

    public function index(Request $request, string $category = 'all')
    {
        $allowed = array_merge(['all'], \App\Models\Procedure::query()->pluck('code')->all());
        if (!in_array($category, $allowed))
            abort(404);

        // ── Sécurité : catégories que l'agent a le droit de traiter ──
        $treatable = $request->user()->treatableCategories();
        if ($category !== 'all' && !in_array($category, $treatable, true)) {
            abort(403, "Vous n'êtes pas autorisé à traiter cette catégorie de demandes.");
        }
        if (empty($treatable)) {
            abort(403, "Aucune catégorie de demandes ne vous est attribuée.");
        }

        $query = StudentRequest::with('student.user', 'parcours.departement.faculte', 'files',
            'derogations.requestType');

        if ($category !== 'all') {
            $query->where('category', $category);
        } else {
            // « Toutes » = uniquement les catégories autorisées
            $query->whereIn('category', $treatable);
        }

        // Périmètre + filtres académiques (année, faculté/école, parcours, niveau)
        $this->appliquerFiltres($request, $query);

        $requests = $query
            ->orderByRaw("CASE status
                WHEN 'soumis'        THEN 0
                WHEN 'en_correction' THEN 1
                WHEN 'brouillon'     THEN 2
                WHEN 'validé'        THEN 3
                WHEN 'rejeté'        THEN 4
                ELSE 5 END")
            ->orderBy('submitted_at', 'desc')
            ->paginate(Setting::perPage())
            ->withQueryString();

        return view('admin.requests.index', array_merge(
            compact('requests', 'category', 'treatable'),
            $this->optionsFiltres($request)
        ));
    }

    /* ── Filtres partagés liste / export ─────────────────── */

    /**
     * Applique le périmètre de l'agent puis les filtres de la barre de
     * recherche. L'année académique est celle du contexte par défaut ;
     * « all » permet de remonter dans les années précédentes.
     */
    private function appliquerFiltres(Request $request, $query): void
    {
        $courante = AnneeAcademique::context();
        $anneeId  = $request->input('annee_id', $courante?->id);
        if (filled($anneeId) && $anneeId !== 'all') {
            $query->where('annee_academique_id', (int) $anneeId);
        }

        // Périmètre de l'agent : par défaut tout, sinon restreint aux parcours affectés
        $request->user()->applyPerimeterFilter($query);

        if ($request->filled('faculte_id')) {
            $fid = (int) $request->faculte_id;
            $query->whereHas('parcours.departement', fn ($d) => $d->where('faculte_id', $fid));
        }
        if ($request->filled('parcours_id')) {
            $query->where('parcours_id', (int) $request->parcours_id);
        }
        if ($request->filled('niveau')) {
            $query->where('semestre', $request->niveau);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('student', fn ($sq) => $sq->where('nom', 'LIKE', "%{$s}%")
                        ->orWhere('prenom', 'LIKE', "%{$s}%"))
                  ->orWhereHas('student.user', fn ($sq) => $sq->where('numero_identifiant', 'LIKE', "%{$s}%"));
            });
        }
    }

    /**
     * Options des listes déroulantes, BORNÉES au périmètre : un agent
     * restreint ne voit dans ses filtres que ce qu'il couvre réellement.
     */
    private function optionsFiltres(Request $request): array
    {
        $user = $request->user();
        $pIds = $user->perimeterParcoursIds();   // null = couvre tout
        $pNiv = $user->perimeterNiveaux();       // null = tous les niveaux

        // Facultés / instituts / écoles ayant au moins un parcours couvert
        $facultes = Faculte::where('is_active', true)
            ->when($pIds !== null, fn ($q) => $q->whereHas('departements.parcours',
                fn ($p) => $p->whereIn('parcours.id', $pIds)))
            ->orderBy('type')->orderBy('nom')->get();

        $parcoursList = Parcours::with('departement.faculte')
            ->where('is_active', true)
            ->when($pIds !== null, fn ($q) => $q->whereIn('id', $pIds))
            ->when($request->filled('faculte_id'), fn ($q) => $q->whereHas('departement',
                fn ($d) => $d->where('faculte_id', (int) $request->faculte_id)))
            ->orderBy('nom')->get();

        $niveaux = Parcours::tousLesNiveaux();
        if ($pNiv !== null) {
            $niveaux = array_intersect_key($niveaux, array_flip($pNiv));
        }

        $annees       = AnneeAcademique::orderByDesc('libelle')->get();
        $anneeContext = AnneeAcademique::context();

        return compact('facultes', 'parcoursList', 'niveaux', 'annees', 'anneeContext');
    }

    /* ── Export Excel des demandes (reprise logiciel d'inscription) ── */

    public function export(Request $request, string $category = 'depot')
    {
        $allowed = array_merge(['all'], \App\Models\Procedure::query()->pluck('code')->all());
        if (!in_array($category, $allowed, true)) {
            abort(404);
        }

        // Mêmes garde-fous que la liste : sans eux, un agent d'une autre
        // catégorie pouvait exporter l'intégralité des dépôts.
        $treatable = $request->user()->treatableCategories();
        if ($category !== 'all' && !in_array($category, $treatable, true)) {
            abort(403, "Vous n'êtes pas autorisé à exporter cette catégorie de demandes.");
        }
        if (empty($treatable)) {
            abort(403, "Aucune catégorie de demandes ne vous est attribuée.");
        }

        $query = StudentRequest::with([
            'student.user',
            'student.bachelier',
            'student.ancienEtudiant',
            'parcours.departement.faculte',
            'anneeAcademique',
            'validator',
            'files',
            'derogations.requestType',
        ]);

        if ($category !== 'all') {
            $query->where('category', $category);
        } else {
            $query->whereIn('category', $treatable);
        }

        // Exactement le même périmètre + les mêmes filtres que la liste
        $this->appliquerFiltres($request, $query);

        $rows = $query->orderBy('parcours_id')->orderBy('semestre')->orderBy('created_at')->get();

        // Nom de fichier lisible : catégorie + année + filtres actifs
        $cats = \App\Models\Procedure::labels();
        $anneeCtx = $request->filled('annee_id') && $request->input('annee_id') === 'all'
            ? 'toutes-annees'
            : (\App\Models\AnneeAcademique::find((int) $request->input('annee_id', \App\Models\AnneeAcademique::context()?->id))?->libelle);
        $contexte = [
            $anneeCtx,
            $request->filled('niveau') ? $request->niveau : null,
            $request->filled('status') ? $request->status : null,
        ];

        if ($request->input('format') === 'pdf') {
            /* Le n° de suivi ouvre la ligne : cette liste sert à valider
               les dossiers dans le logiciel d'inscription, où l'étudiant
               n'est retrouvé que par ce numéro — pas par l'identifiant de
               connexion, qui n'existe qu'ici. */
            $entetes = ['N° suivi', 'Étudiant', 'Identifiant', 'Catégorie', 'Parcours', 'Faculté',
                        'Année', 'Niveau', 'Statut', 'Soumis le'];
            $lignes = $rows->map(fn ($r) => [
                $r->student?->numero_suivi ?: '—',
                $r->student?->full_name ?? '—',
                $r->student?->user?->numero_identifiant ?? '—',
                $cats[$r->category] ?? $r->category,
                $r->parcours?->nom ?? '—',
                $r->parcours?->departement?->faculte?->sigle
                    ?? $r->parcours?->departement?->faculte?->nom ?? '—',
                $r->anneeAcademique?->libelle ?? '—',
                \App\Models\Parcours::libelleNiveau($r->semestre, $r->parcours?->grade) ?? '—',
                ucfirst($r->status),
                $r->submitted_at?->format('d/m/Y') ?? '—',
            ]);

            $pdf = app(\App\Services\TableauPdfService::class)->build(
                'Demandes — ' . ($cats[$category] ?? 'Toutes catégories'),
                $entetes, $lignes, $request->user()->staff_name,
                'Année : ' . (\App\Models\AnneeAcademique::context()?->libelle ?? '—'),
                [.75, 1.4, 1, 1, 1.3, .9, .8, 1, .8, .8]
            );

            $nom = \App\Support\NomExport::build('demandes-' . $category, $contexte, 'pdf');

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $nom . '"',
            ]);
        }

        $filename = \App\Support\NomExport::build('demandes-' . $category, $contexte, 'xlsx');

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DepotsExport($rows), $filename);
    }

    /* ── Détail ──────────────────────────────────────────── */

    public function show(StudentRequest $studentRequest)
    {
        abort_unless(request()->user()->canTreatCategory($studentRequest->category), 403,
            "Vous n'êtes pas autorisé à traiter cette catégorie.");
        abort_unless(request()->user()->coversRequest($studentRequest), 403,
            "Cette demande est hors de votre périmètre d'affectation.");

        $studentRequest->load('student.user', 'files.requestType', 'validator',
            'etudesAnterieures', 'parcours.departement.faculte', 'anneeAcademique');

        /* L'appel precedent ne transmettait pas le niveau du dossier. Depuis
           que chaque niveau a ses propres pieces, sans niveau la liste
           revient vide : l'agent voyait « Documents soumis » sans aucun
           document, alors que les fichiers etaient bien la.
           On affiche desormais tout ce qui a ete depose, en plus de ce que
           le niveau attend. */
        $types = $studentRequest->typesAAfficher();

        return view('admin.requests.show', compact('studentRequest', 'types'));
    }

    /**
     * Levee d'une derogation : l'agent constate que la piece promise
     * est arrivee. Le dossier cesse alors d'etre « sous derogation ».
     */
    public function leverDerogation(Request $request, \App\Models\Derogation $derogation)
    {
        $sr = $derogation->studentRequest;
        abort_unless($sr, 404);
        // La procédure d'abord : le périmètre seul laissait un agent d'un
        // autre service lever une dérogation sur un dépôt.
        abort_unless($request->user()->canTreatCategory($sr->category), 403,
            "Vous n'êtes pas autorisé à traiter cette catégorie.");
        abort_unless($request->user()->coversRequest($sr), 403,
            "Cette demande est hors de votre périmètre d'affectation.");

        if (!$derogation->estEnCours()) {
            return back()->withErrors('Cette dérogation a déjà été traitée.');
        }

        $derogation->update([
            'statut'    => 'levee',
            'levee_at'  => now(),
            'levee_par' => $request->user()->id,
        ]);

        \App\Models\AuditLog::record('depot.derogation_levee', $sr,
            'Dérogation levée sur « ' . ($derogation->requestType->label ?? 'document')
            . " » — pièce fournie ({$sr->reference})");

        return back()->with('success', 'Dérogation levée : la pièce est enregistrée comme fournie.');
    }

    /* ── Aperçu & téléchargement (admin voit l'original) ── */

    public function previewFile(Request $request, $fileId)
    {
        $file = RequestFile::with('studentRequest')->findOrFail($fileId);
        $this->authorizeFileAccess($request, $file, 'consultée');

        $path = Storage::path($file->file_path);
        abort_unless(file_exists($path), 404, 'Fichier introuvable.');

        return response()->file($path,
            \App\Support\FichierSur::entetesApercu($file->mime_type, $file->original_filename));
    }

    public function downloadFile(Request $request, $fileId)
    {
        $file = RequestFile::with('studentRequest')->findOrFail($fileId);
        $this->authorizeFileAccess($request, $file);

        // Sans ce contrôle, une pièce absente du disque casse la page
        // au lieu d'annoncer le problème à l'agent.
        abort_unless(Storage::disk('local')->exists($file->file_path), 404,
            "Le fichier « {$file->original_filename} » est introuvable sur le serveur. "
            . "Demandez à l'étudiant de le redéposer.");

        return Storage::download($file->file_path, $file->original_filename);
    }

    /** Un agent ne peut accéder qu'aux pièces des demandes de son périmètre et de l'année de contexte. */
    /**
     * Notifie l'étudiant d'un changement d'état de son dossier.
     * Silencieux si le module de notifications est éteint.
     */
    private function notifierEtudiant(StudentRequest $sr, string $evenement, array $variables = []): void
    {
        if (!\App\Models\NotificationEnvoyee::moduleActif()) {
            return;
        }

        $sr->loadMissing('student.user', 'anneeAcademique');
        $etudiant = $sr->student;
        $user     = $etudiant?->user;

        app(\App\Services\NotificationService::class)->notifier($evenement, [
            'nom'       => $etudiant?->full_name ?? '',
            'email'     => $user?->email ?? null,
            'telephone' => $etudiant?->telephone ?? null,
        ], $variables + [
            'procedure' => \App\Models\Procedure::labels()[$sr->category] ?? $sr->category,
            'annee'     => $sr->anneeAcademique?->libelle ?? '',
        ], $sr, $user?->id);
    }

    /**
     * Autorise l'accès à une pièce ET journalise la consultation.
     *
     * Sur une plateforme qui stocke des actes de naissance et des diplômes,
     * savoir QUI a ouvert le dossier de QUI est aussi important que de
     * savoir qui l'a validé.
     */
    private function authorizeFileAccess(Request $request, RequestFile $file, string $action = 'consultée'): void
    {
        $sr = $file->studentRequest;
        abort_unless($sr, 404);
        // La procédure d'abord : sans elle, un agent d'une autre catégorie
        // pourrait lire les pièces d'un dépôt en devinant un identifiant.
        abort_unless($request->user()->canTreatCategory($sr->category), 403,
            "Vous n'êtes pas autorisé à consulter les pièces de cette catégorie.");
        abort_unless($request->user()->coversRequest($sr), 403,
            "Cette pièce est hors de votre périmètre d'affectation.");

        \App\Models\AuditLog::record('dossier.piece_consultee', $sr,
            "Pièce « {$file->original_filename} » {$action} — "
                . ($sr->student->full_name ?? '—') . " (demande #{$sr->id})",
            ['fichier_id' => $file->id, 'action' => $action, 'categorie' => $sr->category]);
    }

    /* ════════════════════════════════════════════════════════
     |  VALIDATION
     |  - Pour DÉPÔT    : tamponne les fichiers has_stamp=true
     |  - Pour les autres : enregistre la date de rendez-vous
     ════════════════════════════════════════════════════════ */

    public function validate(Request $request, StudentRequest $studentRequest)
    {
        abort_unless($request->user()->canTreatCategory($studentRequest->category), 403,
            "Vous n'êtes pas autorisé à traiter cette catégorie.");
        abort_unless($request->user()->coversRequest($studentRequest), 403,
            "Cette demande est hors de votre périmètre d'affectation.");

        if ($studentRequest->status !== 'soumis') {
            return back()->withErrors('Cette demande ne peut pas être validée dans son état actuel.');
        }

        // Frais impayés : on ne valide pas un dossier non réglé, sauf dérogation explicite
        $paiements = app(\App\Services\Paiement\PaiementService::class);
        if (Setting::get('paiement.bloque_validation', true)
            && !$paiements->enRegle($studentRequest, $studentRequest->category)
            && !$request->user()->can('paiements.forcer')) {

            $p = $paiements->pour($studentRequest);

            return back()->withErrors(
                "Les frais de ce dossier ne sont pas réglés"
                . ($p ? " ({$p->montant_formate}, statut : {$p->statut_label})" : '')
                . ". Faites valider le paiement avant d'accepter le dossier."
            );
        }

        $isDepot = ($studentRequest->category === 'depot');

        // Validation des champs selon la catégorie
        $rules = ['admin_comment' => 'nullable|string|max:1000'];
        if (!$isDepot) {
            $rules['rendez_vous_at'] = 'required|date|after:today';
            $rules['rendez_vous_note'] = 'nullable|string|max:1000';
        }
        $request->validate($rules);

        // Charger les fichiers avec leur type + le contexte du dépôt
        $studentRequest->load('files.requestType', 'etudesAnterieures');

        $agent = $request->user();

        /* ── Tamponnage ────────────────────────────────────────────────
           Sur la connexion « sync », le travail s'exécute ici, exactement
           comme avant. Avec une vraie file d'attente, l'agent n'attend pas
           la génération des PDF : elle part dans un worker. */
        // Hors dépôt il n'y a rien à tamponner : inutile de passer par la file
        $enFile = $isDepot && config('queue.default') !== 'sync';
        $stampedCount = 0;
        $stampFailed = [];

        if ($enFile) {
            \App\Jobs\TamponnerDossier::dispatch($studentRequest->id, $agent->id);
        } else {
            $r = $this->tamponnageService->traiter($studentRequest, $agent);
            $stampedCount = $r['tamponnes'];
            $stampFailed  = $r['echecs'];
        }

        // Mettre à jour la demande
        $updateData = [
            'status' => 'validé',
            'validated_at' => now(),
            'validated_by' => Auth::id(),
            'admin_comment' => $request->admin_comment,
        ];

        if (!$isDepot) {
            $updateData['rendez_vous_at'] = $request->rendez_vous_at;
            $updateData['rendez_vous_note'] = $request->rendez_vous_note;
        }

        $studentRequest->update($updateData);

        \App\Models\AuditLog::record(
            $isDepot ? 'depot.valide' : 'demande.validee',
            $studentRequest,
            "Demande #{$studentRequest->id} ({$studentRequest->category}) validée — "
                . ($studentRequest->student->full_name ?? '—')
                . ' (' . ($studentRequest->student->user->numero_identifiant ?? '—') . ')',
            [
                'category' => $studentRequest->category,
                'etudiant' => $studentRequest->student->full_name ?? null,
                'fichiers_tamponnes' => $stampedCount,
            ]
        );

        // Prévenir l'étudiant (rien ne part si le module est désactivé)
        $this->notifierEtudiant($studentRequest, 'depot.valide');

        // Message de retour
        $msg = $isDepot
            ? "Dossier de dépôt validé."
            : "Demande validée. Rendez-vous fixé au " . \Carbon\Carbon::parse($request->rendez_vous_at)->format('d/m/Y') . ".";

        if ($enFile && $isDepot) {
            $msg .= " Le tamponnage des pièces se poursuit en arrière-plan.";
        }
        if ($stampedCount > 0)
            $msg .= " {$stampedCount} fichier(s) tamponné(s).";
        if (!empty($stampFailed)) {
            $msg .= " ATTENTION : tamponnage échoué pour — " . implode(', ', $stampFailed)
                . ". Vérifiez FPDI/TCPDF (composer require setasign/fpdi tecnickcom/tcpdf).";
        }

        return redirect()
            ->route('admin.requests.index', ['category' => $studentRequest->category])
            ->with('success', $msg);
    }

    /* ── Renvoyer en correction (dépôt uniquement) ───────── */

    public function reject(Request $request, StudentRequest $studentRequest)
    {
        abort_unless($request->user()->canTreatCategory($studentRequest->category), 403,
            "Vous n'êtes pas autorisé à traiter cette catégorie.");
        abort_unless($request->user()->coversRequest($studentRequest), 403,
            "Cette demande est hors de votre périmètre d'affectation.");

        /* Le message global n'est plus exige. Imposer 10 caracteres ralentissait
           un geste frequent — « illisible », « mauvais fichier » — alors que le
           detail se donne deja piece par piece, dans le commentaire de chaque
           fichier. */
        $request->validate([
            'rejection_message' => 'nullable|string|max:1000',
            'file_valid' => 'nullable|array',
            'file_comments' => 'nullable|array',
            'file_comments.*' => 'nullable|string|max:500',
        ]);

        if ($studentRequest->status !== 'soumis') {
            return back()->withErrors('Cette demande ne peut pas être renvoyée en correction.');
        }

        foreach ($studentRequest->files as $file) {
            $isValid = isset($request->file_valid[$file->id]);
            $comment = $request->file_comments[$file->id] ?? null;
            $file->update([
                // true = validé (verrouillé) ; false = à corriger (déverrouillé,
                // l'étudiant peut recharger le fichier). On n'utilise plus null ici
                // pour que la vue étudiant affiche bien le bouton « Remplacer ».
                'is_valid' => $isValid,
                'is_locked' => $isValid,
                'admin_comment' => $comment,
            ]);
        }

        // Sans message, l'etudiant ne doit pas rester sans indication : on
        // le renvoie vers les commentaires poses sur chaque piece.
        $message = trim((string) $request->rejection_message);
        if ($message === '') {
            $message = 'Certaines pièces sont à corriger : consultez le commentaire indiqué sur chacune.';
        }

        $studentRequest->update([
            'status' => 'en_correction',
            'rejection_message' => $message,
            'validated_by' => Auth::id(),
            'validated_at' => now(),
        ]);

        \App\Models\AuditLog::record('demande.correction', $studentRequest,
            "Demande #{$studentRequest->id} renvoyée en correction — "
                . ($studentRequest->student->full_name ?? '—'),
            ['etudiant' => $studentRequest->student->full_name ?? null]);

        return redirect()
            ->route('admin.requests.index', ['category' => $studentRequest->category])
            ->with('success', 'Demande renvoyée pour correction.');
    }

    /* ── Rejet définitif ─────────────────────────────────── */

    public function rejectFinal(Request $request, StudentRequest $studentRequest)
    {
        abort_unless($request->user()->canTreatCategory($studentRequest->category), 403,
            "Vous n'êtes pas autorisé à traiter cette catégorie.");
        abort_unless($request->user()->coversRequest($studentRequest), 403,
            "Cette demande est hors de votre périmètre d'affectation.");

        $request->validate(['rejection_message' => 'required|string|min:10|max:1000']);

        /* Seul un dossier en attente de décision se rejette. Sans ce
           garde, la même requête rejetait un dossier déjà VALIDÉ — pièces
           tamponnées comprises — ou un brouillon jamais soumis. Revenir
           sur une validation passe par « rouvrir », qui a sa permission. */
        if (!in_array($studentRequest->status, ['soumis', 'en_correction'], true)) {
            return back()->withErrors('Cette demande ne peut pas être rejetée dans son état actuel.');
        }

        $studentRequest->update([
            'status' => 'rejeté',
            'rejection_message' => $request->rejection_message,
            'validated_by' => Auth::id(),
            'validated_at' => now(),
        ]);

        \App\Models\AuditLog::record('demande.rejetee', $studentRequest,
            "Demande #{$studentRequest->id} rejetée définitivement — "
                . ($studentRequest->student->full_name ?? '—'),
            ['etudiant' => $studentRequest->student->full_name ?? null]);

        return redirect()
            ->route('admin.requests.index', ['category' => $studentRequest->category])
            ->with('success', 'Demande rejetée définitivement.');
    }

    /* ════════════════════════════════════════════════════════
     |  ANNULER UNE VALIDATION — rouvre le dossier
     |  Opération irréversible côté tampons : on supprime les
     |  fichiers tamponnés et on renvoie le dossier à l'étudiant.
     ════════════════════════════════════════════════════════ */

    public function reopen(Request $request, StudentRequest $studentRequest)
    {
        abort_unless($request->user()->canTreatCategory($studentRequest->category), 403,
            "Vous n'êtes pas autorisé à traiter cette catégorie.");
        abort_unless($request->user()->coversRequest($studentRequest), 403,
            "Cette demande est hors de votre périmètre d'affectation.");

        $request->validate(['reopen_message' => 'required|string|min:10|max:1000']);

        if (!in_array($studentRequest->status, ['validé', 'rejeté'], true)) {
            return back()->withErrors("Seule une demande déjà validée ou rejetée peut être rouverte.");
        }

        // Supprimer les fichiers tamponnés et déverrouiller les pièces
        foreach ($studentRequest->files as $file) {
            if ($file->stamped_file_path) {
                Storage::disk('local')->delete($file->stamped_file_path);
            }
            $file->update([
                'is_stamped' => false,
                'stamped_file_path' => null,
                'is_locked' => false,
                'is_valid' => false,
                'admin_comment' => null,
            ]);
        }

        $studentRequest->update([
            'status' => 'en_correction',
            'rejection_message' => $request->reopen_message,
            'validated_at' => null,
            'validated_by' => Auth::id(),  // trace de l'agent qui a rouvert
        ]);

        \App\Models\AuditLog::record('demande.rouverte', $studentRequest,
            "Demande #{$studentRequest->id} rouverte (validation annulée) — "
                . ($studentRequest->student->full_name ?? '—'),
            ['etudiant' => $studentRequest->student->full_name ?? null, 'motif' => $request->reopen_message]);

        return redirect()
            ->route('admin.requests.index', ['category' => $studentRequest->category])
            ->with('success', "Validation annulée. Le dossier est renvoyé à l'étudiant pour correction.");
    }
}
