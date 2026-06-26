<?php

namespace App\Http\Controllers;

use App\Models\RequestFile;
use App\Models\RequestType;
use App\Models\StudentRequest;
use App\Services\StampService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminRequestController extends Controller
{
    public function __construct(private StampService $stampService)
    {
    }

    /* ── Liste ───────────────────────────────────────────── */

    public function index(Request $request, string $category = 'all')
    {
        $allowed = ['all', 'depot', 'attestation', 'diplome', 'releve', 'duplicat'];
        if (!in_array($category, $allowed))
            abort(404);

        $query = StudentRequest::with('student.user', 'files');

        if ($category !== 'all') {
            $query->where('category', $category);
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

        return view('admin.requests.index', compact('requests', 'category'));
    }

    /* ── Détail ──────────────────────────────────────────── */

    public function show(StudentRequest $studentRequest)
    {
        $studentRequest->load('student.user', 'files.requestType', 'validator');

        $types = RequestType::forCategory(
            $studentRequest->category,
            $studentRequest->student->user->type_etudiant
        );

        return view('admin.requests.show', compact('studentRequest', 'types'));
    }

    /* ── Aperçu & téléchargement (admin voit l'original) ── */

    public function previewFile($fileId)
    {
        $file = RequestFile::findOrFail($fileId);
        $path = Storage::path($file->file_path);

        abort_unless(file_exists($path), 404, 'Fichier introuvable.');

        return response()->file($path, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'inline; filename="' . $file->original_filename . '"',
        ]);
    }

    public function downloadFile($fileId)
    {
        $file = RequestFile::findOrFail($fileId);
        return Storage::download($file->file_path, $file->original_filename);
    }

    /* ════════════════════════════════════════════════════════
     |  VALIDATION
     |  - Pour DÉPÔT    : tamponne les fichiers has_stamp=true
     |  - Pour les autres : enregistre la date de rendez-vous
     ════════════════════════════════════════════════════════ */

    public function validate(Request $request, StudentRequest $studentRequest)
    {
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

        // Charger les fichiers avec leur type
        $studentRequest->load('files.requestType');

        $stampedCount = 0;
        $stampFailed = [];

        foreach ($studentRequest->files as $file) {

            // ── Tamponnage : uniquement dépôt + has_stamp=true + PDF ──
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

                $ok = $this->stampService->stampPdf($absIn, $absOut);

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

        return redirect()
            ->route('admin.requests.index', ['category' => $studentRequest->category])
            ->with('success', 'Demande renvoyée pour correction.');
    }

    /* ── Rejet définitif ─────────────────────────────────── */

    public function rejectFinal(Request $request, StudentRequest $studentRequest)
    {
        $request->validate(['rejection_message' => 'required|string|min:10']);

        $studentRequest->update([
            'status' => 'rejeté',
            'rejection_message' => $request->rejection_message,
            'validated_by' => Auth::id(),
            'validated_at' => now(),
        ]);

        return redirect()
            ->route('admin.requests.index', ['category' => $studentRequest->category])
            ->with('success', 'Demande rejetée définitivement.');
    }
}
