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
        $query = Candidature::with('parcours')->withCount('files');

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

        $candidatures = $query->orderByRaw("CASE status WHEN 'soumise' THEN 0 WHEN 'en_instruction' THEN 1 WHEN 'en_correction' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('admin.candidatures.index', compact('candidatures'));
    }

    public function show(Candidature $candidature)
    {
        $candidature->load('files.requestType', 'parcours.departement.faculte', 'traiteur');

        return view('admin.candidatures.show', compact('candidature'));
    }

    public function updateStatus(Request $request, Candidature $candidature)
    {
        $data = $request->validate([
            'status'        => 'required|in:en_instruction,en_correction,admis,refuse',
            'admin_comment' => 'nullable|string|max:1000',
        ]);

        $candidature->update([
            'status'        => $data['status'],
            'admin_comment' => $data['admin_comment'] ?? null,
            'traite_par'    => auth()->id(),
            'decision_at'   => in_array($data['status'], ['admis', 'refuse'], true) ? now() : null,
        ]);

        return back()->with('success', 'Candidature mise à jour.');
    }

    public function previewFile($file)
    {
        $f = CandidatureFile::findOrFail($file);
        $path = Storage::path($f->file_path);
        abort_unless(file_exists($path), 404, 'Fichier introuvable.');

        return response()->file($path, [
            'Content-Type'        => $f->mime_type,
            'Content-Disposition' => 'inline; filename="' . $f->original_filename . '"',
        ]);
    }

    public function downloadFile($file)
    {
        $f = CandidatureFile::findOrFail($file);

        return Storage::download($f->file_path, $f->original_filename);
    }
}
