<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Candidature;
use App\Models\CandidatureFile;
use App\Models\Faculte;
use App\Models\Procedure;
use App\Models\RequestType;
use App\Models\Setting;
use Illuminate\Http\Request;

class PublicCandidatureController extends Controller
{
    /** Les candidatures sont-elles ouvertes ? (module activé + procédure active + fenêtre ouverte) */
    private function estOuverte(): bool
    {
        if (!Setting::get('candidatures.enabled', false)) {
            return false;
        }
        $proc = Procedure::where('code', 'candidature')->where('is_active', true)->first();
        if (!$proc) {
            return false;
        }
        $annee = AnneeAcademique::active();

        return $annee ? $annee->operationOpen('candidature') : false;
    }

    private function candidatureDocs()
    {
        return RequestType::where('category', 'candidature')->where('is_active', true)
            ->orderBy('order')->orderBy('id')->get();
    }

    public function create()
    {
        $ouverte = $this->estOuverte();
        $facultes = $ouverte
            ? Faculte::where('is_active', true)
                ->with(['departements.parcours' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('nom')->get()
            : collect();

        return view('public.candidature.create', [
            'ouverte'  => $ouverte,
            'facultes' => $facultes,
            'types'    => $ouverte ? $this->candidatureDocs() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->estOuverte(), 403, "Les candidatures ne sont pas ouvertes actuellement.");

        $data = $request->validate([
            'nom'            => 'required|string|max:100',
            'prenom'         => 'required|string|max:100',
            'sexe'           => 'required|in:M,F',
            'date_naissance' => 'required|date',
            'lieu_naissance' => 'required|string|max:150',
            'email'          => 'required|email|max:150',
            'telephone'      => 'nullable|string|max:30',
            'parcours_id'    => 'required|exists:parcours,id',
            'files'          => 'array',
            'files.*'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Pièces obligatoires présentes ?
        foreach ($this->candidatureDocs()->where('is_required', true) as $type) {
            if (!$request->hasFile("files.{$type->id}")) {
                return back()->withInput()->withErrors("La pièce « {$type->label} » est obligatoire.");
            }
        }

        $cand = Candidature::create([
            'reference'           => Candidature::generateReference(),
            'nom'                 => $data['nom'],
            'prenom'              => $data['prenom'],
            'sexe'                => $data['sexe'],
            'date_naissance'      => $data['date_naissance'],
            'lieu_naissance'      => $data['lieu_naissance'],
            'email'               => $data['email'],
            'telephone'           => $data['telephone'] ?? null,
            'parcours_id'         => $data['parcours_id'],
            'annee_academique_id' => optional(AnneeAcademique::active())->id,
            'status'              => 'soumise',
        ]);

        foreach ((array) $request->file('files', []) as $rtId => $file) {
            if (!$file) {
                continue;
            }
            $stored = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('candidatures/' . $cand->id, $stored, 'local');
            CandidatureFile::create([
                'candidature_id'    => $cand->id,
                'request_type_id'   => is_numeric($rtId) ? (int) $rtId : null,
                'original_filename' => $file->getClientOriginalName(),
                'stored_filename'   => $stored,
                'file_path'         => $path,
                'mime_type'         => $file->getMimeType(),
                'file_size'         => $file->getSize(),
            ]);
        }

        return redirect()->route('candidature.confirmation', $cand->reference);
    }

    public function confirmation(string $reference)
    {
        $candidature = Candidature::where('reference', $reference)->firstOrFail();

        return view('public.candidature.confirmation', compact('candidature'));
    }

    public function suiviForm()
    {
        return view('public.candidature.suivi', ['candidature' => null, 'cherche' => false]);
    }

    public function suivi(Request $request)
    {
        $data = $request->validate(['reference' => 'required|string|max:50']);
        $candidature = Candidature::with('parcours')->where('reference', trim($data['reference']))->first();

        return view('public.candidature.suivi', ['candidature' => $candidature, 'cherche' => true]);
    }
}
