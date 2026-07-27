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

    /** Toutes les pièces de candidature actives (avec leur portée). */
    private function candidatureDocs()
    {
        return RequestType::with('parcours')
            ->where('category', 'candidature')->where('is_active', true)
            ->orderBy('order')->orderBy('id')->get();
    }

    /**
     * Pièces réellement exigées d'un candidat : les pièces COMMUNES
     * plus celles PROPRES aux parcours qu'il a choisis.
     */
    private function docsPourChoix(array $parcoursIds)
    {
        return RequestType::pourCandidature($parcoursIds);
    }

    /** Idem, à partir d'une candidature déjà enregistrée. */
    private function docsPourCandidature(Candidature $cand)
    {
        $ids = $cand->choix->pluck('parcours_id')->filter()->all();
        if (!$ids && $cand->parcours_id) {
            $ids = [$cand->parcours_id];
        }

        return $this->docsPourChoix($ids);
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
            // Jusqu'à 3 parcours classés par préférence (le 1er est obligatoire)
            'parcours_ids'   => 'required|array|min:1|max:3',
            'parcours_ids.1' => 'required|exists:parcours,id',
            'parcours_ids.*' => 'nullable|exists:parcours,id',
            'files'          => 'array',
            'files.*'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [], ['parcours_ids.1' => '1er choix de parcours']);

        // Choix distincts, dans l'ordre saisi
        $choix = collect($data['parcours_ids'])->filter()->map(fn ($v) => (int) $v)->values();
        if ($choix->duplicates()->isNotEmpty()) {
            return $this->echec($request, ['parcours_ids' => 'Chaque parcours ne peut être choisi qu\'une seule fois.']);
        }

        // Pièces exigées = communes + celles propres aux parcours choisis
        $docs = $this->docsPourChoix($choix->all());
        $manquantes = [];
        foreach ($docs->where('is_required', true) as $type) {
            if (!$request->hasFile("files.{$type->id}")) {
                $manquantes["files.{$type->id}"] = "La pièce « {$type->label} » est obligatoire.";
            }
        }
        if ($manquantes) {
            return $this->echec($request, $manquantes);
        }
        $docsAutorises = $docs->pluck('id')->all();

        $cand = Candidature::create([
            'reference'           => Candidature::generateReference(),
            'nom'                 => $data['nom'],
            'prenom'              => $data['prenom'],
            'sexe'                => $data['sexe'],
            'date_naissance'      => $data['date_naissance'],
            'lieu_naissance'      => $data['lieu_naissance'],
            'email'               => $data['email'],
            'telephone'           => $data['telephone'] ?? null,
            'parcours_id'         => $choix->first(), // 1er choix (compatibilité)
            'annee_academique_id' => optional(AnneeAcademique::active())->id,
            'status'              => 'soumise',
        ]);

        foreach ($choix as $i => $pid) {
            \App\Models\CandidatureChoix::create([
                'candidature_id' => $cand->id,
                'parcours_id'    => $pid,
                'ordre'          => $i + 1,
            ]);
        }

        foreach ((array) $request->file('files', []) as $rtId => $file) {
            // On n'accepte que les pièces réellement demandées à ce candidat
            if (!$file || !is_numeric($rtId) || !in_array((int) $rtId, $docsAutorises, true)) {
                continue;
            }
            // Nom de stockage assaini (le nom client brut ne va jamais dans un chemin)
            $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'document';
            $ext  = preg_replace('/[^a-z0-9]/', '', strtolower($file->getClientOriginalExtension()));
            $stored = time() . '_' . substr($base, 0, 100) . ($ext ? '.' . $ext : '');
            $path = $file->storeAs('candidatures/' . $cand->id, $stored, 'local');
            CandidatureFile::create([
                'candidature_id'    => $cand->id,
                'request_type_id'   => (int) $rtId,
                'original_filename' => $file->getClientOriginalName(),
                'stored_filename'   => $stored,
                'file_path'         => $path,
                'mime_type'         => $file->getMimeType(),
                'file_size'         => $file->getSize(),
            ]);
        }

        $url = route('candidature.confirmation', $cand->reference);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'redirect' => $url])
            : redirect($url);
    }

    /**
     * Échec de soumission : en AJAX (barre de progression) on renvoie les
     * messages indexés par champ pour les afficher en face de la pièce ;
     * sinon on repart en arrière avec le sac d'erreurs habituel.
     */
    private function echec(Request $request, array $erreurs)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Votre dossier est incomplet.',
                'errors'  => array_map(fn ($m) => [$m], $erreurs),
            ], 422);
        }

        return back()->withInput()->withErrors($erreurs);
    }

    public function confirmation(string $reference)
    {
        $candidature = Candidature::where('reference', $reference)->firstOrFail();

        return view('public.candidature.confirmation', compact('candidature'));
    }

    public function suiviForm()
    {
        return view('public.candidature.suivi', ['candidature' => null, 'cherche' => false, 'types' => collect()]);
    }

    public function suivi(Request $request)
    {
        $data = $request->validate(['reference' => 'required|string|max:50']);
        $candidature = Candidature::with('choix.parcours.departement.faculte', 'parcoursAdmis', 'files.requestType')
            ->where('reference', trim($data['reference']))->first();

        return view('public.candidature.suivi', [
            'candidature' => $candidature,
            'cherche'     => true,
            'types'       => $candidature ? $this->docsPourCandidature($candidature) : collect(),
        ]);
    }

    /**
     * COMPLÉTION du dossier par le candidat (statut « À compléter ») :
     * il remplace ses pièces directement depuis la page de suivi, puis la
     * candidature repasse « Soumise » pour ré-instruction.
     * Authentification légère : référence + date de naissance.
     */
    public function completer(Request $request)
    {
        $data = $request->validate([
            'reference'      => 'required|string|max:50',
            'date_naissance' => 'required|date',
            'files'          => 'required|array|min:1',
            'files.*'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [], ['date_naissance' => 'date de naissance']);

        $cand = Candidature::where('reference', trim($data['reference']))->first();

        if (!$cand || $cand->date_naissance?->format('Y-m-d') !== $request->date('date_naissance')->format('Y-m-d')) {
            return back()->withErrors('Référence ou date de naissance incorrecte.');
        }
        if ($cand->status !== 'en_correction') {
            return back()->withErrors('Ce dossier n\'est pas ouvert à la complétion actuellement.');
        }

        $cand->load('choix', 'files');
        $docsAutorises = $this->docsPourCandidature($cand)->pluck('id')->all();
        // Une pièce déjà VALIDÉE par l'agent est verrouillée : on refuse de la toucher
        $verrouilles = $cand->files->where('statut', 'valide')->pluck('request_type_id')->filter()->all();

        $erreurs = [];
        $remplaces = 0;
        foreach ((array) $request->file('files', []) as $rtId => $file) {
            if (!$file || !is_numeric($rtId) || !in_array((int) $rtId, $docsAutorises, true)) {
                continue;
            }
            if (in_array((int) $rtId, $verrouilles, true)) {
                $erreurs["files.{$rtId}"] = 'Cette pièce a déjà été validée : elle ne peut plus être modifiée.';
                continue;
            }
            // Remplace l'ancienne pièce du même type
            $ancien = $cand->files()->where('request_type_id', (int) $rtId)->first();
            if ($ancien) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($ancien->file_path);
                $ancien->delete();
            }
            $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'document';
            $ext  = preg_replace('/[^a-z0-9]/', '', strtolower($file->getClientOriginalExtension()));
            $stored = time() . '_' . substr($base, 0, 100) . ($ext ? '.' . $ext : '');
            $path = $file->storeAs('candidatures/' . $cand->id, $stored, 'local');
            CandidatureFile::create([
                'candidature_id'    => $cand->id,
                'request_type_id'   => (int) $rtId,
                'original_filename' => $file->getClientOriginalName(),
                'stored_filename'   => $stored,
                'file_path'         => $path,
                'mime_type'         => $file->getMimeType(),
                'file_size'         => $file->getSize(),
            ]);
            $remplaces++;
        }

        // Erreurs par pièce : ré-affichées en face du champ concerné
        if ($erreurs) {
            return back()->withInput()->withErrors($erreurs);
        }
        if ($remplaces === 0) {
            return back()->withErrors('Aucun fichier fourni.');
        }

        // Le dossier complété repart en instruction
        $cand->update(['status' => 'soumise']);

        \App\Models\AuditLog::record('candidature.completee', $cand,
            "Candidature {$cand->reference} complétée par le candidat ({$remplaces} pièce(s) remplacée(s))",
            ['pieces' => $remplaces]);

        // Ré-affiche le suivi à jour
        $candidature = Candidature::with('choix.parcours.departement.faculte', 'parcoursAdmis', 'files.requestType')
            ->find($cand->id);

        return view('public.candidature.suivi', [
            'candidature' => $candidature,
            'cherche'     => true,
            'types'       => $this->docsPourCandidature($candidature),
            'complete'    => true,
        ]);
    }
}
