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

    public function updateStatus(Request $request, Candidature $candidature)
    {
        abort_unless($request->user()->coversCandidature($candidature), 403,
            "Cette candidature est hors de votre périmètre d'affectation.");

        $data = $request->validate([
            'status'            => 'required|in:en_instruction,en_correction,admis,refuse',
            'admin_comment'     => 'nullable|string|max:1000',
            'parcours_admis_id' => 'nullable|exists:parcours,id',
        ]);

        // Admission : le parcours retenu est OBLIGATOIRE et doit être un des vœux
        $parcoursAdmis = null;
        if ($data['status'] === 'admis') {
            $parcoursAdmis = (int) ($data['parcours_admis_id'] ?? 0);
            $voeux = $candidature->choix()->pluck('parcours_id');
            if ($voeux->isEmpty() && $candidature->parcours_id) {
                $voeux = collect([$candidature->parcours_id]);
            }
            if (!$parcoursAdmis || !$voeux->contains($parcoursAdmis)) {
                return back()->withErrors("Choisissez le parcours d'admission parmi les vœux du candidat.");
            }
        }
        // « À compléter » sans explication = candidat perdu
        if ($data['status'] === 'en_correction' && empty($data['admin_comment'])) {
            return back()->withErrors('Précisez dans le commentaire ce que le candidat doit corriger.');
        }

        $candidature->update([
            'status'            => $data['status'],
            'admin_comment'     => $data['admin_comment'] ?? null,
            'parcours_admis_id' => $data['status'] === 'admis' ? $parcoursAdmis : null,
            'traite_par'        => auth()->id(),
            'decision_at'       => in_array($data['status'], ['admis', 'refuse'], true) ? now() : null,
        ]);

        \App\Models\AuditLog::record('candidature.decision', $candidature,
            "Candidature {$candidature->reference} → {$data['status']} — "
                . trim(($candidature->nom ?? '') . ' ' . ($candidature->prenom ?? '')),
            ['status' => $data['status'], 'candidat' => trim(($candidature->nom ?? '') . ' ' . ($candidature->prenom ?? ''))]);

        return back()->with('success', 'Candidature mise à jour.');
    }

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
        ]);

        if ($data['statut'] === 'rejete' && blank($data['motif_rejet'])) {
            return back()->withErrors('Indiquez au candidat ce qui ne va pas avec cette pièce.');
        }

        $f->update([
            'statut'      => $data['statut'],
            'motif_rejet' => $data['statut'] === 'rejete' ? $data['motif_rejet'] : null,
            'verifie_par' => $request->user()->id,
            'verifie_at'  => now(),
        ]);

        \App\Models\AuditLog::record('candidature.piece', $f->candidature,
            "Pièce « " . ($f->requestType->label ?? 'document') . " » → "
                . (CandidatureFile::$statuts[$data['statut']] ?? $data['statut'])
                . " ({$f->candidature->reference})",
            ['statut' => $data['statut'], 'motif' => $f->motif_rejet]);

        return back()->with('success', 'Pièce mise à jour.');
    }

    public function previewFile($file)
    {
        $f = $this->fichierAutorise($file);
        $path = Storage::path($f->file_path);
        abort_unless(file_exists($path), 404, 'Fichier introuvable.');

        return response()->file($path, [
            'Content-Type'        => $f->mime_type,
            'Content-Disposition' => 'inline; filename="' . $f->original_filename . '"',
        ]);
    }

    public function downloadFile($file)
    {
        $f = $this->fichierAutorise($file);

        return Storage::download($f->file_path, $f->original_filename);
    }

    /** Une pièce n'est consultable que si la candidature est dans le périmètre. */
    private function fichierAutorise($file): CandidatureFile
    {
        $f = CandidatureFile::with('candidature.choix')->findOrFail($file);
        abort_unless($f->candidature && request()->user()->coversCandidature($f->candidature), 403,
            "Cette candidature est hors de votre périmètre d'affectation.");

        return $f;
    }
}
