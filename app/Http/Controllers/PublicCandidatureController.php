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

    private function paiements(): \App\Services\Paiement\PaiementService
    {
        return app(\App\Services\Paiement\PaiementService::class);
    }

    /* ── Essais sur la date de naissance ───────────────────────────
     |  Modifier un dossier ne demande que sa référence et une date de
     |  naissance. La référence circule (elle est imprimée, envoyée par
     |  courriel) ; la date, elle, se devine : il n'y a que quelques
     |  milliers de dates plausibles. La cadence par adresse IP posée
     |  sur les routes ne suffit pas — il suffit d'en changer.
     |  Ici, c'est le DOSSIER visé qui se ferme après quelques erreurs,
     |  d'où que viennent les essais.
     */
    private const ESSAIS_PAR_DOSSIER = 6;
    private const VERROU_SECONDES = 900;

    private function cleEssais(string $reference): string
    {
        return 'candidature-acces:' . sha1(mb_strtoupper(trim($reference)));
    }

    /** Réponse de refus si ce dossier a subi trop d'essais, sinon null. */
    private function tropDEssais(string $reference)
    {
        $cle = $this->cleEssais($reference);

        if (!\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($cle, self::ESSAIS_PAR_DOSSIER)) {
            return null;
        }

        $minutes = (int) ceil(\Illuminate\Support\Facades\RateLimiter::availableIn($cle) / 60);

        return back()->withErrors(
            "Trop de tentatives sur ce dossier. Réessayez dans {$minutes} minute(s).");
    }

    private function compterEchec(string $reference): void
    {
        \Illuminate\Support\Facades\RateLimiter::hit($this->cleEssais($reference), self::VERROU_SECONDES);
    }

    private function oublierEchecs(string $reference): void
    {
        \Illuminate\Support\Facades\RateLimiter::clear($this->cleEssais($reference));
    }

    /**
     * Déclaration d'un versement par le candidat.
     * Authentification légère : référence + date de naissance, comme pour
     * la complétion du dossier.
     */
    public function declarerPaiement(Request $request)
    {
        $data = $request->validate([
            'reference'      => 'required|string|max:50',
            'date_naissance' => 'required|date',
            'section_id'     => 'required|integer',
            'transaction_id' => 'required|string|max:80',
            'numero_payeur'  => 'nullable|string|max:30',
            'operateur'      => 'nullable|in:' . implode(',', array_keys(\App\Models\Paiement::$operateurs)),
        ], [], [
            'date_naissance' => 'date de naissance',
            'transaction_id' => 'identifiant de transaction',
            'section_id'     => 'parcours concerné',
        ]);

        if ($refus = $this->tropDEssais($data['reference'])) {
            return $refus;
        }

        $cand = Candidature::where('reference', trim($data['reference']))->first();
        if (!$cand || $cand->date_naissance?->format('Y-m-d') !== $request->date('date_naissance')->format('Y-m-d')) {
            $this->compterEchec($data['reference']);

            return back()->withErrors('Référence ou date de naissance incorrecte.');
        }
        $this->oublierEchecs($data['reference']);

        $section = $cand->choix()->where('id', (int) $request->input('section_id'))->first();
        $paiement = $section ? $this->paiements()->pour($section) : null;
        if (!$paiement) {
            return back()->withErrors("Aucun frais n'est attendu pour ce parcours.");
        }

        $r = $this->paiements()->declarer(
            $paiement, trim($data['transaction_id']),
            $data['numero_payeur'] ?? null, $data['operateur'] ?? null
        );

        return $r['ok']
            ? back()->with('success', $r['message'])
            : back()->withErrors($r['message']);
    }

    /**
     * Section à laquelle rattacher une pièce.
     * Pièce commune → null (partagée par toutes les sections).
     *
     * @param  array<int,\App\Models\CandidatureChoix>  $sections  indexées par parcours_id
     */
    private function sectionPour(?RequestType $type, array $sections): ?\App\Models\CandidatureChoix
    {
        if (!$type || $type->parcours->isEmpty()) {
            return null;
        }
        foreach ($type->parcours as $p) {
            if (isset($sections[$p->id])) {
                return $sections[$p->id];
            }
        }

        return null;
    }

    /** Stocke une pièce sur le disque privé et l'enregistre. */
    private function rangerPiece(Candidature $cand, $file, int $rtId, ?\App\Models\CandidatureChoix $section): CandidatureFile
    {
        // Le type est lu dans le CONTENU du fichier, pas dans son nom.
        abort_if(\App\Support\FichierSur::type($file) === null, 422,
            'Format non accepté : seuls les fichiers PDF, JPG et PNG sont admis.');

        // Nom de stockage assaini, extension tirée du contenu (le nom
        // client brut ne va jamais dans un chemin).
        $stored = \App\Support\FichierSur::nomDeStockage($file);
        $path = $file->storeAs('candidatures/' . $cand->id, $stored, 'local');

        return CandidatureFile::create([
            'candidature_id'       => $cand->id,
            'candidature_choix_id' => $section?->id,
            'request_type_id'      => $rtId,
            'original_filename'    => \App\Support\FichierSur::nomOriginal($file),
            'stored_filename'      => $stored,
            'file_path'            => $path,
            'mime_type'            => $file->getMimeType(),
            'file_size'            => $file->getSize(),
        ]);
    }

    /**
     * Efface le fichier du disque, sauf si une autre pièce s'appuie encore
     * dessus. Deux enregistrements peuvent partager un même chemin ; une
     * suppression aveugle laisserait l'autre pointer dans le vide.
     */
    private function supprimerFichierPhysique(CandidatureFile $piece): void
    {
        $partage = CandidatureFile::where('file_path', $piece->file_path)
            ->where('id', '!=', $piece->id)->exists();

        if (!$partage) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($piece->file_path);
        }
    }

    /**
     * Pièces d'un parcours réparties en deux : les COMMUNES (déjà au
     * dossier, jamais redemandées) et les SPÉCIFIQUES à ce parcours.
     */
    public function piecesPourParcours(Candidature $cand, int $parcoursId): array
    {
        $docs = RequestType::pourCandidature([$parcoursId]);
        $deja = $cand->files->keyBy('request_type_id');

        $communes = $docs->filter(fn ($t) => $t->parcours->isEmpty())->values();
        foreach ($communes as $t) {
            $t->fichier_existant = $deja->get($t->id);
        }

        return [
            'communes'    => $communes,
            'specifiques' => $docs->filter(fn ($t) => $t->parcours->isNotEmpty())->values(),
        ];
    }

    /**
     * Postuler à un PARCOURS SUPPLÉMENTAIRE.
     *
     * Le candidat ne refournit que les pièces propres à ce parcours :
     * les pièces communes déjà au dossier sont reprises telles quelles.
     */
    public function ajouterParcours(Request $request)
    {
        abort_unless($this->estOuverte(), 403, "Les candidatures ne sont pas ouvertes actuellement.");

        $data = $request->validate([
            'reference'      => 'required|string|max:50',
            'date_naissance' => 'required|date',
            'parcours_id'    => 'required|exists:parcours,id',
            'files'          => 'array',
            'files.*'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [], ['date_naissance' => 'date de naissance', 'parcours_id' => 'parcours']);

        if ($refus = $this->tropDEssais($data['reference'])) {
            return $refus;
        }

        $cand = Candidature::with('choix', 'files')->where('reference', trim($data['reference']))->first();
        if (!$cand || $cand->date_naissance?->format('Y-m-d') !== $request->date('date_naissance')->format('Y-m-d')) {
            $this->compterEchec($data['reference']);

            return back()->withErrors('Référence ou date de naissance incorrecte.');
        }
        $this->oublierEchecs($data['reference']);

        $parcoursId = (int) $data['parcours_id'];
        if ($cand->choix->contains('parcours_id', $parcoursId)) {
            return back()->withErrors('Vous avez déjà postulé à ce parcours.');
        }

        // Seules les pièces PROPRES au nouveau parcours sont exigées
        $pieces = $this->piecesPourParcours($cand, $parcoursId);
        foreach ($pieces['specifiques']->where('is_required', true) as $type) {
            if (!$request->hasFile("files.{$type->id}")) {
                return back()->withErrors("La pièce « {$type->label} » est obligatoire pour ce parcours.");
            }
        }

        $section = \App\Models\CandidatureChoix::create([
            'candidature_id' => $cand->id,
            'parcours_id'    => $parcoursId,
            'ordre'          => ((int) $cand->choix->max('ordre')) + 1,
            'soumis_at'      => now(),
        ]);

        $autorisees = $pieces['specifiques']->pluck('id')->all();
        foreach ((array) $request->file('files', []) as $rtId => $file) {
            if (!$file || !is_numeric($rtId) || !in_array((int) $rtId, $autorisees, true)) {
                continue;
            }
            $this->rangerPiece($cand, $file, (int) $rtId, $section);
        }

        // Quittance propre à cette nouvelle section
        app(\App\Services\Paiement\PaiementService::class)
            ->creerPour($section, 'candidature', $cand->annee_academique_id);

        // Un dossier encore en cours repart en instruction : il y a du nouveau
        if (!in_array($cand->status, ['admis', 'refuse'], true)) {
            $cand->update(['status' => 'soumise']);
        }

        \App\Models\AuditLog::record('candidature.parcours_ajoute', $cand,
            "Parcours ajouté à la candidature {$cand->reference} : "
                . (\App\Models\Parcours::find($parcoursId)?->nom ?? $parcoursId),
            ['parcours_id' => $parcoursId]);

        return back()->with('success',
            "Votre candidature à ce parcours est enregistrée. Les pièces communes de votre dossier ont été reprises automatiquement.");
    }

    /** Parcours auxquels le candidat n'a pas encore postulé. */
    private function parcoursDisponibles(Candidature $cand)
    {
        $deja = $cand->choix->pluck('parcours_id')->all();

        return Faculte::where('is_active', true)
            ->with(['departements.parcours' => fn ($q) => $q->where('is_active', true)
                ->whereNotIn('id', $deja ?: [0])->orderBy('nom')])
            ->orderBy('type')->orderBy('nom')->get();
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
            'date_naissance' => 'required|date|before:today|after:1940-01-01',
            'lieu_naissance' => 'required|string|max:150',
            'email'          => 'required|email|max:150',
            'telephone'      => 'nullable|string|max:30',
            // Une SECTION par parcours visé — pas de classement, ce sont
            // des candidatures parallèles instruites indépendamment.
            'sections'                 => 'required|array|min:1|max:6',
            'sections.*.parcours_id'   => 'required|exists:parcours,id',
            'sections.*.files'         => 'array',
            'sections.*.files.*'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            // Pièces du dossier commun
            'files'          => 'array',
            'files.*'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'date_naissance.before' => "La date de naissance doit être antérieure à aujourd'hui.",
            'date_naissance.after'  => 'Vérifiez votre date de naissance.',
        ], ['sections' => 'parcours visés', 'sections.*.parcours_id' => 'parcours']);

        // Un même parcours ne peut pas faire l'objet de deux sections
        $choix = collect($data['sections'])->pluck('parcours_id')->map(fn ($v) => (int) $v)->values();
        if ($choix->duplicates()->isNotEmpty()) {
            return $this->echec($request, ['sections' => 'Vous avez sélectionné deux fois le même parcours.']);
        }

        $tousDocs = $this->docsPourChoix($choix->all());
        $manquantes = [];

        // 1. Pièces COMMUNES : exigées une seule fois pour tout le dossier
        $communes = $tousDocs->filter(fn ($t) => $t->parcours->isEmpty());
        foreach ($communes->where('is_required', true) as $type) {
            if (!$request->hasFile("files.{$type->id}")) {
                $manquantes["files.{$type->id}"] = "La pièce « {$type->label} » est obligatoire.";
            }
        }

        // 2. Pièces PROPRES : exigées section par section
        foreach ($data['sections'] as $i => $sectionSaisie) {
            $pid = (int) $sectionSaisie['parcours_id'];
            foreach (RequestType::pourCandidature([$pid]) as $type) {
                if ($type->parcours->isEmpty() || !$type->is_required) {
                    continue;
                }
                if (!$request->hasFile("sections.{$i}.files.{$type->id}")) {
                    $nom = \App\Models\Parcours::find($pid)?->nom ?? 'ce parcours';
                    $manquantes["sections.{$i}.files.{$type->id}"] =
                        "La pièce « {$type->label} » est obligatoire pour {$nom}.";
                }
            }
        }

        if ($manquantes) {
            return $this->echec($request, $manquantes);
        }
        $docsAutorises = $communes->pluck('id')->all();

        $cand = Candidature::create([
            'reference'           => Candidature::generateReference(),
            'nom'                 => $data['nom'],
            'prenom'              => $data['prenom'],
            'sexe'                => $data['sexe'],
            'date_naissance'      => $data['date_naissance'],
            'lieu_naissance'      => $data['lieu_naissance'],
            'email'               => $data['email'],
            'telephone'           => $data['telephone'] ?? null,
            'parcours_id'         => $choix->first(), // 1re section (compatibilité des écrans)
            'annee_academique_id' => optional(AnneeAcademique::active())->id,
            'status'              => 'soumise',
        ]);

        // Une SECTION par parcours visé
        $sections = [];
        $ordre = 1;
        foreach ($data['sections'] as $i => $sectionSaisie) {
            $pid = (int) $sectionSaisie['parcours_id'];
            $sections[$i] = \App\Models\CandidatureChoix::create([
                'candidature_id' => $cand->id,
                'parcours_id'    => $pid,
                'ordre'          => $ordre++,
                'soumis_at'      => now(),
            ]);
        }

        // Pièces COMMUNES : rattachées au dossier, partagées par toutes les sections
        foreach ((array) $request->file('files', []) as $rtId => $file) {
            if (!$file || !is_numeric($rtId) || !in_array((int) $rtId, $docsAutorises, true)) {
                continue;
            }
            $this->rangerPiece($cand, $file, (int) $rtId, null);
        }

        // Pièces PROPRES : rattachées à leur section
        foreach ((array) $request->file('sections', []) as $i => $bloc) {
            if (!isset($sections[$i]) || empty($bloc['files'])) {
                continue;
            }
            $autorisees = RequestType::pourCandidature([$sections[$i]->parcours_id])
                ->filter(fn ($t) => $t->parcours->isNotEmpty())->pluck('id')->all();

            foreach ((array) $bloc['files'] as $rtId => $file) {
                if (!$file || !is_numeric($rtId) || !in_array((int) $rtId, $autorisees, true)) {
                    continue;
                }
                $this->rangerPiece($cand, $file, (int) $rtId, $sections[$i]);
            }
        }

        // Frais : une quittance PAR SECTION (chaque parcours se paie séparément)
        $paiements = app(\App\Services\Paiement\PaiementService::class);
        foreach ($sections as $section) {
            $paiements->creerPour($section, 'candidature', $cand->annee_academique_id);
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
        return view('public.candidature.suivi', ['candidature' => null, 'cherche' => false, 'types' => collect(), 'parcoursDispo' => collect()]);
    }

    public function suivi(Request $request)
    {
        $data = $request->validate(['reference' => 'required|string|max:50']);
        $candidature = Candidature::with('choix.parcours.departement.faculte', 'choix.files.requestType',
            'choix.paiement', 'parcoursAdmis', 'files.requestType')
            ->where('reference', trim($data['reference']))->first();

        return view('public.candidature.suivi', [
            'candidature' => $candidature,
            'cherche'     => true,
            'types'       => $candidature ? $this->docsPourCandidature($candidature) : collect(),
            'parcoursDispo' => $candidature ? $this->parcoursDisponibles($candidature) : collect(),
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

        if ($refus = $this->tropDEssais($data['reference'])) {
            return $refus;
        }

        $cand = Candidature::where('reference', trim($data['reference']))->first();

        if (!$cand || $cand->date_naissance?->format('Y-m-d') !== $request->date('date_naissance')->format('Y-m-d')) {
            $this->compterEchec($data['reference']);

            return back()->withErrors('Référence ou date de naissance incorrecte.');
        }
        $this->oublierEchecs($data['reference']);
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
            // Remplace l'ancienne pièce du même type, en conservant la
            // section d'origine : une pièce propre à un parcours ne doit
            // pas devenir commune parce qu'elle a été renvoyée.
            $ancien  = $cand->files()->where('request_type_id', (int) $rtId)->first();
            $section = $ancien?->choix;
            if ($ancien) {
                $this->supprimerFichierPhysique($ancien);
                $ancien->delete();
            }
            if (!$section) {
                $type = RequestType::find((int) $rtId);
                $section = $this->sectionPour($type, $cand->choix->keyBy('parcours_id')->all());
            }

            $this->rangerPiece($cand, $file, (int) $rtId, $section);
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
        $candidature = Candidature::with('choix.parcours.departement.faculte', 'choix.files.requestType',
            'choix.paiement', 'parcoursAdmis', 'files.requestType')
            ->find($cand->id);

        return view('public.candidature.suivi', [
            'candidature' => $candidature,
            'cherche'     => true,
            'types'       => $this->docsPourCandidature($candidature),
            'parcoursDispo' => $this->parcoursDisponibles($candidature),
            'complete'    => true,
        ]);
    }
}
