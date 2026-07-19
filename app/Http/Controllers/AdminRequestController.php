<?php

namespace App\Http\Controllers;

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

        $query = StudentRequest::with('student.user', 'files');

        // Isolation par année : on ne voit que l'année de contexte
        if ($annee = \App\Models\AnneeAcademique::context()) {
            $query->where('annee_academique_id', $annee->id);
        }

        // Périmètre de l'agent : par défaut tout, sinon restreint aux parcours affectés
        $request->user()->applyPerimeterFilter($query);

        if ($category !== 'all') {
            $query->where('category', $category);
        } else {
            // « Toutes » = uniquement les catégories autorisées
            $query->whereIn('category', $treatable);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas(
                    'student',
                    fn($sq) =>
                    $sq->where('nom', 'LIKE', "%{$s}%")->orWhere('prenom', 'LIKE', "%{$s}%")
                )->orWhereHas(
                        'student.user',
                        fn($sq) =>
                        $sq->where('numero_identifiant', 'LIKE', "%{$s}%")
                    );
            });
        }

        $requests = $query
            ->orderByRaw("CASE status
                WHEN 'soumis'        THEN 0
                WHEN 'en_correction' THEN 1
                WHEN 'brouillon'     THEN 2
                WHEN 'validé'        THEN 3
                WHEN 'rejeté'        THEN 4
                ELSE 5 END")
            ->orderBy('submitted_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.requests.index', compact('requests', 'category', 'treatable'));
    }

    /* ── Export Excel des demandes (reprise logiciel d'inscription) ── */

    public function export(Request $request, string $category = 'depot')
    {
        $allowed = ['all', 'depot', 'attestation', 'diplome', 'releve', 'duplicat'];
        if (!in_array($category, $allowed)) {
            abort(404);
        }

        $query = StudentRequest::with([
            'student.user',
            'student.bachelier',
            'student.ancienEtudiant',
            'parcours.departement.faculte',
            'anneeAcademique',
            'validator',
            'files',
        ]);

        // Isolation par année (même contexte que la liste)
        if ($annee = \App\Models\AnneeAcademique::context()) {
            $query->where('annee_academique_id', $annee->id);
        }

        // Même périmètre + filtres que la liste
        $request->user()->applyPerimeterFilter($query);

        if ($category !== 'all') {
            $query->where('category', $category);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('student', fn ($sq) =>
                $sq->where('nom', 'LIKE', "%{$s}%")->orWhere('prenom', 'LIKE', "%{$s}%"));
        }

        $rows = $query->orderBy('parcours_id')->orderBy('semestre')->orderBy('created_at')->get();

        $filename = 'depots-' . $category . '-' . now()->format('Ymd-His') . '.xlsx';

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

        $types = RequestType::forCategory(
            $studentRequest->category,
            $studentRequest->student->user->type_etudiant
        );

        return view('admin.requests.show', compact('studentRequest', 'types'));
    }

    /* ── Aperçu & téléchargement (admin voit l'original) ── */

    public function previewFile(Request $request, $fileId)
    {
        $file = RequestFile::with('studentRequest')->findOrFail($fileId);
        $this->authorizeFileAccess($request, $file);

        $path = Storage::path($file->file_path);
        abort_unless(file_exists($path), 404, 'Fichier introuvable.');

        return response()->file($path, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'inline; filename="' . $file->original_filename . '"',
        ]);
    }

    public function downloadFile(Request $request, $fileId)
    {
        $file = RequestFile::with('studentRequest')->findOrFail($fileId);
        $this->authorizeFileAccess($request, $file);

        return Storage::download($file->file_path, $file->original_filename);
    }

    /** Un agent ne peut accéder qu'aux pièces des demandes de son périmètre et de l'année de contexte. */
    private function authorizeFileAccess(Request $request, RequestFile $file): void
    {
        $sr = $file->studentRequest;
        abort_unless($sr, 404);
        abort_unless($request->user()->coversRequest($sr), 403,
            "Cette pièce est hors de votre périmètre d'affectation.");
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

        $stampedCount = 0;
        $stampFailed = [];
        $agent = $request->user();
        $validatorName = $agent->staff_name;

        // Ressources d'overlay (cachet + signature agent)
        $cachetRel = Setting::get('cachet_path');
        $cachetAbs = $cachetRel && Storage::disk('local')->exists($cachetRel)
            ? Storage::disk('local')->path($cachetRel) : null;
        $signAbs = $agent->signature_path && Storage::disk('local')->exists($agent->signature_path)
            ? Storage::disk('local')->path($agent->signature_path) : null;

        // Études antérieures déclarées (pour le verso)
        $etudes = $studentRequest->etudesAnterieures->map(fn ($e) => [
            'annee_debut'   => $e->annee_debut,
            'annee_fin'     => $e->annee_fin,
            'universite'    => $e->universite,
            'domaine'       => $e->domaine,
            'etablissement' => $e->etablissement,
            'parcours'      => $e->parcours,
            'credits'       => $e->credits,
        ])->all();

        foreach ($studentRequest->files as $file) {

            // ── Traitement PDF : uniquement dépôt + has_stamp=true + PDF ──
            if ($isDepot && $file->requestType?->has_stamp === true && $file->isPdf()) {

                $dir = 'requests/' . $studentRequest->id . '/stamped';
                $stampedName = 'TAMPONNE_' . $file->stored_filename;
                $stampedPath = $dir . '/' . $stampedName;

                $absIn = Storage::path($file->file_path);
                $absOut = Storage::path($stampedPath);

                // Créer le dossier de destination si nécessaire
                $absDir = dirname($absOut);
                if (!is_dir($absDir)) {
                    mkdir($absDir, 0755, true);
                }

                // La FICHE (fills_verso) : verso auto-rempli ; sinon simple cachet
                if ($file->requestType->fills_verso === true) {
                    $ok = $this->versoService->fillVerso(
                        $absIn, $absOut, $etudes, $cachetAbs, $signAbs, $validatorName,
                        optional($studentRequest->submitted_at)->toDateTimeString(),
                        'DEP-' . $studentRequest->id
                    );
                } else {
                    $ok = $this->stampService->stampPdf($absIn, $absOut, $validatorName, $cachetAbs);
                }

                if ($ok) {
                    $file->update([
                        'is_stamped' => true,
                        'stamped_file_path' => $stampedPath,
                        'is_locked' => true,
                        'is_valid' => true,
                    ]);
                    $stampedCount++;
                    Log::info("Tampon OK : {$file->original_filename} → {$stampedPath}");
                } else {
                    $stampFailed[] = $file->original_filename;
                    $file->update(['is_locked' => true, 'is_valid' => true]);
                    Log::error("Tampon ÉCHEC : {$file->original_filename}");
                }

            } else {
                // Pas de tampon : juste verrouiller et marquer valide
                $file->update(['is_locked' => true, 'is_valid' => true]);
            }
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
            "Demande #{$studentRequest->id} ({$studentRequest->category}) validée",
            ['category' => $studentRequest->category, 'fichiers_tamponnes' => $stampedCount]
        );

        // Message de retour
        $msg = $isDepot
            ? "Dossier de dépôt validé."
            : "Demande validée. Rendez-vous fixé au " . \Carbon\Carbon::parse($request->rendez_vous_at)->format('d/m/Y') . ".";

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

        $request->validate([
            'rejection_message' => 'required|string|min:10',
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

        $studentRequest->update([
            'status' => 'en_correction',
            'rejection_message' => $request->rejection_message,
            'validated_by' => Auth::id(),
            'validated_at' => now(),
        ]);

        \App\Models\AuditLog::record('demande.correction', $studentRequest,
            "Demande #{$studentRequest->id} renvoyée en correction");

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

        $request->validate(['rejection_message' => 'required|string|min:10']);

        $studentRequest->update([
            'status' => 'rejeté',
            'rejection_message' => $request->rejection_message,
            'validated_by' => Auth::id(),
            'validated_at' => now(),
        ]);

        \App\Models\AuditLog::record('demande.rejetee', $studentRequest,
            "Demande #{$studentRequest->id} rejetée définitivement");

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
            "Demande #{$studentRequest->id} rouverte (validation annulée)",
            ['motif' => $request->reopen_message]);

        return redirect()
            ->route('admin.requests.index', ['category' => $studentRequest->category])
            ->with('success', "Validation annulée. Le dossier est renvoyé à l'étudiant pour correction.");
    }
}
