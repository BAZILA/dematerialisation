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
        $request->validate(['category' => 'required|in:depot,attestation,diplome,releve,duplicat']);

        $student = Auth::user()->student;
        $typeEtudiant = Auth::user()->type_etudiant;
        $category = $request->category;

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

        StudentRequest::create([
            'student_id' => $student->id,
            'category' => $category,
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
            return $this->tabRedirect($tab)->withErrors('Cette demande ne peut plus être modifiée.');
        }

        $type = RequestType::findOrFail($request->request_type_id);
        $existing = $sr->files()->where('request_type_id', $type->id)->first();

        if ($existing?->is_locked) {
            return $this->tabRedirect($tab)
                ->withErrors("« {$type->label} » a été validé et ne peut plus être remplacé.");
        }

        if ($existing) {
            Storage::disk('local')->delete($existing->file_path);
            if ($existing->stamped_file_path) {
                Storage::disk('local')->delete($existing->stamped_file_path);
            }
            $existing->delete();
        }

        $file = $request->file('file');
        $storedName = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('requests/' . $sr->id, $storedName, 'local');

        RequestFile::create([
            'student_request_id' => $sr->id,
            'request_type_id' => $type->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedName,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return $this->tabRedirect($tab)->with('success', "« {$type->label} » uploadé avec succès.");
    }

    public function deleteFile($fileId)
    {
        $file = RequestFile::findOrFail($fileId);
        $sr = $file->studentRequest;
        $tab = $sr->category;
        $this->authorizeRequest($sr);

        if ($file->is_locked) {
            return $this->tabRedirect($tab)->withErrors('Ce fichier a été validé et ne peut pas être supprimé.');
        }
        if (!$sr->canEdit()) {
            return $this->tabRedirect($tab)->withErrors('Cette demande ne peut plus être modifiée.');
        }

        Storage::disk('local')->delete($file->file_path);
        if ($file->stamped_file_path) {
            Storage::disk('local')->delete($file->stamped_file_path);
        }
        $file->delete();

        return $this->tabRedirect($tab)->with('success', 'Fichier supprimé.');
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
        if (!$sr->allRequiredFilesUploaded()) {
            return $this->tabRedirect($tab)->withErrors('Uploadez tous les documents obligatoires avant de soumettre.');
        }

        $sr->update([
            'status' => 'soumis',
            'submitted_at' => now(),
            'version' => $sr->version + 1,
        ]);

        return $this->tabRedirect($tab)->with('success', 'Demande soumise avec succès.');
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
        if ($sr->student_id !== Auth::user()->student->id)
            abort(403);
    }
}
