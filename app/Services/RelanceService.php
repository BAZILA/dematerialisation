<?php

namespace App\Services;

use App\Models\Candidature;
use App\Models\NotificationEnvoyee;
use App\Models\Setting;
use App\Models\StudentRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * Relance des dossiers laissés « à compléter ».
 *
 * Un dossier renvoyé à son auteur reste souvent en l'état : l'usager n'a
 * pas vu le message, ou l'a oublié. Sans rappel, le dossier meurt en
 * silence et la scolarité le découvre trop tard. Cette relance ne crée
 * aucune donnée nouvelle : elle relit l'historique des notifications pour
 * savoir qui a déjà été relancé, et quand.
 */
class RelanceService
{
    public function __construct(private NotificationService $notifications)
    {
    }

    /** La relance automatique est-elle armée ? */
    public function actif(): bool
    {
        return (bool) Setting::get('relances.enabled', false);
    }

    /** Jours d'inaction avant le premier rappel. */
    public function delaiJours(): int
    {
        return max(1, (int) Setting::get('relances.delai_jours', 3));
    }

    /** Délai minimum entre deux rappels d'un même dossier. */
    public function intervalleJours(): int
    {
        return max(1, (int) Setting::get('relances.intervalle_jours', 7));
    }

    /** Au-delà, on cesse : le dossier relève d'un traitement manuel. */
    public function maxRelances(): int
    {
        return max(1, (int) Setting::get('relances.max', 3));
    }

    /**
     * Parcourt les dossiers en attente et relance ceux qui le méritent.
     *
     * @param  bool  $simulation  n'envoie rien, se contente de lister
     * @return array{candidatures:array, depots:array, ignores:int}
     */
    public function executer(bool $simulation = false): array
    {
        $rapport = ['candidatures' => [], 'depots' => [], 'ignores' => 0];

        foreach ($this->candidaturesEnAttente() as $cand) {
            $verdict = $this->evaluer($cand, $cand->updated_at);

            if (!$verdict['relancer']) {
                $rapport['ignores']++;
                continue;
            }

            if (!$simulation) {
                $this->notifications->notifier('candidature.relance', [
                    'email'     => $cand->email,
                    'telephone' => $cand->telephone,
                    'nom'       => $cand->full_name,
                ], [
                    'reference' => $cand->reference,
                    'jours'     => $verdict['jours'],
                    'motif'     => $this->motifCandidature($cand),
                ], $cand);
            }

            $rapport['candidatures'][] = [
                'reference' => $cand->reference,
                'nom'       => $cand->full_name,
                'contact'   => $cand->email ?: $cand->telephone,
                'jours'     => $verdict['jours'],
                'rappel'    => $verdict['deja'] + 1,
            ];
        }

        foreach ($this->depotsEnAttente() as $dep) {
            $verdict = $this->evaluer($dep, $dep->updated_at);

            if (!$verdict['relancer']) {
                $rapport['ignores']++;
                continue;
            }

            $etudiant = $dep->student;
            $contact  = $etudiant?->user;

            if (!$simulation) {
                $this->notifications->notifier('depot.relance', [
                    'email'     => $contact?->email,
                    'telephone' => $etudiant?->telephone,
                    'nom'       => $etudiant?->full_name ?? $contact?->staff_name ?? '',
                ], [
                    'procedure' => $this->libelleProcedure($dep),
                    'jours'     => $verdict['jours'],
                    'motif'     => $dep->admin_comment ?: 'Des pièces de votre dossier doivent être remplacées.',
                ], $dep, $contact?->id);
            }

            $rapport['depots'][] = [
                'reference' => $dep->reference ?? ('#' . $dep->id),
                'nom'       => $etudiant?->full_name ?? '—',
                'contact'   => $contact?->email ?? '—',
                'jours'     => $verdict['jours'],
                'rappel'    => $verdict['deja'] + 1,
            ];
        }

        return $rapport;
    }

    /**
     * Faut-il relancer ce dossier ? On tient compte de trois choses :
     * l'ancienneté de l'inaction, le nombre de rappels déjà envoyés, et
     * la date du dernier — pour ne pas harceler.
     *
     * @return array{relancer:bool, jours:int, deja:int, raison:string}
     */
    private function evaluer(Model $dossier, $depuis): array
    {
        $jours = $depuis ? (int) $depuis->diffInDays(now()) : 0;

        if ($jours < $this->delaiJours()) {
            return ['relancer' => false, 'jours' => $jours, 'deja' => 0, 'raison' => 'trop récent'];
        }

        $rappels = NotificationEnvoyee::where('concerne_type', $dossier->getMorphClass())
            ->where('concerne_id', $dossier->getKey())
            ->where('evenement', 'LIKE', '%.relance')
            ->orderByDesc('created_at')
            ->get();

        // Un canal éteint produit une trace « suspendue » : elle ne compte
        // pas comme un rappel, sinon on épuiserait le quota sans rien envoyer.
        $reels = $rappels->where('statut', '!=', 'suspendu');
        $deja  = $reels->pluck('created_at')->map(fn ($d) => $d->format('Y-m-d H:i'))->unique()->count();

        if ($deja >= $this->maxRelances()) {
            return ['relancer' => false, 'jours' => $jours, 'deja' => $deja, 'raison' => 'quota atteint'];
        }

        $dernier = $reels->first()?->created_at;
        if ($dernier && $dernier->diffInDays(now()) < $this->intervalleJours()) {
            return ['relancer' => false, 'jours' => $jours, 'deja' => $deja, 'raison' => 'relancé récemment'];
        }

        return ['relancer' => true, 'jours' => $jours, 'deja' => $deja, 'raison' => ''];
    }

    /** Candidatures dont au moins une section attend le candidat. */
    private function candidaturesEnAttente()
    {
        return Candidature::with('choix.parcours')
            ->where(function ($q) {
                $q->where('status', 'en_correction')
                  ->orWhereHas('choix', fn ($c) => $c->where('statut', 'en_correction'));
            })
            ->whereNotNull('email')
            ->get();
    }

    /** Dépôts renvoyés à l'étudiant pour correction. */
    private function depotsEnAttente()
    {
        return StudentRequest::with('student.user')
            ->where('status', 'en_correction')
            ->get();
    }

    /**
     * Ce qu'on redit au candidat : le motif de la section concernée, ou à
     * défaut un rappel générique. Un rappel sans motif n'aide personne.
     */
    private function motifCandidature(Candidature $cand): string
    {
        $motifs = $cand->choix
            ->where('statut', 'en_correction')
            ->filter(fn ($s) => filled($s->motif))
            ->map(fn ($s) => '— ' . ($s->parcours?->nom ?? 'Votre parcours') . ' : ' . $s->motif);

        if ($motifs->isNotEmpty()) {
            return $motifs->implode("\n");
        }

        return filled($cand->admin_comment)
            ? $cand->admin_comment
            : 'Des pièces de votre dossier doivent être complétées ou remplacées.';
    }

    private function libelleProcedure(StudentRequest $dep): string
    {
        return \App\Models\Procedure::labels()[$dep->category] ?? 'dossier';
    }
}
