<?php

namespace App\Http\Controllers;

use App\Models\RequestFile;
use App\Models\RequestType;
use App\Models\StudentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StudentRequestController extends Controller
{
    private function tabRedirect(string $tab): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('student.dashboard', ['tab' => $tab]);
    }

    public function create(Request $request)
    {
        /* Le parcours decide du niveau, et le niveau decide des pieces :
           sans lui on ne saurait pas quoi reclamer. Le depot le recueille
           par son assistant ; les autres procedures le demandent ici. */
        $request->validate([
            'category'    => ['required', \Illuminate\Validation\Rule::exists('procedures', 'code')],
            'parcours_id' => ['nullable', 'exists:parcours,id'],
        ]);

        $student = Auth::user()->student;
        $typeEtudiant = Auth::user()->type_etudiant;
        $category = $request->category;

        // ── Fenêtre d'ouverture : TOUTE création exige que l'opération soit ouverte ──
        if (!\App\Models\AnneeAcademique::operationOuverte($category)) {
            $label = \App\Models\Procedure::labels()[$category] ?? $category;
            return $this->tabRedirect($category)
                ->withErrors("« {$label} » est actuellement fermé. Cette opération n'est possible que pendant la période d'ouverture définie par l'administration.");
        }

        // Le dépôt passe désormais par l'assistant (choix Faculté → Parcours → Année → Semestre)
        if ($category === 'depot') {
            return redirect()->route('student.depot.start');
        }

        if ($typeEtudiant === 'nouveau' && $category !== 'depot') {
            return $this->tabRedirect($category)
                ->withErrors('Seuls les anciens étudiants peuvent faire ce type de demande.');
        }

        $existing = StudentRequest::where('student_id', $student->id)
            ->where('category', $category)
            ->whereIn('status', ['brouillon', 'soumis', 'en_correction'])
            ->first();

        if ($existing) {
            return $this->tabRedirect($category)
                ->with('info', 'Vous avez déjà une demande en cours pour cette catégorie.');
        }

        // Toute demande est rattachée à l'année académique active
        $annee = \App\Models\AnneeAcademique::active();
        if (!$annee) {
            return $this->tabRedirect($category)
                ->withErrors("Aucune année académique active. Contactez l'administration.");
        }

        $parcoursId = $request->input('parcours_id');
        if (!$parcoursId) {
            return $this->tabRedirect($category)->withErrors(
                "Indiquez votre parcours : c'est lui qui determine les pieces a fournir."
            );
        }

        StudentRequest::create([
            'student_id' => $student->id,
            'category' => $category,
            'parcours_id' => $parcoursId,
            'annee_academique_id' => $annee->id,
            'status' => 'brouillon',
            'version' => 1,
        ]);

        return $this->tabRedirect($category)->with('success', 'Demande créée. Uploadez vos fichiers.');
    }

    public function uploadFile(Request $request)
    {
        $request->validate([
            'student_request_id' => 'required|exists:student_requests,id',
            'request_type_id' => 'required|exists:request_types,id',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $sr = StudentRequest::findOrFail($request->student_request_id);
        $tab = $sr->category;
        $this->authorizeRequest($sr);

        if (!$sr->canEdit()) {
            return $this->ajaxOrBack($request, false, 'Cette demande ne peut plus être modifiée.', $tab);
        }

        $type = RequestType::findOrFail($request->request_type_id);

        /* La pièce doit être de celles que CE dossier attend. Sans ce
           contrôle, un identifiant de pièce pris dans une autre procédure
           ou un autre niveau était accepté : le fichier se rangeait dans
           le dossier sous une étiquette qui n'a rien à y faire. */
        if (!$sr->typesAAfficher()->contains('id', $type->id)) {
            return $this->ajaxOrBack($request, false,
                "Cette pièce n'est pas attendue pour ce dossier.", $tab);
        }

        $existing = $sr->files()->where('request_type_id', $type->id)->first();

        if ($existing?->is_locked) {
            return $this->ajaxOrBack($request, false, "« {$type->label} » a été validé et ne peut plus être remplacé.", $tab);
        }

        if ($existing) {
            $this->effacerFichiers($existing);
            $existing->delete();
        }

        $file = $request->file('file');

        // Le type est lu dans le CONTENU du fichier, pas dans son nom.
        if (\App\Support\FichierSur::type($file) === null) {
            return $this->ajaxOrBack($request, false,
                'Format non accepté : seuls les fichiers PDF, JPG et PNG sont admis.', $tab);
        }

        // Nom de stockage assaini, extension tirée du contenu : jamais le
        // nom client brut dans un chemin, jamais son extension sur parole.
        $storedName = \App\Support\FichierSur::nomDeStockage($file);
        $path = $file->storeAs('requests/' . $sr->id, $storedName, 'local');

        RequestFile::create([
            'student_request_id' => $sr->id,
            'request_type_id' => $type->id,
            'original_filename' => \App\Support\FichierSur::nomOriginal($file),
            'stored_filename' => $storedName,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return $this->ajaxOrBack($request, true, "« {$type->label} » ajouté.", $tab);
    }

    /**
     * Efface les fichiers du disque, sauf ceux encore référencés par une
     * autre pièce : un chemin partagé effacé aveuglément laisserait l'autre
     * enregistrement pointer vers le vide.
     */
    private function effacerFichiers(RequestFile $file): void
    {
        foreach ([$file->file_path, $file->stamped_file_path] as $chemin) {
            if (blank($chemin)) {
                continue;
            }
            $partage = RequestFile::where('id', '!=', $file->id)
                ->where(fn ($q) => $q->where('file_path', $chemin)
                                     ->orWhere('stamped_file_path', $chemin))
                ->exists();

            if (!$partage) {
                Storage::disk('local')->delete($chemin);
            }
        }
    }

    /**
     * Assainit un nom de fichier client : lettres/chiffres/._- uniquement
     * (bloque traversée de chemin, caractères de contrôle, etc.).
     */
    private function safeFilename(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?: 'document';

        return substr($base, 0, 100) . ($ext ? '.' . preg_replace('/[^a-z0-9]/', '', $ext) : '');
    }

    /** Réponse JSON si AJAX, sinon redirection classique avec message. */
    private function ajaxOrBack(Request $request, bool $ok, string $message, string $tab)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }
        return $ok
            ? $this->tabRedirect($tab)->with('success', $message)
            : $this->tabRedirect($tab)->withErrors($message);
    }

    public function deleteFile(Request $request, $fileId)
    {
        $file = RequestFile::findOrFail($fileId);
        $sr = $file->studentRequest;
        $tab = $sr->category;
        $this->authorizeRequest($sr);

        if ($file->is_locked) {
            return $this->ajaxOrBack($request, false, 'Ce fichier a été validé et ne peut pas être supprimé.', $tab);
        }
        if (!$sr->canEdit()) {
            return $this->ajaxOrBack($request, false, 'Cette demande ne peut plus être modifiée.', $tab);
        }

        $this->effacerFichiers($file);
        $file->delete();

        return $this->ajaxOrBack($request, true, 'Fichier supprimé.', $tab);
    }

    /* ── Annuler / recommencer un dépôt encore modifiable ──
     |  L'étudiant peut supprimer un brouillon (avant soumission)
     |  pour repartir de zéro : contexte + pièces + études effacés.
     */
    public function cancelRequest(Request $request, StudentRequest $studentRequest)
    {
        $this->authorizeRequest($studentRequest);
        $tab = $studentRequest->category;

        // Seul un brouillon (jamais soumis) peut être annulé par l'étudiant
        if ($studentRequest->status !== 'brouillon') {
            return $this->tabRedirect($tab)
                ->withErrors("Ce dossier a déjà été soumis et ne peut plus être annulé. Contactez l'administration si besoin.");
        }

        // Supprimer les pièces stockées puis l'enregistrement
        foreach ($studentRequest->files as $file) {
            $this->effacerFichiers($file);
        }
        $studentRequest->files()->delete();
        $studentRequest->etudesAnterieures()->delete();
        $studentRequest->delete();

        return $this->tabRedirect($tab)->with('success', 'Dépôt annulé. Vous pouvez en démarrer un nouveau.');
    }

    public function submit(Request $request)
    {
        $request->validate(['student_request_id' => 'required|exists:student_requests,id']);

        $sr = StudentRequest::findOrFail($request->student_request_id);
        $tab = $sr->category;
        $this->authorizeRequest($sr);

        if (!$sr->canEdit()) {
            return $this->tabRedirect($tab)->withErrors('Cette demande ne peut pas être soumise.');
        }

        // ── Fenêtre d'ouverture : une PREMIÈRE soumission exige l'opération ouverte.
        //    (Une demande renvoyée « en correction » par l'administration reste
        //    soumissible : le dossier était déjà entré pendant la période ouverte.)
        if ($sr->status === 'brouillon' && !\App\Models\AnneeAcademique::operationOuverte($sr->category)) {
            $label = \App\Models\Procedure::labels()[$sr->category] ?? $sr->category;
            return $this->tabRedirect($tab)
                ->withErrors("« {$label} » est actuellement fermé : la soumission n'est plus possible. Contactez l'administration si la période doit être prolongée.");
        }

        /* Certaines pieces obligatoires mettent des semaines a etre
           delivrees, et la periode de depot, elle, se ferme. L'usager
           peut s'engager a les fournir plus tard — mais seulement pour
           les pieces que l'administration a ouvertes a la derogation. */
        $engagements = array_map('intval', (array) $request->input('derogations', []));
        $derogeables = $sr->piecesDerogeables()->pluck('id')->all();
        $retenus = array_values(array_intersect($engagements, $derogeables));

        foreach ($retenus as $typeId) {
            \App\Models\Derogation::updateOrCreate(
                ['student_request_id' => $sr->id, 'request_type_id' => $typeId],
                ['statut' => 'engagee', 'engagee_at' => now(),
                 'levee_at' => null, 'levee_par' => null]
            );
        }

        $bloquantes = $sr->fresh()->piecesBloquantes();
        if ($bloquantes->isNotEmpty()) {
            $noms = $bloquantes->pluck('label')->implode(', ');

            return $this->tabRedirect($tab)->withErrors(
                "Il manque encore : {$noms}. Ces pièces sont exigées pour soumettre."
            );
        }

        // Pour un dépôt : l'étudiant doit avoir confirmé l'engagement (« Lu et approuvé »)
        if ($sr->category === 'depot' && !$request->boolean('engagement')) {
            return $this->tabRedirect($tab)
                ->withErrors("Vous devez lire et accepter l'engagement avant de soumettre votre dépôt.");
        }

        $sr->update([
            'status' => 'soumis',
            'submitted_at' => now(),
            'version' => $sr->version + 1,
            'engagement_accepte' => $sr->category === 'depot' ? true : $sr->engagement_accepte,
        ]);

        if ($retenus) {
            $libelles = \App\Models\RequestType::whereIn('id', $retenus)->pluck('label')->implode(', ');
            \App\Models\AuditLog::record('depot.derogation', $sr,
                "Dossier soumis sous dérogation — pièce(s) attendue(s) : {$libelles}");
        }

        // Frais de dossier : créés à la soumission si la procédure est payante
        $paiement = app(\App\Services\Paiement\PaiementService::class)
            ->creerPour($sr, $sr->category, $sr->annee_academique_id);

        $msg = 'Demande soumise avec succès.';
        if ($paiement && !$paiement->estPaye()) {
            $msg .= " Des frais de {$paiement->montant_formate} restent à régler : voir l'encadré « Frais de dossier ».";
        }

        return $this->tabRedirect($tab)->with('success', $msg);
    }

    /* ══════════════════════════════════════════════════
     |  TÉLÉCHARGEMENT — sert le fichier tamponné
     |  Utilise explicitement Storage::disk('local')
     ══════════════════════════════════════════════════ */
    public function downloadFile($fileId)
    {
        $file = RequestFile::with('studentRequest')->findOrFail($fileId);
        $sr = $file->studentRequest;
        $this->authorizeRequest($sr);

        // Uniquement pour le dépôt
        if ($sr->category !== 'depot') {
            abort(403, 'Le téléchargement est uniquement disponible pour les dépôts.');
        }

        // Le fichier doit être tamponné
        if (!$file->is_stamped || !$file->stamped_file_path) {
            abort(403, 'Ce document n\'est pas disponible au téléchargement. Seuls les documents tamponnés peuvent être téléchargés après validation.');
        }

        // Vérifier sur le disk 'local'
        if (!Storage::disk('local')->exists($file->stamped_file_path)) {
            abort(404, 'Fichier tamponné introuvable. Contactez l\'administration.');
        }

        return Storage::disk('local')->download(
            $file->stamped_file_path,
            'TAMPONNE_' . $file->original_filename
        );
    }

    private function authorizeRequest(StudentRequest $sr): void
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

    /* ══════════════════════════════════════════════════
     |  FRAIS DE DOSSIER — déclaration du versement
     ══════════════════════════════════════════════════ */

    public function declarerPaiement(Request $request, \App\Models\Paiement $paiement)
    {
        // Le paiement doit bien porter sur une demande de CET étudiant
        $sr = $paiement->payable;
        abort_unless($sr instanceof StudentRequest, 404);
        $this->authorizeRequest($sr);

        $data = $request->validate([
            'transaction_id' => 'required|string|max:80',
            'numero_payeur'  => 'nullable|string|max:30',
            'operateur'      => 'nullable|in:' . implode(',', array_keys(\App\Models\Paiement::$operateurs)),
        ], [], ['transaction_id' => 'identifiant de transaction']);

        $r = app(\App\Services\Paiement\PaiementService::class)->declarer(
            $paiement, trim($data['transaction_id']),
            $data['numero_payeur'] ?? null, $data['operateur'] ?? null
        );

        return $r['ok']
            ? $this->tabRedirect($sr->category)->with('success', $r['message'])
            : $this->tabRedirect($sr->category)->withErrors($r['message']);
    }
}
