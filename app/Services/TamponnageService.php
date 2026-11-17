<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\StudentRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Apposition du cachet (et remplissage du verso de la fiche) sur les pièces
 * d'un dossier validé.
 *
 * Extrait du contrôleur pour pouvoir tourner soit dans la requête (file
 * d'attente « sync »), soit dans un worker en arrière-plan — le traitement
 * est identique dans les deux cas.
 */
class TamponnageService
{
    public function __construct(
        private StampService $stampService,
        private FicheVersoService $versoService,
    ) {
    }

    /**
     * Traite toutes les pièces du dossier : tamponnage si demandé, puis
     * verrouillage. Idempotent — une pièce déjà tamponnée est ignorée.
     *
     * @return array{tamponnes:int, echecs:array<string>}
     */
    public function traiter(StudentRequest $sr, User $agent): array
    {
        $sr->loadMissing('files.requestType', 'etudesAnterieures');

        $isDepot   = $sr->category === 'depot';
        $tamponnes = 0;
        $echecs    = [];

        $cachetRel = Setting::get('cachet_path');
        $cachetAbs = $cachetRel && Storage::disk('local')->exists($cachetRel)
            ? Storage::disk('local')->path($cachetRel) : null;
        $signAbs = $agent->signature_path && Storage::disk('local')->exists($agent->signature_path)
            ? Storage::disk('local')->path($agent->signature_path) : null;

        $etudes = $sr->etudesAnterieures->map(fn ($e) => [
            'annee_debut'   => $e->annee_debut,
            'annee_fin'     => $e->annee_fin,
            'universite'    => $e->universite,
            'domaine'       => $e->domaine,
            'etablissement' => $e->etablissement,
            'parcours'      => $e->parcours,
            'credits'       => $e->credits,
        ])->all();

        foreach ($sr->files as $file) {

            $aTamponner = $isDepot && $file->requestType?->has_stamp === true && $file->isPdf();

            if (!$aTamponner) {
                $file->update(['is_locked' => true, 'is_valid' => true]);
                continue;
            }
            if ($file->is_stamped) {
                continue; // déjà fait (reprise d'un travail interrompu)
            }

            $dir         = 'requests/' . $sr->id . '/stamped';
            $stampedName = 'TAMPONNE_' . $file->stored_filename;
            $stampedPath = $dir . '/' . $stampedName;

            $absIn  = Storage::path($file->file_path);
            $absOut = Storage::path($stampedPath);

            $absDir = dirname($absOut);
            if (!is_dir($absDir)) {
                mkdir($absDir, 0755, true);
            }

            // La FICHE (fills_verso) : verso auto-rempli ; sinon simple cachet
            if ($file->requestType->fills_verso === true) {
                $ok = $this->versoService->fillVerso(
                    $absIn, $absOut, $etudes, $cachetAbs, $signAbs, $agent->staff_name,
                    optional($sr->submitted_at)->toDateTimeString(),
                    'DEP-' . $sr->id
                );
            } else {
                $ok = $this->stampService->stampPdf($absIn, $absOut, $agent->staff_name, $cachetAbs);
            }

            if ($ok) {
                $file->update([
                    'is_stamped'        => true,
                    'stamped_file_path' => $stampedPath,
                    'is_locked'         => true,
                    'is_valid'          => true,
                ]);
                $tamponnes++;
                Log::info("Tampon OK : {$file->original_filename} → {$stampedPath}");
            } else {
                $echecs[] = $file->original_filename;
                $file->update(['is_locked' => true, 'is_valid' => true]);
                Log::error("Tampon ÉCHEC : {$file->original_filename}");
            }
        }

        return ['tamponnes' => $tamponnes, 'echecs' => $echecs];
    }

    /** Le dossier attend-il encore un tamponnage ? (traitement en arrière-plan) */
    public static function enAttente(StudentRequest $sr): bool
    {
        if ($sr->category !== 'depot' || $sr->status !== 'validé') {
            return false;
        }

        return $sr->files
            ->filter(fn ($f) => $f->requestType?->has_stamp === true && $f->isPdf())
            ->contains(fn ($f) => !$f->is_stamped);
    }
}
