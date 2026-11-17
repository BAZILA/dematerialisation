<?php

namespace App\Services\Paiement;

use App\Models\AnneeAcademique;
use App\Models\AuditLog;
use App\Models\Paiement;
use App\Models\Procedure;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Règles métier des frais de dossier : création, déclaration par
 * l'usager, rapprochement par un agent.
 */
class PaiementService
{
    public function __construct(private PasserelleOperateur $passerelle)
    {
    }

    public function passerelle(): PasserelleOperateur
    {
        return $this->passerelle;
    }

    /** Le module de paiement est-il activé globalement ? */
    public function actif(): bool
    {
        return (bool) Setting::get('payments.enabled', false);
    }

    /** Montant exigé pour une procédure (0 = gratuit). */
    public function montantPour(string $procedureCode): int
    {
        if (!$this->actif()) {
            return 0;
        }
        $proc = Procedure::where('code', $procedureCode)->first();
        if (!$proc || !$proc->frais_actifs) {
            return 0;
        }

        return max(0, (int) $proc->frais_montant);
    }

    /**
     * Crée le paiement attaché à un dossier si la procédure est payante.
     * Idempotent : un dossier ne porte jamais deux paiements actifs.
     */
    public function creerPour(Model $payable, string $procedureCode, ?int $anneeId = null): ?Paiement
    {
        $montant = $this->montantPour($procedureCode);
        if ($montant <= 0) {
            return null;
        }

        $existant = $this->pour($payable);
        if ($existant && $existant->statut !== 'rejete') {
            return $existant;
        }

        $paiement = Paiement::create([
            'reference'           => Paiement::genererReference(),
            'payable_type'        => $payable->getMorphClass(),
            'payable_id'          => $payable->getKey(),
            'procedure_code'      => $procedureCode,
            'annee_academique_id' => $anneeId ?? AnneeAcademique::context()?->id,
            'montant'             => $montant,
            'devise'              => (string) Setting::get('paiement.devise', 'XOF'),
            'operateur'           => (string) Setting::get('paiement.operateur_defaut', 'tmoney'),
            'statut'              => 'en_attente',
        ]);

        AuditLog::record('paiement.cree', $paiement,
            "Frais à régler : {$paiement->montant_formate} — {$paiement->reference}",
            ['procedure' => $procedureCode, 'montant' => $montant]);

        return $paiement;
    }

    /** Paiement actif d'un dossier. */
    public function pour(Model $payable): ?Paiement
    {
        return Paiement::where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Le dossier est-il en règle ? Vrai aussi quand la procédure est
     * gratuite ou le module désactivé.
     */
    public function enRegle(Model $payable, string $procedureCode): bool
    {
        if ($this->montantPour($procedureCode) <= 0) {
            return true;
        }

        return (bool) $this->pour($payable)?->estPaye();
    }

    /**
     * L'usager déclare sa transaction. Avec une passerelle automatique,
     * la confirmation est immédiate ; sinon le dossier part en vérification.
     *
     * @return array{ok:bool, message:string}
     */
    public function declarer(Paiement $paiement, string $transactionId, ?string $numeroPayeur, ?string $operateur = null): array
    {
        if (!$paiement->peutDeclarer()) {
            return ['ok' => false, 'message' => 'Ce paiement ne peut plus être déclaré.'];
        }

        $doublon = Paiement::where('transaction_id', $transactionId)
            ->whereIn('statut', ['soumis', 'valide'])
            ->where('id', '<>', $paiement->id)
            ->exists();
        if ($doublon) {
            return ['ok' => false, 'message' => "Cet identifiant de transaction est déjà utilisé pour un autre dossier."];
        }

        $paiement->fill([
            'transaction_id' => $transactionId,
            'numero_payeur'  => $numeroPayeur,
            'operateur'      => $operateur ?: $paiement->operateur,
            'declare_at'     => now(),
            'statut'         => 'soumis',
            'motif_rejet'    => null,
        ]);

        // Prêt pour l'API : dès qu'une passerelle automatique existe,
        // la confirmation devient immédiate sans toucher à ce parcours.
        $reponse = $this->passerelle->verifier($paiement);
        if ($reponse['confirme']) {
            $paiement->statut       = 'valide';
            $paiement->valide_at    = now();
            $paiement->verifie_auto = true;
            $paiement->meta         = $reponse['meta'];
        }
        $paiement->save();

        AuditLog::record('paiement.declare', $paiement,
            "Transaction déclarée pour {$paiement->reference} ({$paiement->montant_formate})"
                . ($paiement->estPaye() ? ' — confirmée automatiquement' : ' — en attente de vérification'),
            ['transaction' => $transactionId, 'operateur' => $paiement->operateur]);

        return [
            'ok'      => true,
            'message' => $paiement->estPaye()
                ? 'Paiement confirmé, merci.'
                : "Transaction enregistrée. Un agent la vérifie, votre dossier reste consultable.",
        ];
    }

    /** Rapprochement par un agent : paiement confirmé. */
    public function valider(Paiement $paiement, User $agent): void
    {
        $paiement->update([
            'statut'      => 'valide',
            'valide_par'  => $agent->id,
            'valide_at'   => now(),
            'motif_rejet' => null,
        ]);

        AuditLog::record('paiement.valide', $paiement,
            "Paiement {$paiement->reference} validé ({$paiement->montant_formate})",
            ['transaction' => $paiement->transaction_id]);
    }

    /** Rapprochement par un agent : versement introuvable ou incorrect. */
    public function rejeter(Paiement $paiement, User $agent, string $motif): void
    {
        $paiement->update([
            'statut'      => 'rejete',
            'motif_rejet' => $motif,
            'valide_par'  => $agent->id,
            'valide_at'   => now(),
        ]);

        AuditLog::record('paiement.rejete', $paiement,
            "Paiement {$paiement->reference} rejeté — {$motif}",
            ['transaction' => $paiement->transaction_id]);
    }
}
